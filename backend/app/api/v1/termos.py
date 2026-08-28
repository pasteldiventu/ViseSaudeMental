from datetime import datetime
from typing import Annotated

from fastapi import APIRouter, Depends, Request, status
from sqlalchemy import select
from sqlalchemy.orm import Session

from app.database import get_db
from app.deps import get_current_aluno
from app.models import Aluno, TermoAceite
from app.schemas.aluno_api import StatusResponse, TermoRequest


router = APIRouter(prefix="", tags=["termos"])


@router.post(
    "/termos", response_model=StatusResponse, status_code=status.HTTP_201_CREATED
)
def aceitar_termo(
    payload: TermoRequest,
    request: Request,
    aluno: Annotated[Aluno, Depends(get_current_aluno)],
    db: Annotated[Session, Depends(get_db)],
):
    termo = db.scalar(
        select(TermoAceite).where(
            TermoAceite.aluno_id == aluno.id,
            TermoAceite.versao == payload.versao,
        )
    )
    values = {
        "texto_hash": payload.texto_hash,
        "ip": request.client.host if request.client else None,
        "user_agent": request.headers.get("User-Agent"),
        "accepted_at": datetime.utcnow(),
    }
    if termo is None:
        termo = TermoAceite(
            aluno_id=aluno.id, versao=payload.versao, **values
        )
        db.add(termo)
    else:
        for field, value in values.items():
            setattr(termo, field, value)
    db.commit()
    return {"status": "aceito"}
