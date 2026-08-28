from fastapi import HTTPException
from sqlalchemy import or_, select
from sqlalchemy.orm import Session

from app.models import (
    Categoria,
    OpcaoResposta,
    Pergunta,
    Questionario,
    RegraClassificacao,
    Subcategoria,
)


class PublicarQuestionarioService:
    @staticmethod
    def publicar(db: Session, questionario: Questionario) -> Questionario:
        if questionario.status != "rascunho":
            raise HTTPException(
                status_code=422,
                detail="Somente questionários em rascunho podem ser publicados.",
            )
        questionario.status = "publicado"
        db.commit()
        db.refresh(questionario)
        return questionario

    @staticmethod
    def criar_nova_versao(
        db: Session, questionario: Questionario
    ) -> Questionario:
        if questionario.status != "publicado":
            raise HTTPException(
                status_code=422,
                detail="A nova versão deve partir de um questionário publicado.",
            )

        novo = Questionario(
            escola_id=questionario.escola_id,
            pesquisador_id=questionario.pesquisador_id,
            nome=questionario.nome,
            descricao=questionario.descricao,
            status="rascunho",
            publico_alvo=questionario.publico_alvo,
            versao=questionario.versao + 1,
            parent_id=questionario.id,
            compartilhado_na_escola=questionario.compartilhado_na_escola,
        )
        db.add(novo)
        db.flush()

        categorias = db.scalars(
            select(Categoria)
            .where(Categoria.questionario_id == questionario.id)
            .order_by(Categoria.ordem)
        ).all()
        categorias_map: dict[int, int] = {}
        subcategorias_map: dict[int, int] = {}

        for categoria in categorias:
            nova_categoria = Categoria(
                questionario_id=novo.id,
                escola_id=categoria.escola_id,
                nome=categoria.nome,
                ordem=categoria.ordem,
                cor=categoria.cor,
                mensagem_avatar=categoria.mensagem_avatar,
                imagem_apoio=categoria.imagem_apoio,
            )
            db.add(nova_categoria)
            db.flush()
            categorias_map[categoria.id] = nova_categoria.id

            for subcategoria in categoria.subcategorias:
                nova_subcategoria = Subcategoria(
                    categoria_id=nova_categoria.id,
                    escola_id=subcategoria.escola_id,
                    nome=subcategoria.nome,
                    ordem=subcategoria.ordem,
                )
                db.add(nova_subcategoria)
                db.flush()
                subcategorias_map[subcategoria.id] = nova_subcategoria.id

            for pergunta in categoria.perguntas:
                nova_pergunta = Pergunta(
                    categoria_id=nova_categoria.id,
                    subcategoria_id=subcategorias_map.get(pergunta.subcategoria_id),
                    escola_id=pergunta.escola_id,
                    tipo=pergunta.tipo,
                    texto=pergunta.texto,
                    ordem=pergunta.ordem,
                    obrigatoria=pergunta.obrigatoria,
                    peso=pergunta.peso,
                    imagem=pergunta.imagem,
                )
                db.add(nova_pergunta)
                db.flush()
                for opcao in pergunta.opcoes:
                    db.add(
                        OpcaoResposta(
                            pergunta_id=nova_pergunta.id,
                            escola_id=opcao.escola_id,
                            descricao=opcao.descricao,
                            pontuacao=opcao.pontuacao,
                            ordem=opcao.ordem,
                            cor=opcao.cor,
                            emoji=opcao.emoji,
                        )
                    )

        regras = db.scalars(
            select(RegraClassificacao).where(
                or_(
                    RegraClassificacao.questionario_id == questionario.id,
                    RegraClassificacao.categoria_id.in_(categorias_map),
                )
            )
        ).all()
        for regra in regras:
            db.add(
                RegraClassificacao(
                    escola_id=regra.escola_id,
                    categoria_id=categorias_map.get(regra.categoria_id),
                    questionario_id=(
                        novo.id
                        if regra.questionario_id == questionario.id
                        else regra.questionario_id
                    ),
                    min_score=regra.min_score,
                    max_score=regra.max_score,
                    rotulo=regra.rotulo,
                    descricao=regra.descricao,
                )
            )

        db.commit()
        db.refresh(novo)
        return novo


def publicar(db: Session, questionario: Questionario) -> Questionario:
    return PublicarQuestionarioService.publicar(db, questionario)


def criar_nova_versao(
    db: Session, questionario: Questionario
) -> Questionario:
    return PublicarQuestionarioService.criar_nova_versao(db, questionario)
