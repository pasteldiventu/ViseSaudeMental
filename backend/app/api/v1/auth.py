from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, Request
from sqlalchemy import select
from sqlalchemy.orm import Session

from app.database import get_db
from app.deps import get_current_aluno
from app.models import Aluno
from app.schemas.aluno_api import (
    AlunoMe,
    LoginRequest,
    LoginResponse,
    StatusResponse,
)
from app.security.tokens import create_aluno_token, revoke_aluno_token


router = APIRouter(prefix="", tags=["autenticação"])


@router.post("/login", response_model=LoginResponse)
def login(payload: LoginRequest, db: Annotated[Session, Depends(get_db)]):
    aluno = db.scalar(
        select(Aluno).where(
            Aluno.cpf == payload.cpf,
            Aluno.data_nascimento == payload.data_nascimento,
            Aluno.deleted_at.is_(None),
        )
    )
    if aluno is None:
        raise HTTPException(status_code=422, detail="Credenciais inválidas.")
    return LoginResponse(
        token=create_aluno_token(db, aluno),
        aluno=AlunoMe.model_validate(aluno),
    )


@router.post("/logout", response_model=StatusResponse)
def logout(
    request: Request,
    aluno: Annotated[Aluno, Depends(get_current_aluno)],
    db: Annotated[Session, Depends(get_db)],
):
    del aluno
    authorization = request.headers.get("Authorization", "")
    token = authorization.removeprefix("Bearer ").strip()
    revoke_aluno_token(db, token)
    return {"status": "ok"}


@router.get("/me", response_model=AlunoMe)
def me(aluno: Annotated[Aluno, Depends(get_current_aluno)]):
    return aluno
