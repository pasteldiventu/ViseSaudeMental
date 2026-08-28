from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy import select
from sqlalchemy.orm import Session

from app.api.v1.aplicacoes import para_aluno
from app.database import get_db
from app.deps import get_current_aluno
from app.models import Aluno, Categoria, Pergunta, Resposta
from app.schemas.aluno_api import FinalizarResponse
from app.services.resultado import CalcularResultadoService


router = APIRouter(prefix="", tags=["finalização"])


@router.post(
    "/aplicacoes/{aplicacao_id}/finalizar",
    response_model=FinalizarResponse,
)
def finalizar(
    aplicacao_id: int,
    aluno: Annotated[Aluno, Depends(get_current_aluno)],
    db: Annotated[Session, Depends(get_db)],
):
    aplicacao = para_aluno(db, aluno, aplicacao_id)
    obrigatorias = set(
        db.scalars(
            select(Pergunta.id)
            .join(Categoria, Pergunta.categoria_id == Categoria.id)
            .where(
                Categoria.questionario_id == aplicacao.questionario_id,
                Pergunta.obrigatoria.is_(True),
            )
        ).all()
    )
    respondidas = set(
        db.scalars(
            select(Resposta.pergunta_id).where(
                Resposta.aplicacao_id == aplicacao.id,
                Resposta.aluno_id == aluno.id,
                Resposta.pergunta_id.in_(obrigatorias),
            )
        ).all()
    )
    if obrigatorias - respondidas:
        raise HTTPException(
            status_code=422,
            detail="Ainda existem perguntas obrigatórias sem resposta.",
        )
    CalcularResultadoService.calcular(db, aplicacao, aluno)
    return {"status": "concluido"}
