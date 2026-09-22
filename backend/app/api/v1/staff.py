"""API do app de equipe (admin escola, pesquisador, professor)."""

from __future__ import annotations

import secrets
import string
from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy import func, select
from sqlalchemy.orm import Session, selectinload

from app.admin.roles import (
    ROLE_ADMIN_ESCOLA,
    ROLE_LABELS,
    ROLE_PESQUISADOR,
    ROLE_PROFESSOR,
    STAFF_ROLES,
)
from app.config import settings
from app.database import get_db
from app.deps import get_current_user
from app.models import (
    AplicacaoQuestionario,
    Escola,
    EscolaUser,
    ProfessorTurma,
    Questionario,
    Resposta,
    Resultado,
    Turma,
    User,
)
from app.schemas.staff_api import (
    CriarSalaRequest,
    QuestionarioStaffItem,
    SalaItem,
    SalasResponse,
    StaffEscolaResumo,
    StaffLoginRequest,
    StaffLoginResponse,
    StaffMe,
    TurmaStaffItem,
)
from app.security.passwords import verify_password
from app.security.tokens import create_access_token


router = APIRouter(prefix="/staff", tags=["staff"])

Db = Annotated[Session, Depends(get_db)]
CurrentUser = Annotated[User, Depends(get_current_user)]


def _gerar_codigo(db: Session) -> str:
    alphabet = string.ascii_uppercase + string.digits
    for _ in range(40):
        codigo = "".join(secrets.choice(alphabet) for _ in range(6))
        existe = db.scalar(
            select(AplicacaoQuestionario.id).where(
                AplicacaoQuestionario.codigo_sala == codigo
            )
        )
        if existe is None:
            return codigo
    raise HTTPException(
        status_code=500, detail="Não foi possível gerar código da sala."
    )


def _vinculos_ativos(db: Session, user: User) -> list[EscolaUser]:
    return list(
        db.scalars(
            select(EscolaUser)
            .options(selectinload(EscolaUser.escola))
            .where(
                EscolaUser.user_id == user.id,
                EscolaUser.status == "ativo",
                EscolaUser.role.in_(STAFF_ROLES),
            )
        ).all()
    )


def _staff_me(db: Session, user: User) -> StaffMe:
    vinculos = _vinculos_ativos(db, user)
    escolas = [
        StaffEscolaResumo(
            id=v.escola_id,
            nome=v.escola.nome if v.escola else f"Escola #{v.escola_id}",
            role=v.role,
            role_label=ROLE_LABELS.get(v.role, v.role),
        )
        for v in vinculos
    ]
    if user.is_superuser and not escolas:
        todas = db.scalars(select(Escola).where(Escola.deleted_at.is_(None))).all()
        escolas = [
            StaffEscolaResumo(
                id=e.id,
                nome=e.nome,
                role="superuser",
                role_label="Administrador geral",
            )
            for e in todas
        ]
    return StaffMe(
        id=user.id,
        name=user.name,
        email=user.email,
        is_superuser=user.is_superuser,
        escolas=escolas,
    )


def _roles_na_escola(db: Session, user: User, escola_id: int) -> set[str]:
    if user.is_superuser:
        return set(STAFF_ROLES)
    return {v.role for v in _vinculos_ativos(db, user) if v.escola_id == escola_id}


def _pode_acessar_escola(db: Session, user: User, escola_id: int) -> bool:
    return bool(_roles_na_escola(db, user, escola_id))


def _turma_ids_professor(db: Session, user: User) -> list[int]:
    return list(
        db.scalars(
            select(ProfessorTurma.turma_id).where(ProfessorTurma.user_id == user.id)
        ).all()
    )


def _link_sala(codigo: str) -> str:
    base = settings.public_app_url.rstrip("/")
    return f"{base}/?sala={codigo}"


def _sala_item(db: Session, aplicacao: AplicacaoQuestionario) -> SalaItem:
    codigo = aplicacao.codigo_sala or ""
    respondentes = (
        db.scalar(
            select(func.count(func.distinct(Resposta.aluno_id))).where(
                Resposta.aplicacao_id == aplicacao.id
            )
        )
        or 0
    )
    concluidos = (
        db.scalar(
            select(func.count(Resultado.id)).where(
                Resultado.aplicacao_id == aplicacao.id
            )
        )
        or 0
    )
    return SalaItem(
        id=aplicacao.id,
        codigo=codigo,
        link=_link_sala(codigo) if codigo else "",
        status=aplicacao.status,
        alvo_tipo=aplicacao.alvo_tipo,
        questionario_id=aplicacao.questionario_id,
        questionario_nome=aplicacao.questionario.nome
        if aplicacao.questionario
        else f"#{aplicacao.questionario_id}",
        escola_id=aplicacao.escola_id,
        escola_nome=aplicacao.escola.nome
        if aplicacao.escola
        else f"#{aplicacao.escola_id}",
        turma_id=aplicacao.turma_id,
        turma_nome=aplicacao.turma.nome if aplicacao.turma else None,
        inicia_em=aplicacao.inicia_em,
        termina_em=aplicacao.termina_em,
        respondentes=respondentes,
        concluidos=concluidos,
    )


@router.post("/login", response_model=StaffLoginResponse)
def staff_login(payload: StaffLoginRequest, db: Db):
    email = payload.email.strip().lower()
    user = db.scalar(
        select(User).where(User.email == email, User.deleted_at.is_(None))
    )
    if user is None or not verify_password(payload.password, user.password):
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            detail="E-mail ou senha inválidos.",
        )
    vinculos = _vinculos_ativos(db, user)
    if not user.is_superuser and not vinculos:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Usuário sem perfil de equipe ativo.",
        )
    token = create_access_token(user.id)
    return StaffLoginResponse(token=token, user=_staff_me(db, user))


@router.get("/me", response_model=StaffMe)
def staff_me(user: CurrentUser, db: Db):
    vinculos = _vinculos_ativos(db, user)
    if not user.is_superuser and not vinculos:
        raise HTTPException(status_code=403, detail="Sem perfil de equipe.")
    return _staff_me(db, user)


@router.get("/questionarios", response_model=list[QuestionarioStaffItem])
def listar_questionarios(
    user: CurrentUser,
    db: Db,
    escola_id: int | None = None,
):
    query = select(Questionario).where(
        Questionario.deleted_at.is_(None),
        Questionario.status == "publicado",
    )
    vinculos = _vinculos_ativos(db, user)
    if user.is_superuser:
        if escola_id is not None:
            query = query.where(Questionario.escola_id == escola_id)
    else:
        escolas = {v.escola_id for v in vinculos}
        if escola_id is not None:
            if escola_id not in escolas:
                raise HTTPException(status_code=403, detail="Escola fora do seu escopo.")
            escolas = {escola_id}
        query = query.where(Questionario.escola_id.in_(escolas))
        so_pesquisador = all(v.role == ROLE_PESQUISADOR for v in vinculos) and not any(
            v.role in {ROLE_ADMIN_ESCOLA, ROLE_PROFESSOR} for v in vinculos
        )
        if so_pesquisador:
            query = query.where(
                (Questionario.pesquisador_id == user.id)
                | (Questionario.compartilhado_na_escola.is_(True))
            )
    return list(db.scalars(query.order_by(Questionario.nome, Questionario.id)).all())


@router.get("/turmas", response_model=list[TurmaStaffItem])
def listar_turmas(
    user: CurrentUser,
    db: Db,
    escola_id: int | None = None,
):
    query = (
        select(Turma)
        .options(selectinload(Turma.serie))
        .where(Turma.deleted_at.is_(None))
    )
    vinculos = _vinculos_ativos(db, user)
    if user.is_superuser:
        if escola_id is not None:
            query = query.where(Turma.escola_id == escola_id)
    else:
        escolas = {v.escola_id for v in vinculos}
        if escola_id is not None:
            if escola_id not in escolas:
                raise HTTPException(status_code=403, detail="Escola fora do seu escopo.")
            escolas = {escola_id}
        query = query.where(Turma.escola_id.in_(escolas))
        roles = {v.role for v in vinculos if v.escola_id in escolas}
        if ROLE_PROFESSOR in roles and ROLE_ADMIN_ESCOLA not in roles:
            turmas_prof = _turma_ids_professor(db, user)
            query = query.where(Turma.id.in_(turmas_prof or [-1]))
    turmas = db.scalars(query.order_by(Turma.nome, Turma.id)).all()
    return [
        TurmaStaffItem(
            id=t.id,
            nome=t.nome,
            turno=t.turno,
            escola_id=t.escola_id,
            serie=t.serie.descricao if t.serie else None,
        )
        for t in turmas
    ]


@router.get("/salas", response_model=SalasResponse)
def listar_salas(
    user: CurrentUser,
    db: Db,
    escola_id: int | None = None,
):
    query = (
        select(AplicacaoQuestionario)
        .options(
            selectinload(AplicacaoQuestionario.questionario),
            selectinload(AplicacaoQuestionario.escola),
            selectinload(AplicacaoQuestionario.turma),
        )
        .where(AplicacaoQuestionario.deleted_at.is_(None))
    )
    vinculos = _vinculos_ativos(db, user)
    if user.is_superuser:
        if escola_id is not None:
            query = query.where(AplicacaoQuestionario.escola_id == escola_id)
    else:
        escolas = {v.escola_id for v in vinculos}
        if escola_id is not None:
            if escola_id not in escolas:
                raise HTTPException(status_code=403, detail="Escola fora do seu escopo.")
            escolas = {escola_id}
        query = query.where(AplicacaoQuestionario.escola_id.in_(escolas))
        roles = {v.role for v in vinculos if v.escola_id in escolas}
        if ROLE_PROFESSOR in roles and ROLE_ADMIN_ESCOLA not in roles:
            turmas_prof = _turma_ids_professor(db, user)
            query = query.where(
                (AplicacaoQuestionario.alvo_tipo == "escola")
                | (AplicacaoQuestionario.turma_id.in_(turmas_prof or [-1]))
            )
    apps = list(db.scalars(query.order_by(AplicacaoQuestionario.id.desc())).all())
    dirty = False
    for app in apps:
        if not app.codigo_sala:
            app.codigo_sala = _gerar_codigo(db)
            dirty = True
    if dirty:
        db.commit()
        for app in apps:
            db.refresh(app)
    return SalasResponse(data=[_sala_item(db, app) for app in apps])


@router.post("/salas", response_model=SalaItem, status_code=201)
def criar_sala(payload: CriarSalaRequest, user: CurrentUser, db: Db):
    if not _pode_acessar_escola(db, user, payload.escola_id):
        raise HTTPException(status_code=403, detail="Escola fora do seu escopo.")
    roles = _roles_na_escola(db, user, payload.escola_id)

    questionario = db.get(Questionario, payload.questionario_id)
    if (
        questionario is None
        or questionario.deleted_at is not None
        or questionario.status != "publicado"
        or questionario.escola_id != payload.escola_id
    ):
        raise HTTPException(status_code=422, detail="Questionário inválido.")

    if ROLE_PESQUISADOR in roles and ROLE_ADMIN_ESCOLA not in roles:
        if (
            questionario.pesquisador_id != user.id
            and not questionario.compartilhado_na_escola
        ):
            raise HTTPException(
                status_code=403,
                detail="Você só pode liberar seus questionários ou os compartilhados.",
            )

    turma_id = None
    if payload.alvo_tipo == "turma":
        if payload.turma_id is None:
            raise HTTPException(status_code=422, detail="Informe a turma.")
        turma = db.get(Turma, payload.turma_id)
        if turma is None or turma.escola_id != payload.escola_id:
            raise HTTPException(status_code=422, detail="Turma inválida.")
        if ROLE_PROFESSOR in roles and ROLE_ADMIN_ESCOLA not in roles:
            if turma.id not in _turma_ids_professor(db, user):
                raise HTTPException(
                    status_code=403, detail="Turma fora do seu vínculo."
                )
        turma_id = turma.id
    elif ROLE_PROFESSOR in roles and ROLE_ADMIN_ESCOLA not in roles:
        raise HTTPException(
            status_code=403,
            detail="Professor só pode criar sala para as suas turmas.",
        )

    aplicacao = AplicacaoQuestionario(
        questionario_id=questionario.id,
        escola_id=payload.escola_id,
        alvo_tipo=payload.alvo_tipo,
        turma_id=turma_id,
        status="ativa",
        codigo_sala=_gerar_codigo(db),
    )
    db.add(aplicacao)
    db.commit()
    aplicacao = db.scalar(
        select(AplicacaoQuestionario)
        .options(
            selectinload(AplicacaoQuestionario.questionario),
            selectinload(AplicacaoQuestionario.escola),
            selectinload(AplicacaoQuestionario.turma),
        )
        .where(AplicacaoQuestionario.id == aplicacao.id)
    )
    return _sala_item(db, aplicacao)


@router.post("/salas/{sala_id}/encerrar", response_model=SalaItem)
def encerrar_sala(sala_id: int, user: CurrentUser, db: Db):
    aplicacao = db.scalar(
        select(AplicacaoQuestionario)
        .options(
            selectinload(AplicacaoQuestionario.questionario),
            selectinload(AplicacaoQuestionario.escola),
            selectinload(AplicacaoQuestionario.turma),
        )
        .where(
            AplicacaoQuestionario.id == sala_id,
            AplicacaoQuestionario.deleted_at.is_(None),
        )
    )
    if aplicacao is None:
        raise HTTPException(status_code=404, detail="Sala não encontrada.")
    if not _pode_acessar_escola(db, user, aplicacao.escola_id):
        raise HTTPException(status_code=403, detail="Sem permissão.")
    aplicacao.status = "encerrada"
    db.commit()
    db.refresh(aplicacao)
    return _sala_item(db, aplicacao)
