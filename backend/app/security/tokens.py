import hashlib
import secrets
from datetime import datetime, timedelta, timezone
from typing import Any

from jose import JWTError, jwt
from sqlalchemy import delete, select
from sqlalchemy.orm import Session

from app.config import settings
from app.models import Aluno, PersonalAccessToken


ALGORITHM = "HS256"


def create_access_token(
    subject: str | int, expires_delta: timedelta | None = None
) -> str:
    expires = datetime.now(timezone.utc) + (
        expires_delta or timedelta(minutes=settings.access_token_expire_minutes)
    )
    return jwt.encode(
        {"sub": str(subject), "exp": expires},
        settings.secret_key,
        algorithm=ALGORITHM,
    )


def decode_token(token: str) -> dict[str, Any]:
    try:
        return jwt.decode(token, settings.secret_key, algorithms=[ALGORITHM])
    except JWTError as exc:
        raise ValueError("Token inválido ou expirado.") from exc


def _token_hash(plain_token: str) -> str:
    return hashlib.sha256(plain_token.encode("utf-8")).hexdigest()


def create_aluno_token(db: Session, aluno: Aluno, name: str = "mobile") -> str:
    plain = secrets.token_urlsafe(40)
    db.execute(
        delete(PersonalAccessToken).where(
            PersonalAccessToken.tokenable_type == "aluno",
            PersonalAccessToken.tokenable_id == aluno.id,
        )
    )
    db.add(
        PersonalAccessToken(
            tokenable_type="aluno",
            tokenable_id=aluno.id,
            name=name,
            token=_token_hash(plain),
        )
    )
    db.commit()
    return plain


def find_aluno_by_token(
    db: Session, plain_token: str
) -> tuple[Aluno, PersonalAccessToken] | None:
    token = db.scalar(
        select(PersonalAccessToken).where(
            PersonalAccessToken.token == _token_hash(plain_token),
            PersonalAccessToken.tokenable_type == "aluno",
        )
    )
    if token is None:
        return None
    if token.expires_at and token.expires_at <= datetime.utcnow():
        db.delete(token)
        db.commit()
        return None
    aluno = db.get(Aluno, token.tokenable_id)
    if aluno is None:
        return None
    token.last_used_at = datetime.utcnow()
    db.commit()
    return aluno, token


def revoke_aluno_token(db: Session, plain_token: str) -> bool:
    token = db.scalar(
        select(PersonalAccessToken).where(
            PersonalAccessToken.token == _token_hash(plain_token),
            PersonalAccessToken.tokenable_type == "aluno",
        )
    )
    if token is None:
        return False
    db.delete(token)
    db.commit()
    return True
