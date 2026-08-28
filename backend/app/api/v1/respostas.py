from datetime import datetime, timezone
from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy import select
from sqlalchemy.orm import Session

from app.api.v1.aplicacoes import para_aluno
from app.database import get_db
from app.deps import get_current_aluno
from app.models import Aluno, Categoria, OpcaoResposta, Pergunta, Resposta
from app.schemas.aluno_api import (
    RespostaEntrada,
    RespostaSalvaResponse,
    RespostasLoteRequest,
    RespostasLoteResponse,
)


router = APIRouter(prefix="", tags=["respostas"])


def _data_ingenua(value: datetime | None) -> datetime:
    value = value or datetime.utcnow()
    if value.tzinfo is not None:
        return value.astimezone(timezone.utc).replace(tzinfo=None)
    return value


def _persistir(
    db: Session, aplicacao_id: int, aluno: Aluno, payload: RespostaEntrada
) -> Resposta:
    aplicacao = para_aluno(db, aluno, aplicacao_id)
    pergunta = db.scalar(
        select(Pergunta)
        .join(Categoria, Pergunta.categoria_id == Categoria.id)
        .where(
            Pergunta.id == payload.pergunta_id,
            Categoria.questionario_id == aplicacao.questionario_id,
        )
    )
    if pergunta is None:
        raise HTTPException(status_code=404, detail="Pergunta não encontrada.")
    if payload.opcao_id is not None:
        opcao_valida = db.scalar(
            select(OpcaoResposta.id).where(
                OpcaoResposta.id == payload.opcao_id,
                OpcaoResposta.pergunta_id == pergunta.id,
            )
        )
        if opcao_valida is None:
            raise HTTPException(
                status_code=422, detail="Opção inválida para a pergunta."
            )

    responded_at = _data_ingenua(payload.responded_at)
    resposta = db.scalar(
        select(Resposta).where(
            Resposta.aplicacao_id == aplicacao.id,
            Resposta.aluno_id == aluno.id,
            Resposta.pergunta_id == pergunta.id,
        )
    )
    if resposta and resposta.responded_at > responded_at:
        return resposta
    values = {
        "opcao_id": payload.opcao_id,
        "texto": payload.texto,
        "tempo_gasto_ms": payload.tempo_gasto_ms,
        "dispositivo": payload.dispositivo,
        "responded_at": responded_at,
        "client_uuid": str(payload.client_uuid) if payload.client_uuid else None,
    }
    if resposta is None:
        resposta = Resposta(
            aplicacao_id=aplicacao.id,
            aluno_id=aluno.id,
            pergunta_id=pergunta.id,
            **values,
        )
        db.add(resposta)
    else:
        for field, value in values.items():
            setattr(resposta, field, value)
    db.flush()
    return resposta


@router.post(
    "/aplicacoes/{aplicacao_id}/respostas",
    response_model=RespostaSalvaResponse,
)
def salvar_resposta(
    aplicacao_id: int,
    payload: RespostaEntrada,
    aluno: Annotated[Aluno, Depends(get_current_aluno)],
    db: Annotated[Session, Depends(get_db)],
):
    resposta = _persistir(db, aplicacao_id, aluno, payload)
    db.commit()
    return {"status": "salvo", "id": resposta.id}


@router.post(
    "/aplicacoes/{aplicacao_id}/respostas/lote",
    response_model=RespostasLoteResponse,
)
def salvar_lote(
    aplicacao_id: int,
    payload: RespostasLoteRequest,
    aluno: Annotated[Aluno, Depends(get_current_aluno)],
    db: Annotated[Session, Depends(get_db)],
):
    try:
        for item in payload.respostas:
            _persistir(db, aplicacao_id, aluno, item)
        db.commit()
    except Exception:
        db.rollback()
        raise
    return {"status": "sincronizado", "quantidade": len(payload.respostas)}
