"""Filtros de listagem do SQLAdmin por papel."""

from __future__ import annotations

from sqlalchemy import Select, false, or_, select

from app.admin.roles import (
    escola_ids,
    is_pesquisador_only,
    is_professor_only,
    is_superuser,
    turma_ids,
)
from app.models import (
    Aluno,
    AplicacaoQuestionario,
    Categoria,
    OpcaoResposta,
    Pergunta,
    Questionario,
    RegraClassificacao,
    Subcategoria,
    Turma,
)


def questionarios_visiveis(request, *, so_proprios: bool = False):
    user_id = request.session.get("admin_user_id")
    cond = Questionario.pesquisador_id == user_id
    if not so_proprios:
        cond = or_(cond, Questionario.compartilhado_na_escola.is_(True))
    ids = escola_ids(request)
    stmt = select(Questionario.id).where(cond)
    if ids:
        stmt = stmt.where(Questionario.escola_id.in_(ids))
    return stmt


def filtrar_instrumento(query: Select, request, model, *, so_proprios: bool = False):
    if is_superuser(request) or not is_pesquisador_only(request):
        return query
    visiveis = questionarios_visiveis(request, so_proprios=so_proprios)
    categorias = select(Categoria.id).where(Categoria.questionario_id.in_(visiveis))
    perguntas = select(Pergunta.id).where(Pergunta.categoria_id.in_(categorias))
    if model is Questionario:
        return query.where(Questionario.id.in_(visiveis))
    if model is Categoria:
        return query.where(Categoria.questionario_id.in_(visiveis))
    if model is Subcategoria:
        return query.where(Subcategoria.categoria_id.in_(categorias))
    if model is Pergunta:
        return query.where(Pergunta.categoria_id.in_(categorias))
    if model is OpcaoResposta:
        return query.where(OpcaoResposta.pergunta_id.in_(perguntas))
    if model is RegraClassificacao:
        return query.where(
            or_(
                RegraClassificacao.questionario_id.in_(visiveis),
                RegraClassificacao.categoria_id.in_(categorias),
            )
        )
    return query


def filtrar_professor(query: Select, request, model):
    if is_superuser(request) or not is_professor_only(request):
        return query
    turmas = turma_ids(request)
    if not turmas:
        return query.where(false())
    if model is Aluno:
        return query.where(Aluno.turma_id.in_(turmas))
    if model is Turma:
        return query.where(Turma.id.in_(turmas))
    if model is AplicacaoQuestionario:
        return query.where(
            or_(
                AplicacaoQuestionario.alvo_tipo == "escola",
                (
                    (AplicacaoQuestionario.alvo_tipo == "turma")
                    & AplicacaoQuestionario.turma_id.in_(turmas)
                ),
                (
                    (AplicacaoQuestionario.alvo_tipo == "aluno")
                    & AplicacaoQuestionario.aluno_id.in_(
                        select(Aluno.id).where(Aluno.turma_id.in_(turmas))
                    )
                ),
            )
        )
    return query


def filtrar_via_aplicacao(query: Select, request):
    query = query.join(AplicacaoQuestionario)
    if is_superuser(request):
        return query
    query = query.where(AplicacaoQuestionario.escola_id.in_(escola_ids(request)))
    return filtrar_professor(query, request, AplicacaoQuestionario)
