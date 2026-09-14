from pathlib import Path

from fastapi import HTTPException
from sqladmin import Admin, ModelView, action
from sqladmin.authentication import AuthenticationBackend
from sqlalchemy import select
from starlette.requests import Request
from starlette.responses import RedirectResponse
from wtforms import SelectField

from app.admin.custom_views import ImportAlunosView
from app.admin.roles import (
    ROLE_LABELS,
    ROLE_PROFESSOR,
    STAFF_ROLES,
    can_access,
    can_write,
    is_pesquisador_only,
    is_superuser,
)
from app.admin.scope import filtrar_instrumento, filtrar_professor, filtrar_via_aplicacao
from app.config import settings
from app.database import SessionLocal, engine
from app.models import (
    Aluno,
    AplicacaoQuestionario,
    Avatar,
    Categoria,
    Escola,
    EscolaUser,
    OpcaoResposta,
    Pergunta,
    ProfessorTurma,
    Questionario,
    RegraClassificacao,
    Resposta,
    Resultado,
    Serie,
    Subcategoria,
    TermoAceite,
    Turma,
    User,
)
from app.models.entities import _mascarar_cpf
from app.security.passwords import hash_password, verify_password
from app.services.limpar_aplicacao import LimparAplicacaoService
from app.services.publicar import PublicarQuestionarioService

STATUS_VINCULO = {"ativo": "Ativo", "inativo": "Inativo"}
STATUS_QUESTIONARIO = {"rascunho": "Rascunho", "publicado": "Publicado"}
STATUS_APLICACAO = {"ativa": "Ativa", "encerrada": "Encerrada"}
ALVO_TIPO = {"escola": "Toda a escola", "turma": "Turma", "aluno": "Aluno"}
TIPOS_PERGUNTA = {
    "multipla_escolha": "Múltipla escolha",
    "texto": "Texto livre",
}
TURNOS = {
    "matutino": "Matutino",
    "vespertino": "Vespertino",
    "noturno": "Noturno",
    "integral": "Integral",
}


def _choices(mapping: dict[str, str]) -> list[tuple[str, str]]:
    return list(mapping.items())


def _rotulo(mapping: dict[str, str], value: str | None) -> str:
    if not value:
        return "—"
    return mapping.get(value, value)


def _fmt_data(value) -> str:
    if value is None:
        return "—"
    return value.strftime("%d/%m/%Y")


def _fmt_data_hora(value) -> str:
    if value is None:
        return "—"
    return value.strftime("%d/%m/%Y %H:%M")


def _fmt_resumo(value: str | None, limite: int = 80) -> str:
    texto = (value or "").strip() or "—"
    if len(texto) > limite:
        return texto[: limite - 1] + "…"
    return texto


class AdminAuthentication(AuthenticationBackend):
    async def login(self, request: Request) -> bool:
        form = await request.form()
        email = str(form.get("username", "")).strip().lower()
        password = str(form.get("password", ""))
        with SessionLocal() as db:
            user = db.scalar(select(User).where(User.email == email))
            if user is None or not verify_password(password, user.password):
                return False
            vinculos = db.scalars(
                select(EscolaUser).where(
                    EscolaUser.user_id == user.id,
                    EscolaUser.status == "ativo",
                )
            ).all()
            if not user.is_superuser and not vinculos:
                return False
            roles = sorted({vinculo.role for vinculo in vinculos if vinculo.role})
            turma_ids_sessao: list[int] = []
            if ROLE_PROFESSOR in roles:
                turma_ids_sessao = list(
                    db.scalars(
                        select(ProfessorTurma.turma_id).where(
                            ProfessorTurma.user_id == user.id
                        )
                    ).all()
                )
            request.session.update(
                {
                    "admin_user_id": user.id,
                    "admin_user_name": user.name,
                    "admin_user_email": user.email,
                    "is_superuser": user.is_superuser,
                    "escola_ids": [vinculo.escola_id for vinculo in vinculos],
                    "roles": roles,
                    "turma_ids": turma_ids_sessao,
                }
            )
            return True

    async def logout(self, request: Request) -> bool:
        request.session.clear()
        return True

    async def authenticate(self, request: Request) -> bool:
        user_id = request.session.get("admin_user_id")
        if not user_id:
            return False
        with SessionLocal() as db:
            user = db.get(User, int(user_id))
            if user is None:
                request.session.clear()
                return False
            request.session["admin_user_name"] = user.name
            request.session["admin_user_email"] = user.email
            if user.is_superuser:
                return True
            return (
                db.scalar(
                    select(EscolaUser.id).where(
                        EscolaUser.user_id == user.id,
                        EscolaUser.status == "ativo",
                    )
                )
                is not None
            )


class RoleAwareMixin:
    """Menu por papel. Escrita bloqueada em on_model_change quando read-only."""

    resource: str = ""
    force_readonly: bool = False

    def is_accessible(self, request: Request) -> bool:
        return can_access(request, self.resource)

    def is_visible(self, request: Request) -> bool:
        return self.is_accessible(request)

    def _ensure_writable(self, request: Request) -> None:
        if self.force_readonly or not can_write(request, self.resource):
            raise PermissionError("Seu perfil não pode alterar este registro.")

    async def on_model_change(
        self, data: dict, model, is_created: bool, request: Request
    ) -> None:
        self._ensure_writable(request)
        await super().on_model_change(data, model, is_created, request)

    async def on_model_delete(self, model, request: Request) -> None:
        self._ensure_writable(request)
        await super().on_model_delete(model, request)


def _pks(request: Request) -> list[int]:
    raw = request.query_params.get("pks") or ""
    ids: list[int] = []
    for item in raw.split(","):
        item = item.strip()
        if item.isdigit():
            ids.append(int(item))
    return ids


def _redirect_list(identity: str) -> RedirectResponse:
    return RedirectResponse(f"/admin/{identity}/list", status_code=302)


class TenantViewMixin(RoleAwareMixin):
    def list_query(self, request: Request):
        query = super().list_query(request)
        if is_superuser(request):
            query = query
        else:
            escola_ids = request.session.get("escola_ids", [])
            if self.model is Escola:
                query = query.where(Escola.id.in_(escola_ids))
            elif hasattr(self.model, "escola_id"):
                query = query.where(self.model.escola_id.in_(escola_ids))
        query = filtrar_instrumento(query, request, self.model)
        return filtrar_professor(query, request, self.model)

    def form_edit_query(self, request: Request):
        stmt = super().form_edit_query(request)
        if is_pesquisador_only(request):
            stmt = filtrar_instrumento(
                stmt, request, self.model, so_proprios=True
            )
        return stmt


class EscolaAdmin(TenantViewMixin, ModelView, model=Escola):
    resource = "escola"
    name = "Escola"
    name_plural = "Escolas"
    column_list = [
        Escola.nome,
        Escola.municipio,
        Escola.uf,
        Escola.inep,
        Escola.responsavel,
        Escola.ativo,
    ]
    column_searchable_list = [Escola.nome, Escola.inep, Escola.municipio]
    column_labels = {
        Escola.nome: "Nome",
        Escola.municipio: "Município",
        Escola.uf: "UF",
        Escola.inep: "INEP",
        Escola.responsavel: "Responsável",
        Escola.ativo: "Ativa",
    }
    form_excluded_columns = [
        Escola.usuarios_vinculos,
        Escola.turmas,
        Escola.alunos,
        Escola.questionarios,
        Escola.categorias,
        Escola.subcategorias,
        Escola.perguntas,
        Escola.opcoes_resposta,
        Escola.regras_classificacao,
        Escola.aplicacoes,
        Escola.avatars,
    ]

    def is_accessible(self, request: Request) -> bool:
        return is_superuser(request) or can_access(request, self.resource)


class UserAdmin(RoleAwareMixin, ModelView, model=User):
    resource = "user"
    name = "Usuário"
    name_plural = "Usuários"
    column_list = [User.name, User.email, User.is_superuser]
    form_excluded_columns = [
        User.escolas_vinculos,
        User.questionarios,
        User.turmas_professor,
    ]
    column_labels = {
        User.name: "Nome",
        User.email: "E-mail",
        User.is_superuser: "Admin geral",
        User.password: "Senha",
    }
    column_searchable_list = [User.name, User.email]

    def is_accessible(self, request: Request) -> bool:
        return is_superuser(request) or can_access(request, self.resource)

    def list_query(self, request: Request):
        query = super().list_query(request)
        if is_superuser(request):
            return query
        return (
            query.join(EscolaUser, EscolaUser.user_id == User.id)
            .where(
                EscolaUser.escola_id.in_(request.session.get("escola_ids", [])),
                EscolaUser.status == "ativo",
            )
            .distinct()
        )

    async def on_model_change(
        self, data: dict, model: User, is_created: bool, request: Request
    ) -> None:
        self._ensure_writable(request)
        if not is_superuser(request):
            data["is_superuser"] = False
            if not is_created and getattr(model, "is_superuser", False):
                raise PermissionError("Não é possível editar o admin geral.")
        password = data.get("password")
        if password and not str(password).startswith(("$2a$", "$2b$", "$2y$")):
            data["password"] = hash_password(str(password))


class EscolaUserAdmin(TenantViewMixin, ModelView, model=EscolaUser):
    resource = "escola_user"
    name = "Vínculo"
    name_plural = "Vínculos de escola"
    column_list = [
        EscolaUser.user,
        EscolaUser.escola,
        EscolaUser.role,
        EscolaUser.status,
    ]
    column_labels = {
        EscolaUser.user: "Usuário",
        EscolaUser.escola: "Escola",
        EscolaUser.role: "Perfil",
        EscolaUser.status: "Status",
    }
    column_formatters = {
        EscolaUser.role: lambda m, a: _rotulo(ROLE_LABELS, m.role),
        EscolaUser.status: lambda m, a: _rotulo(STATUS_VINCULO, m.status),
    }
    column_formatters_detail = column_formatters
    form_overrides = {"role": SelectField, "status": SelectField}
    form_args = {
        "role": {"choices": _choices(ROLE_LABELS), "coerce": str},
        "status": {"choices": _choices(STATUS_VINCULO), "coerce": str},
    }

    async def on_model_change(
        self, data: dict, model: EscolaUser, is_created: bool, request: Request
    ) -> None:
        self._ensure_writable(request)
        role = str(data.get("role") or getattr(model, "role", "") or "")
        if role and role not in STAFF_ROLES:
            raise ValueError(
                "Perfil inválido. Use: "
                + ", ".join(f"{k} ({v})" for k, v in ROLE_LABELS.items())
            )


class SerieAdmin(RoleAwareMixin, ModelView, model=Serie):
    resource = "serie"
    name = "Série"
    name_plural = "Séries"
    column_list = [Serie.descricao]
    column_labels = {Serie.descricao: "Descrição"}
    form_excluded_columns = [Serie.turmas]


class ProfessorTurmaAdmin(RoleAwareMixin, ModelView, model=ProfessorTurma):
    resource = "professor_turma"
    name = "Professor × turma"
    name_plural = "Professores e turmas"
    column_list = [ProfessorTurma.user, ProfessorTurma.turma]
    column_labels = {
        ProfessorTurma.user: "Professor",
        ProfessorTurma.turma: "Turma",
    }

    def list_query(self, request: Request):
        query = super().list_query(request)
        if is_superuser(request):
            return query
        return query.join(Turma).where(
            Turma.escola_id.in_(request.session.get("escola_ids", []))
        )


class TurmaAdmin(TenantViewMixin, ModelView, model=Turma):
    resource = "turma"
    name = "Turma"
    name_plural = "Turmas"
    column_list = [Turma.nome, Turma.serie, Turma.turno, Turma.escola]
    column_searchable_list = [Turma.nome]
    column_labels = {
        Turma.nome: "Nome",
        Turma.serie: "Série",
        Turma.turno: "Turno",
        Turma.escola: "Escola",
    }
    column_formatters = {
        Turma.turno: lambda m, a: _rotulo(TURNOS, m.turno),
    }
    column_formatters_detail = column_formatters
    form_excluded_columns = [Turma.alunos, Turma.aplicacoes, Turma.professores]
    form_overrides = {"turno": SelectField}
    form_args = {"turno": {"choices": _choices(TURNOS), "coerce": str}}


class AlunoAdmin(TenantViewMixin, ModelView, model=Aluno):
    resource = "aluno"
    name = "Aluno"
    name_plural = "Alunos"
    column_list = [
        Aluno.nome,
        Aluno.cpf,
        Aluno.data_nascimento,
        Aluno.turma,
        Aluno.escola,
        Aluno.matricula,
    ]
    column_searchable_list = [Aluno.nome, Aluno.cpf, Aluno.matricula]
    column_labels = {
        Aluno.nome: "Nome",
        Aluno.cpf: "CPF",
        Aluno.data_nascimento: "Nascimento",
        Aluno.turma: "Turma",
        Aluno.escola: "Escola",
        Aluno.matricula: "Matrícula",
        Aluno.sexo: "Sexo",
        Aluno.responsavel: "Responsável",
        Aluno.contato_responsavel: "Contato do responsável",
    }
    column_formatters = {
        Aluno.cpf: lambda m, a: _mascarar_cpf(m.cpf),
        Aluno.data_nascimento: lambda m, a: _fmt_data(m.data_nascimento),
    }
    column_formatters_detail = column_formatters
    form_excluded_columns = [
        Aluno.aplicacoes,
        Aluno.respostas,
        Aluno.resultados,
        Aluno.termos_aceite,
    ]


class QuestionarioAdmin(TenantViewMixin, ModelView, model=Questionario):
    resource = "questionario"
    name = "Questionário"
    name_plural = "Questionários"
    column_list = [
        Questionario.nome,
        Questionario.status,
        Questionario.versao,
        Questionario.pesquisador,
        Questionario.escola,
        Questionario.compartilhado_na_escola,
    ]
    column_searchable_list = [Questionario.nome]
    column_labels = {
        Questionario.nome: "Nome",
        Questionario.status: "Status",
        Questionario.versao: "Versão",
        Questionario.pesquisador: "Pesquisador",
        Questionario.escola: "Escola",
        Questionario.compartilhado_na_escola: "Compartilhado na escola",
        Questionario.descricao: "Descrição",
        Questionario.publico_alvo: "Público-alvo",
        Questionario.parent: "Versão anterior",
    }
    column_formatters = {
        Questionario.status: lambda m, a: _rotulo(STATUS_QUESTIONARIO, m.status),
    }
    column_formatters_detail = column_formatters
    form_excluded_columns = [
        Questionario.categorias,
        Questionario.regras_classificacao,
        Questionario.aplicacoes,
        Questionario.versoes,
    ]
    form_overrides = {"status": SelectField}
    form_args = {"status": {"choices": _choices(STATUS_QUESTIONARIO), "coerce": str}}
    form_widget_args = {"descricao": {"rows": 4}}

    async def on_model_change(
        self, data: dict, model: Questionario, is_created: bool, request: Request
    ) -> None:
        self._ensure_writable(request)
        if is_pesquisador_only(request):
            user_id = request.session.get("admin_user_id")
            if is_created:
                data["pesquisador"] = user_id
            elif getattr(model, "pesquisador_id", None) != user_id:
                raise PermissionError(
                    "Você só pode editar os seus questionários."
                )
        elif is_created and not data.get("pesquisador"):
            data["pesquisador"] = request.session.get("admin_user_id")

    async def on_model_delete(self, model, request: Request) -> None:
        self._ensure_writable(request)
        if is_pesquisador_only(request) and model.pesquisador_id != request.session.get(
            "admin_user_id"
        ):
            raise PermissionError("Você só pode excluir os seus questionários.")
        await super().on_model_delete(model, request)

    @action(
        name="publicar",
        label="Publicar",
        confirmation_message="Publicar os questionários em rascunho selecionados?",
    )
    async def publicar(self, request: Request):
        self._ensure_writable(request)
        with SessionLocal() as db:
            for pk in _pks(request):
                questionario = db.get(Questionario, pk)
                if questionario is None:
                    continue
                if is_pesquisador_only(request) and questionario.pesquisador_id != request.session.get(
                    "admin_user_id"
                ):
                    continue
                try:
                    PublicarQuestionarioService.publicar(db, questionario)
                except HTTPException:
                    continue
        return _redirect_list(self.identity)

    @action(
        name="nova-versao",
        label="Nova versão",
        confirmation_message="Criar rascunho de nova versão a partir dos publicados?",
    )
    async def nova_versao(self, request: Request):
        self._ensure_writable(request)
        with SessionLocal() as db:
            for pk in _pks(request):
                questionario = db.get(Questionario, pk)
                if questionario is None:
                    continue
                if is_pesquisador_only(request) and questionario.pesquisador_id != request.session.get(
                    "admin_user_id"
                ):
                    continue
                try:
                    PublicarQuestionarioService.criar_nova_versao(db, questionario)
                except HTTPException:
                    continue
        return _redirect_list(self.identity)


class CategoriaAdmin(TenantViewMixin, ModelView, model=Categoria):
    resource = "categoria"
    name = "Categoria"
    name_plural = "Categorias"
    column_list = [
        Categoria.nome,
        Categoria.questionario,
        Categoria.ordem,
        Categoria.cor,
    ]
    column_searchable_list = [Categoria.nome]
    column_labels = {
        Categoria.nome: "Nome",
        Categoria.questionario: "Questionário",
        Categoria.ordem: "Ordem",
        Categoria.cor: "Cor",
        Categoria.mensagem_avatar: "Mensagem do avatar",
        Categoria.imagem_apoio: "Imagem de apoio",
        Categoria.escola: "Escola",
    }
    form_excluded_columns = [
        Categoria.subcategorias,
        Categoria.perguntas,
        Categoria.regras_classificacao,
    ]
    column_details_exclude_list = [
        Categoria.perguntas,
        Categoria.regras_classificacao,
    ]
    form_widget_args = {"mensagem_avatar": {"rows": 3}}


class SubcategoriaAdmin(TenantViewMixin, ModelView, model=Subcategoria):
    resource = "subcategoria"
    name = "Subcategoria"
    name_plural = "Subcategorias"
    column_list = [
        Subcategoria.nome,
        Subcategoria.categoria,
        Subcategoria.ordem,
    ]
    column_searchable_list = [Subcategoria.nome]
    column_labels = {
        Subcategoria.nome: "Nome",
        Subcategoria.categoria: "Categoria",
        Subcategoria.ordem: "Ordem",
        Subcategoria.escola: "Escola",
    }
    form_excluded_columns = [Subcategoria.perguntas]
    column_details_exclude_list = [Subcategoria.perguntas]


class PerguntaAdmin(TenantViewMixin, ModelView, model=Pergunta):
    resource = "pergunta"
    name = "Pergunta"
    name_plural = "Perguntas"
    column_list = [
        Pergunta.texto,
        Pergunta.tipo,
        Pergunta.categoria,
        Pergunta.subcategoria,
        Pergunta.ordem,
        Pergunta.obrigatoria,
    ]
    column_searchable_list = [Pergunta.texto]
    column_sortable_list = [Pergunta.ordem, Pergunta.tipo, Pergunta.texto]
    column_labels = {
        Pergunta.texto: "Texto",
        Pergunta.tipo: "Tipo",
        Pergunta.categoria: "Categoria",
        Pergunta.subcategoria: "Subcategoria",
        Pergunta.ordem: "Ordem",
        Pergunta.obrigatoria: "Obrigatória",
        Pergunta.peso: "Peso",
        Pergunta.imagem: "Imagem",
        Pergunta.escola: "Escola",
    }
    column_formatters = {
        Pergunta.texto: lambda m, a: _fmt_resumo(m.texto),
        Pergunta.tipo: lambda m, a: _rotulo(TIPOS_PERGUNTA, m.tipo),
    }
    column_formatters_detail = {
        Pergunta.tipo: lambda m, a: _rotulo(TIPOS_PERGUNTA, m.tipo),
    }
    form_excluded_columns = [Pergunta.opcoes_resposta, Pergunta.respostas]
    column_details_exclude_list = [Pergunta.respostas]
    form_overrides = {"tipo": SelectField}
    form_args = {"tipo": {"choices": _choices(TIPOS_PERGUNTA), "coerce": str}}
    form_widget_args = {"texto": {"rows": 4}}


class OpcaoRespostaAdmin(TenantViewMixin, ModelView, model=OpcaoResposta):
    resource = "opcao"
    name = "Opção de resposta"
    name_plural = "Opções de resposta"
    column_list = [
        OpcaoResposta.descricao,
        OpcaoResposta.pergunta,
        OpcaoResposta.pontuacao,
        OpcaoResposta.ordem,
        OpcaoResposta.emoji,
    ]
    column_searchable_list = [OpcaoResposta.descricao]
    column_labels = {
        OpcaoResposta.descricao: "Descrição",
        OpcaoResposta.pergunta: "Pergunta",
        OpcaoResposta.pontuacao: "Pontuação",
        OpcaoResposta.ordem: "Ordem",
        OpcaoResposta.emoji: "Emoji",
        OpcaoResposta.cor: "Cor",
        OpcaoResposta.escola: "Escola",
    }
    form_excluded_columns = [OpcaoResposta.respostas]
    column_details_exclude_list = [OpcaoResposta.respostas]


class RegraClassificacaoAdmin(
    TenantViewMixin, ModelView, model=RegraClassificacao
):
    resource = "regra"
    name = "Regra de classificação"
    name_plural = "Regras de classificação"
    column_list = [
        RegraClassificacao.rotulo,
        RegraClassificacao.categoria,
        RegraClassificacao.questionario,
        RegraClassificacao.min_score,
        RegraClassificacao.max_score,
    ]
    column_searchable_list = [RegraClassificacao.rotulo]
    column_labels = {
        RegraClassificacao.rotulo: "Rótulo",
        RegraClassificacao.categoria: "Categoria",
        RegraClassificacao.questionario: "Questionário",
        RegraClassificacao.min_score: "Pontuação mín.",
        RegraClassificacao.max_score: "Pontuação máx.",
        RegraClassificacao.descricao: "Descrição",
        RegraClassificacao.escola: "Escola",
    }
    form_widget_args = {"descricao": {"rows": 3}}


class AplicacaoAdmin(TenantViewMixin, ModelView, model=AplicacaoQuestionario):
    resource = "aplicacao"
    name = "Aplicação"
    name_plural = "Aplicações"
    column_list = [
        AplicacaoQuestionario.questionario,
        AplicacaoQuestionario.escola,
        AplicacaoQuestionario.turma,
        AplicacaoQuestionario.aluno,
        AplicacaoQuestionario.alvo_tipo,
        AplicacaoQuestionario.status,
        AplicacaoQuestionario.inicia_em,
    ]
    column_labels = {
        AplicacaoQuestionario.questionario: "Questionário",
        AplicacaoQuestionario.escola: "Escola",
        AplicacaoQuestionario.turma: "Turma",
        AplicacaoQuestionario.aluno: "Aluno",
        AplicacaoQuestionario.alvo_tipo: "Alvo",
        AplicacaoQuestionario.status: "Status",
        AplicacaoQuestionario.inicia_em: "Início",
        AplicacaoQuestionario.termina_em: "Término",
    }
    column_formatters = {
        AplicacaoQuestionario.alvo_tipo: lambda m, a: _rotulo(
            ALVO_TIPO, m.alvo_tipo
        ),
        AplicacaoQuestionario.status: lambda m, a: _rotulo(
            STATUS_APLICACAO, m.status
        ),
        AplicacaoQuestionario.inicia_em: lambda m, a: _fmt_data_hora(m.inicia_em),
        AplicacaoQuestionario.termina_em: lambda m, a: _fmt_data_hora(
            m.termina_em
        ),
    }
    column_formatters_detail = column_formatters
    form_excluded_columns = [
        AplicacaoQuestionario.respostas,
        AplicacaoQuestionario.resultados,
    ]
    column_details_exclude_list = [
        AplicacaoQuestionario.respostas,
        AplicacaoQuestionario.resultados,
    ]
    form_overrides = {
        "alvo_tipo": SelectField,
        "status": SelectField,
    }
    form_args = {
        "alvo_tipo": {"choices": _choices(ALVO_TIPO), "coerce": str},
        "status": {"choices": _choices(STATUS_APLICACAO), "coerce": str},
    }

    @action(
        name="limpar-respostas",
        label="Limpar respostas (demo)",
        confirmation_message="Apaga respostas e resultados no servidor. Só o admin geral pode fazer isso.",
    )
    async def limpar_respostas(self, request: Request):
        if not is_superuser(request):
            raise PermissionError("Apenas o administrador geral pode limpar respostas.")
        with SessionLocal() as db:
            for pk in _pks(request):
                LimparAplicacaoService.limpar(db, pk)
        return _redirect_list(self.identity)


class RespostaAdmin(RoleAwareMixin, ModelView, model=Resposta):
    resource = "resposta"
    name = "Resposta"
    name_plural = "Respostas"
    force_readonly = True
    can_create = False
    can_edit = False
    can_delete = False
    column_list = [
        Resposta.aluno,
        Resposta.pergunta,
        Resposta.opcao,
        Resposta.texto,
        Resposta.responded_at,
        Resposta.aplicacao,
    ]
    column_labels = {
        Resposta.aluno: "Aluno",
        Resposta.pergunta: "Pergunta",
        Resposta.opcao: "Opção",
        Resposta.texto: "Texto",
        Resposta.responded_at: "Respondido em",
        Resposta.aplicacao: "Aplicação",
        Resposta.dispositivo: "Dispositivo",
        Resposta.tempo_gasto_ms: "Tempo (ms)",
    }
    column_formatters = {
        Resposta.texto: lambda m, a: _fmt_resumo(m.texto, 60),
        Resposta.responded_at: lambda m, a: _fmt_data_hora(m.responded_at),
    }
    column_formatters_detail = {
        Resposta.responded_at: lambda m, a: _fmt_data_hora(m.responded_at),
    }

    def list_query(self, request: Request):
        return filtrar_via_aplicacao(super().list_query(request), request)


class ResultadoAdmin(RoleAwareMixin, ModelView, model=Resultado):
    resource = "resultado"
    name = "Resultado"
    name_plural = "Resultados"
    force_readonly = True
    can_create = False
    can_edit = False
    can_delete = False
    column_list = [Resultado.aluno, Resultado.aplicacao]
    column_labels = {
        Resultado.aluno: "Aluno",
        Resultado.aplicacao: "Aplicação",
        Resultado.totais_json: "Totais",
        Resultado.classificacao_json: "Classificação",
    }

    def list_query(self, request: Request):
        return filtrar_via_aplicacao(super().list_query(request), request)


class AvatarAdmin(TenantViewMixin, ModelView, model=Avatar):
    resource = "avatar"
    name = "Avatar"
    name_plural = "Avatares"
    column_list = [Avatar.nome, Avatar.escola, Avatar.mensagem_padrao]
    column_searchable_list = [Avatar.nome]
    column_labels = {
        Avatar.nome: "Nome",
        Avatar.escola: "Escola",
        Avatar.mensagem_padrao: "Mensagem padrão",
        Avatar.imagem_path: "Imagem",
    }
    column_formatters = {
        Avatar.mensagem_padrao: lambda m, a: _fmt_resumo(m.mensagem_padrao, 60),
    }
    form_widget_args = {"mensagem_padrao": {"rows": 3}}


class TermoAceiteAdmin(RoleAwareMixin, ModelView, model=TermoAceite):
    resource = "termo"
    name = "Termo aceito"
    name_plural = "Termos aceitos"
    force_readonly = True
    can_create = False
    can_edit = False
    can_delete = False
    column_list = [
        TermoAceite.aluno,
        TermoAceite.versao,
        TermoAceite.accepted_at,
    ]
    column_labels = {
        TermoAceite.aluno: "Aluno",
        TermoAceite.versao: "Versão",
        TermoAceite.accepted_at: "Aceito em",
        TermoAceite.ip: "IP",
        TermoAceite.user_agent: "Navegador",
        TermoAceite.texto_hash: "Hash do texto",
    }
    column_formatters = {
        TermoAceite.accepted_at: lambda m, a: _fmt_data_hora(m.accepted_at),
    }
    column_formatters_detail = column_formatters


VIEWS = [
    EscolaAdmin,
    UserAdmin,
    EscolaUserAdmin,
    ProfessorTurmaAdmin,
    SerieAdmin,
    TurmaAdmin,
    AlunoAdmin,
    ImportAlunosView,
    QuestionarioAdmin,
    CategoriaAdmin,
    SubcategoriaAdmin,
    PerguntaAdmin,
    OpcaoRespostaAdmin,
    RegraClassificacaoAdmin,
    AplicacaoAdmin,
    RespostaAdmin,
    ResultadoAdmin,
    AvatarAdmin,
    TermoAceiteAdmin,
]


def setup_admin(app) -> Admin:
    authentication = AdminAuthentication(secret_key=settings.secret_key)
    admin = Admin(
        app,
        engine,
        title="VISE-MT",
        templates_dir=str(Path(__file__).resolve().parent / "templates"),
        authentication_backend=authentication,
        base_url="/admin",
    )
    for view in VIEWS:
        admin.add_view(view)
    return admin
