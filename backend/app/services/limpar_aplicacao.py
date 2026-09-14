from sqlalchemy import select
from sqlalchemy.orm import Session, selectinload

from app.models import Categoria, Pergunta, Questionario, Resposta, Resultado


class LimparAplicacaoService:
    @staticmethod
    def limpar(db: Session, aplicacao_id: int) -> dict[str, int]:
        respostas = db.scalars(
            select(Resposta).where(Resposta.aplicacao_id == aplicacao_id)
        ).all()
        resultados = db.scalars(
            select(Resultado).where(Resultado.aplicacao_id == aplicacao_id)
        ).all()
        n_respostas = len(respostas)
        n_resultados = len(resultados)
        for item in respostas:
            db.delete(item)
        for item in resultados:
            db.delete(item)
        db.commit()
        return {"respostas": n_respostas, "resultados": n_resultados}


def questionario_com_filhos(db: Session, questionario_id: int) -> Questionario | None:
    return db.scalar(
        select(Questionario)
        .options(
            selectinload(Questionario.categorias)
            .selectinload(Categoria.subcategorias),
            selectinload(Questionario.categorias)
            .selectinload(Categoria.perguntas)
            .selectinload(Pergunta.opcoes_resposta),
        )
        .where(Questionario.id == questionario_id)
    )
