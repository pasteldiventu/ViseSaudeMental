from decimal import Decimal

from sqlalchemy import select
from sqlalchemy.orm import Session, selectinload

from app.models import (
    Aluno,
    AplicacaoQuestionario,
    RegraClassificacao,
    Resposta,
    Resultado,
)


class CalcularResultadoService:
    @staticmethod
    def calcular(
        db: Session, aplicacao: AplicacaoQuestionario, aluno: Aluno
    ) -> Resultado:
        respostas = db.scalars(
            select(Resposta)
            .options(
                selectinload(Resposta.pergunta),
                selectinload(Resposta.opcao),
            )
            .where(
                Resposta.aplicacao_id == aplicacao.id,
                Resposta.aluno_id == aluno.id,
            )
        ).all()
        totais: dict[str, float] = {}
        for resposta in respostas:
            categoria_id = str(resposta.pergunta.categoria_id)
            pontos = resposta.opcao.pontuacao if resposta.opcao else 0
            valor = Decimal(pontos) * Decimal(resposta.pergunta.peso or 1)
            totais[categoria_id] = totais.get(categoria_id, 0.0) + float(valor)

        classificacoes: dict[str, dict[str, str | None] | None] = {}
        for categoria_id, total in totais.items():
            regra = db.scalar(
                select(RegraClassificacao).where(
                    RegraClassificacao.categoria_id == int(categoria_id),
                    RegraClassificacao.min_score <= total,
                    RegraClassificacao.max_score >= total,
                )
            )
            classificacoes[categoria_id] = (
                {"rotulo": regra.rotulo, "descricao": regra.descricao}
                if regra
                else None
            )

        resultado = db.scalar(
            select(Resultado).where(
                Resultado.aplicacao_id == aplicacao.id,
                Resultado.aluno_id == aluno.id,
            )
        )
        if resultado is None:
            resultado = Resultado(
                aplicacao_id=aplicacao.id,
                aluno_id=aluno.id,
                totais_json=totais,
                classificacao_json=classificacoes,
            )
            db.add(resultado)
        else:
            resultado.totais_json = totais
            resultado.classificacao_json = classificacoes
        db.commit()
        db.refresh(resultado)
        return resultado


def calcular_resultado(
    db: Session, aplicacao: AplicacaoQuestionario, aluno: Aluno
) -> Resultado:
    return CalcularResultadoService.calcular(db, aplicacao, aluno)
