from typing import Annotated

from fastapi import APIRouter, Depends
from sqlalchemy import case, or_, select
from sqlalchemy.orm import Session

from app.database import get_db
from app.deps import get_current_aluno
from app.models import Aluno, Avatar
from app.schemas.aluno_api import AvatarResponse


router = APIRouter(prefix="", tags=["avatar"])


@router.get("/avatar", response_model=AvatarResponse)
def obter_avatar(
    aluno: Annotated[Aluno, Depends(get_current_aluno)],
    db: Annotated[Session, Depends(get_db)],
):
    avatar = db.scalar(
        select(Avatar)
        .where(or_(Avatar.escola_id == aluno.escola_id, Avatar.escola_id.is_(None)))
        .order_by(case((Avatar.escola_id == aluno.escola_id, 0), else_=1), Avatar.id)
    )
    return {"data": avatar}
