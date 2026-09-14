from datetime import datetime

import pytest
from sqlalchemy import create_engine, select
from sqlalchemy.orm import sessionmaker
from sqlalchemy.pool import StaticPool

from app.database import Base
from app.models import Aluno, Categoria, OpcaoResposta, Pergunta, Questionario, Resposta, Resultado
from app.services.import_alunos import ImportAlunosCsvService
from app.services.limpar_aplicacao import LimparAplicacaoService
from app.services.publicar import PublicarQuestionarioService
from app.services.resultado import calcular_resultado
from tests.test_api import _seed_minimo


@pytest.fixture()
def db_session():
    engine = create_engine(
        "sqlite+pysqlite:///:memory:",
        connect_args={"check_same_thread": False},
        poolclass=StaticPool,
    )
    Base.metadata.create_all(bind=engine)
    TestingSession = sessionmaker(bind=engine, autocommit=False, autoflush=False)
    session = TestingSession()
    try:
        yield session
    finally:
        session.close()


def test_publicar_so_rascunho(db_session):
    _, aplicacao, _, _ = _seed_minimo(db_session)
    q = db_session.get(Questionario, aplicacao.questionario_id)
    q.status = "rascunho"
    db_session.commit()
    publicado = PublicarQuestionarioService.publicar(db_session, q)
    assert publicado.status == "publicado"


def test_nova_versao_copia_opcoes(db_session):
    _, aplicacao, _, _ = _seed_minimo(db_session)
    q = db_session.get(Questionario, aplicacao.questionario_id)
    novo = PublicarQuestionarioService.criar_nova_versao(db_session, q)
    assert novo.versao == q.versao + 1
    assert novo.status == "rascunho"
    assert novo.parent_id == q.id
    cats = db_session.scalars(
        select(Categoria).where(Categoria.questionario_id == novo.id)
    ).all()
    assert len(cats) == 1
    novas_perguntas = db_session.scalars(
        select(Pergunta).where(Pergunta.categoria_id == cats[0].id)
    ).all()
    assert len(novas_perguntas) == 1
    opcoes = db_session.scalars(
        select(OpcaoResposta).where(
            OpcaoResposta.pergunta_id == novas_perguntas[0].id
        )
    ).all()
    assert len(opcoes) == 1


def test_import_csv_com_coluna_escola(db_session):
    _seed_minimo(db_session)
    csv = (
        "escola,nome,cpf,data_nascimento,sexo,matricula,turma_nome,"
        "responsavel,contato_responsavel\n"
        "1,Bruno,52998224725,2010-05-01,outro,M2,A,Resp,119\n"
    )
    resultado = ImportAlunosCsvService.importar_bytes(
        db_session, csv.encode("utf-8")
    )
    assert resultado["success"] == 1
    assert resultado["errors"] == []
    aluno = db_session.scalar(select(Aluno).where(Aluno.cpf == "52998224725"))
    assert aluno is not None
    assert aluno.nome == "Bruno"


def test_import_csv_escola_fora_do_escopo(db_session):
    _seed_minimo(db_session)
    csv = (
        "escola,nome,cpf,data_nascimento,sexo,matricula,turma_nome,"
        "responsavel,contato_responsavel\n"
        "1,Bruno,52998224725,2010-05-01,outro,M2,A,Resp,119\n"
    )
    resultado = ImportAlunosCsvService.importar_bytes(
        db_session, csv.encode("utf-8"), allowed_escola_ids=[999]
    )
    assert resultado["success"] == 0
    assert resultado["errors"]


def test_limpar_aplicacao_remove_respostas(db_session):
    aluno, aplicacao, pergunta, opcao = _seed_minimo(db_session)
    db_session.add(
        Resposta(
            aplicacao_id=aplicacao.id,
            aluno_id=aluno.id,
            pergunta_id=pergunta.id,
            opcao_id=opcao.id,
            responded_at=datetime.utcnow(),
        )
    )
    db_session.commit()
    calcular_resultado(db_session, aplicacao, aluno)
    LimparAplicacaoService.limpar(db_session, aplicacao.id)
    assert (
        db_session.scalar(
            select(Resposta).where(Resposta.aplicacao_id == aplicacao.id)
        )
        is None
    )
    assert (
        db_session.scalar(
            select(Resultado).where(Resultado.aplicacao_id == aplicacao.id)
        )
        is None
    )
