from fastapi import HTTPException
from sqladmin import Admin, ModelView, action
from sqladmin.authentication import AuthenticationBackend
from sqlalchemy import select
from starlette.requests import Request
from starlette.responses import RedirectResponse

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
from app.security.passwords import hash_password, verify_password
from app.services.limpar_aplicacao import LimparAplicacaoService
from app.services.publicar import PublicarQuestionarioService


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

    def is_accessible(self, request: Request) -> bool:
        return is_superuser(request) or can_access(request, self.resource)


class UserAdmin(RoleAwareMixin, ModelView, model=User):
    resource = "user"
    name = "Usuário"
    name_plural = "Usuários"
    column_list = [User.id, User.name, User.email, User.is_superuser]
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
        EscolaUser.id,
        EscolaUser.user,
        EscolaUser.escola,
        EscolaUser.role,
        EscolaUser.status,
    ]
    column_labels = {EscolaUser.role: "Perfil", EscolaUser.status: "Status"}

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


class ProfessorTurmaAdmin(RoleAwareMixin, ModelView, model=ProfessorTurma):
    resource = "professor_turma"
    name = "Professor × turma"
    name_plural = "Professores e turmas"
    column_list = [ProfessorTurma.id, ProfessorTurma.user, ProfessorTurma.turma]

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


class AlunoAdmin(TenantViewMixin, ModelView, model=Aluno):
    resource = "aluno"
    name = "Aluno"
    name_plural = "Alunos"
    column_searchable_list = [Aluno.nome, Aluno.cpf, Aluno.matricula]


class QuestionarioAdmin(TenantViewMixin, ModelView, model=Questionario):
    resource = "questionario"
    name = "Questionário"
    name_plural = "Questionários"
    column_list = [
        Questionario.id,
        Questionario.nome,
        Questionario.status,
        Questionario.versao,
        Questionario.pesquisador,
        Questionario.compartilhado_na_escola,
    ]

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


class SubcategoriaAdmin(TenantViewMixin, ModelView, model=Subcategoria):
    resource = "subcategoria"
    name = "Subcategoria"
    name_plural = "Subcategorias"


class PerguntaAdmin(TenantViewMixin, ModelView, model=Pergunta):
    resource = "pergunta"
    name = "Pergunta"
    name_plural = "Perguntas"


class OpcaoRespostaAdmin(TenantViewMixin, ModelView, model=OpcaoResposta):
    resource = "opcao"
    name = "Opção de resposta"
    name_plural = "Opções de resposta"


class RegraClassificacaoAdmin(
    TenantViewMixin, ModelView, model=RegraClassificacao
):
    resource = "regra"
    name = "Regra de classificação"
    name_plural = "Regras de classificação"


class AplicacaoAdmin(TenantViewMixin, ModelView, model=AplicacaoQuestionario):
    resource = "aplicacao"
    name = "Aplicação"
    name_plural = "Aplicações"
    column_list = [
        AplicacaoQuestionario.id,
        AplicacaoQuestionario.questionario,
        AplicacaoQuestionario.escola,
        AplicacaoQuestionario.alvo_tipo,
        AplicacaoQuestionario.status,
    ]

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
    column_exclude_list = [Resultado.totais_json, Resultado.classificacao_json]

    def list_query(self, request: Request):
        return filtrar_via_aplicacao(super().list_query(request), request)


class AvatarAdmin(TenantViewMixin, ModelView, model=Avatar):
    resource = "avatar"
    name = "Avatar"
    name_plural = "Avatares"


class TermoAceiteAdmin(RoleAwareMixin, ModelView, model=TermoAceite):
    resource = "termo"
    name = "Termo aceito"
    name_plural = "Termos aceitos"
    force_readonly = True
    can_create = False
    can_edit = False
    can_delete = False


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
        title="Vise Saúde Mental",
        logo_url="/static/logo.jpeg",
        authentication_backend=authentication,
        base_url="/admin",
    )
    for view in VIEWS:
        admin.add_view(view)
    return admin
