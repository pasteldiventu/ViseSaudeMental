from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy import func, or_, select
from sqlalchemy.orm import Session, selectinload

from app.database import get_db
from app.deps import get_current_aluno
from app.models import (
    Aluno,
    AplicacaoQuestionario,
    Categoria,
    Pergunta,
    Resposta,
)
from app.schemas.aluno_api import (
    AplicacoesResponse,
    CategoriasResponse,
    PerguntasResponse,
)


router = APIRouter(prefix="", tags=["aplicações"])


def _alvo_do_aluno(aluno: Aluno):
    return or_(
        AplicacaoQuestionario.alvo_tipo == "escola",
        (
            (AplicacaoQuestionario.alvo_tipo == "turma")
            & (AplicacaoQuestionario.turma_id == aluno.turma_id)
        ),
        (
            (AplicacaoQuestionario.alvo_tipo == "aluno")
            & (AplicacaoQuestionario.aluno_id == aluno.id)
        ),
    )


def para_aluno(
    db: Session, aluno: Aluno, aplicacao_id: int
) -> AplicacaoQuestionario:
    aplicacao = db.scalar(
        select(AplicacaoQuestionario)
        .options(selectinload(AplicacaoQuestionario.questionario))
        .where(
            AplicacaoQuestionario.id == aplicacao_id,
            AplicacaoQuestionario.escola_id == aluno.escola_id,
            AplicacaoQuestionario.status == "ativa",
            _alvo_do_aluno(aluno),
        )
    )
    if aplicacao is None:
        raise HTTPException(status_code=404, detail="Aplicação não encontrada.")
    return aplicacao


@router.get("/aplicacoes", response_model=AplicacoesResponse)
def listar_aplicacoes(
    aluno: Annotated[Aluno, Depends(get_current_aluno)],
    db: Annotated[Session, Depends(get_db)],
):
    aplicacoes = db.scalars(
        select(AplicacaoQuestionario)
        .options(selectinload(AplicacaoQuestionario.questionario))
        .where(
            AplicacaoQuestionario.escola_id == aluno.escola_id,
            AplicacaoQuestionario.status == "ativa",
            _alvo_do_aluno(aluno),
        )
    ).all()
    data = []
    for aplicacao in aplicacoes:
        total = db.scalar(
            select(func.count(Pergunta.id))
            .join(Categoria, Pergunta.categoria_id == Categoria.id)
            .where(Categoria.questionario_id == aplicacao.questionario_id)
        ) or 0
        respondidas = db.scalar(
            select(func.count(Resposta.id)).where(
                Resposta.aplicacao_id == aplicacao.id,
                Resposta.aluno_id == aluno.id,
            )
        ) or 0
        data.append(
            {
                "id": aplicacao.id,
                "questionario": {
                    "id": aplicacao.questionario.id,
                    "nome": aplicacao.questionario.nome,
                    "versao": aplicacao.questionario.versao,
                },
                "respondidas": respondidas,
                "total": total,
                "concluido": total > 0 and respondidas >= total,
            }
        )
    return {"data": data}


@router.get(
    "/aplicacoes/{aplicacao_id}/categorias",
    response_model=CategoriasResponse,
)
def listar_categorias(
    aplicacao_id: int,
    aluno: Annotated[Aluno, Depends(get_current_aluno)],
    db: Annotated[Session, Depends(get_db)],
):
    aplicacao = para_aluno(db, aluno, aplicacao_id)
    categorias = db.scalars(
        select(Categoria)
        .where(Categoria.questionario_id == aplicacao.questionario_id)
        .order_by(Categoria.ordem, Categoria.id)
    ).all()
    return {"data": categorias}


@router.get(
    "/aplicacoes/{aplicacao_id}/categorias/{categoria_id}/perguntas",
    response_model=PerguntasResponse,
)
def listar_perguntas(
    aplicacao_id: int,
    categoria_id: int,
    aluno: Annotated[Aluno, Depends(get_current_aluno)],
    db: Annotated[Session, Depends(get_db)],
):
    aplicacao = para_aluno(db, aluno, aplicacao_id)
    categoria = db.scalar(
        select(Categoria).where(
            Categoria.id == categoria_id,
            Categoria.questionario_id == aplicacao.questionario_id,
        )
    )
    if categoria is None:
        raise HTTPException(status_code=404, detail="Categoria não encontrada.")
    perguntas = db.scalars(
        select(Pergunta)
        .options(selectinload(Pergunta.opcoes_resposta))
        .where(Pergunta.categoria_id == categoria.id)
        .order_by(Pergunta.ordem, Pergunta.id)
    ).all()
    return {
        "data": [
            {
                "id": pergunta.id,
                "categoria_id": pergunta.categoria_id,
                "subcategoria_id": pergunta.subcategoria_id,
                "tipo": pergunta.tipo,
                "texto": pergunta.texto,
                "ordem": pergunta.ordem,
                "obrigatoria": pergunta.obrigatoria,
                "imagem": pergunta.imagem,
                "opcoes": [
                    {
                        "id": opcao.id,
                        "descricao": opcao.descricao,
                        "ordem": opcao.ordem,
                        "cor": opcao.cor,
                        "emoji": opcao.emoji,
                    }
                    for opcao in sorted(
                        pergunta.opcoes_resposta,
                        key=lambda item: (item.ordem, item.id),
                    )
                ],
            }
            for pergunta in perguntas
        ]
    }
