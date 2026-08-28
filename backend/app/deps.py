from typing import Annotated

from fastapi import Depends, Header, HTTPException, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer
from sqlalchemy import select
from sqlalchemy.orm import Session

from app.database import get_db
from app.models import Aluno, EscolaUser, User
from app.security.tokens import decode_token, find_aluno_by_token


bearer_scheme = HTTPBearer(auto_error=False)
DbSession = Annotated[Session, Depends(get_db)]
BearerCredentials = Annotated[
    HTTPAuthorizationCredentials | None, Depends(bearer_scheme)
]


def _plain_bearer(credentials: BearerCredentials) -> str:
    if credentials is None or credentials.scheme.lower() != "bearer":
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Token de autenticação não informado.",
            headers={"WWW-Authenticate": "Bearer"},
        )
    return credentials.credentials


def get_current_aluno(db: DbSession, credentials: BearerCredentials) -> Aluno:
    found = find_aluno_by_token(db, _plain_bearer(credentials))
    if found is None:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Token inválido ou expirado.",
            headers={"WWW-Authenticate": "Bearer"},
        )
    return found[0]


def get_current_user(db: DbSession, credentials: BearerCredentials) -> User:
    try:
        payload = decode_token(_plain_bearer(credentials))
        user_id = int(payload["sub"])
    except (ValueError, KeyError, TypeError):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Token inválido ou expirado.",
            headers={"WWW-Authenticate": "Bearer"},
        ) from None
    user = db.get(User, user_id)
    if user is None:
        raise HTTPException(status_code=401, detail="Usuário não encontrado.")
    return user


def get_tenant_escola_id(
    db: DbSession,
    user: Annotated[User, Depends(get_current_user)],
    x_escola_id: Annotated[int | None, Header(alias="X-Escola-Id")] = None,
) -> int | None:
    if x_escola_id is None or user.is_superuser:
        return x_escola_id
    vinculo = db.scalar(
        select(EscolaUser.id).where(
            EscolaUser.user_id == user.id,
            EscolaUser.escola_id == x_escola_id,
            EscolaUser.status == "ativo",
        )
    )
    if vinculo is None:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Usuário sem vínculo ativo com a escola.",
        )
    return x_escola_id


__all__ = [
    "get_db",
    "get_current_aluno",
    "get_current_user",
    "get_tenant_escola_id",
]
