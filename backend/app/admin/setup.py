from starlette.requests import Request
from sqladmin import Admin, ModelView
from sqladmin.authentication import AuthenticationBackend
from sqlalchemy import select

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
            request.session.update(
                {
                    "admin_user_id": user.id,
                    "is_superuser": user.is_superuser,
                    "escola_ids": [vinculo.escola_id for vinculo in vinculos],
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


class TenantViewMixin:
    """Filtra modelos com escola_id; relações indiretas permanecem globais."""

    def list_query(self, request: Request):
        query = super().list_query(request)
        if request.session.get("is_superuser"):
            return query
        escola_ids = request.session.get("escola_ids", [])
        if self.model is Escola:
            return query.where(Escola.id.in_(escola_ids))
        if hasattr(self.model, "escola_id"):
            return query.where(self.model.escola_id.in_(escola_ids))
        return query


class EscolaAdmin(TenantViewMixin, ModelView, model=Escola):
    name = "Escola"
    name_plural = "Escolas"


class UserAdmin(ModelView, model=User):
    name = "Usuário"
    name_plural = "Usuários"
    column_exclude_list = [User.password]

    def list_query(self, request: Request):
        query = super().list_query(request)
        if request.session.get("is_superuser"):
            return query
        return (
            query.join(EscolaUser, EscolaUser.user_id == User.id)
            .where(
                EscolaUser.escola_id.in_(
                    request.session.get("escola_ids", [])
                ),
                EscolaUser.status == "ativo",
            )
            .distinct()
        )

    async def on_model_change(
        self, data: dict, model: User, is_created: bool, request: Request
    ) -> None:
        del model, is_created, request
        password = data.get("password")
        if password and not str(password).startswith(("$2a$", "$2b$", "$2y$")):
            data["password"] = hash_password(str(password))


class EscolaUserAdmin(TenantViewMixin, ModelView, model=EscolaUser):
    name = "Vínculo"
    name_plural = "Vínculos de escola"


class SerieAdmin(ModelView, model=Serie):
    name = "Série"
    name_plural = "Séries"


class TurmaAdmin(TenantViewMixin, ModelView, model=Turma):
    name = "Turma"
    name_plural = "Turmas"


class AlunoAdmin(TenantViewMixin, ModelView, model=Aluno):
    name = "Aluno"
    name_plural = "Alunos"


class QuestionarioAdmin(TenantViewMixin, ModelView, model=Questionario):
    name = "Questionário"
    name_plural = "Questionários"


class CategoriaAdmin(TenantViewMixin, ModelView, model=Categoria):
    name = "Categoria"
    name_plural = "Categorias"


class SubcategoriaAdmin(TenantViewMixin, ModelView, model=Subcategoria):
    name = "Subcategoria"
    name_plural = "Subcategorias"


class PerguntaAdmin(TenantViewMixin, ModelView, model=Pergunta):
    name = "Pergunta"
    name_plural = "Perguntas"


class OpcaoRespostaAdmin(TenantViewMixin, ModelView, model=OpcaoResposta):
    name = "Opção de resposta"
    name_plural = "Opções de resposta"


class RegraClassificacaoAdmin(
    TenantViewMixin, ModelView, model=RegraClassificacao
):
    name = "Regra de classificação"
    name_plural = "Regras de classificação"


class AplicacaoAdmin(TenantViewMixin, ModelView, model=AplicacaoQuestionario):
    name = "Aplicação"
    name_plural = "Aplicações"


class RespostaAdmin(ModelView, model=Resposta):
    name = "Resposta"
    name_plural = "Respostas"


class ResultadoAdmin(ModelView, model=Resultado):
    name = "Resultado"
    name_plural = "Resultados"


class AvatarAdmin(TenantViewMixin, ModelView, model=Avatar):
    name = "Avatar"
    name_plural = "Avatares"


class TermoAceiteAdmin(ModelView, model=TermoAceite):
    name = "Termo aceito"
    name_plural = "Termos aceitos"


VIEWS = [
    EscolaAdmin,
    UserAdmin,
    EscolaUserAdmin,
    SerieAdmin,
    TurmaAdmin,
    AlunoAdmin,
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
        authentication_backend=authentication,
        base_url="/admin",
    )
    for view in VIEWS:
        admin.add_view(view)
    return admin
