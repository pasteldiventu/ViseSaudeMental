import pytest
from datetime import date, datetime
from decimal import Decimal

from fastapi.testclient import TestClient
from sqlalchemy import create_engine
from sqlalchemy.orm import Session, sessionmaker
from sqlalchemy.pool import StaticPool

from app.database import Base, get_db
from app.main import create_app
from app.models import (
    Aluno,
    AplicacaoQuestionario,
    Categoria,
    Escola,
    OpcaoResposta,
    Pergunta,
    Questionario,
    RegraClassificacao,
    Resposta,
    Serie,
    Turma,
    User,
)
from app.security.passwords import hash_password
from app.security.tokens import create_aluno_token
from app.services.resultado import calcular_resultado


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


@pytest.fixture()
def client(db_session: Session):
    app = create_app()

    def _override():
        try:
            yield db_session
        finally:
            pass

    app.dependency_overrides[get_db] = _override
    with TestClient(app) as test_client:
        yield test_client
    app.dependency_overrides.clear()


def _seed_minimo(db: Session) -> tuple[Aluno, AplicacaoQuestionario, Pergunta, OpcaoResposta]:
    user = User(
        name="Admin",
        email="admin@test.local",
        password=hash_password("password"),
        is_superuser=True,
    )
    escola = Escola(nome="E", municipio="SP", uf="SP", inep="1", ativo=True)
    serie = Serie(descricao="9º")
    db.add_all([user, escola, serie])
    db.flush()
    turma = Turma(nome="A", serie_id=serie.id, turno="matutino", escola_id=escola.id)
    aluno = Aluno(
        nome="Ana",
        cpf="07593256189",
        data_nascimento=date(2009, 10, 21),
        escola_id=escola.id,
        turma_id=None,
    )
    db.add_all([turma, aluno])
    db.flush()
    aluno.turma_id = turma.id
    q = Questionario(
        escola_id=escola.id,
        pesquisador_id=user.id,
        nome="Q",
        status="publicado",
        versao=1,
        compartilhado_na_escola=True,
    )
    db.add(q)
    db.flush()
    cat = Categoria(
        questionario_id=q.id, escola_id=escola.id, nome="Cat", ordem=1
    )
    db.add(cat)
    db.flush()
    pergunta = Pergunta(
        categoria_id=cat.id,
        escola_id=escola.id,
        tipo="multipla_escolha",
        texto="P?",
        ordem=1,
        obrigatoria=True,
        peso=Decimal("1"),
    )
    db.add(pergunta)
    db.flush()
    opcao = OpcaoResposta(
        pergunta_id=pergunta.id,
        escola_id=escola.id,
        descricao="Sim",
        pontuacao=2,
        ordem=1,
    )
    db.add(opcao)
    db.add(
        RegraClassificacao(
            escola_id=escola.id,
            categoria_id=cat.id,
            min_score=Decimal("0"),
            max_score=Decimal("10"),
            rotulo="verde",
        )
    )
    aplicacao = AplicacaoQuestionario(
        questionario_id=q.id,
        escola_id=escola.id,
        alvo_tipo="escola",
        status="ativa",
    )
    db.add(aplicacao)
    db.commit()
    db.refresh(aluno)
    db.refresh(aplicacao)
    db.refresh(pergunta)
    db.refresh(opcao)
    return aluno, aplicacao, pergunta, opcao


def test_login_aluno(client: TestClient, db_session: Session):
    _seed_minimo(db_session)
    response = client.post(
        "/api/v1/login",
        json={"cpf": "075.932.561-89", "data_nascimento": "2009-10-21"},
    )
    assert response.status_code == 200
    body = response.json()
    assert "token" in body
    assert body["aluno"]["nome"] == "Ana"


def test_login_data_errada(client: TestClient, db_session: Session):
    _seed_minimo(db_session)
    response = client.post(
        "/api/v1/login",
        json={"cpf": "07593256189", "data_nascimento": "2009-10-22"},
    )
    assert response.status_code == 422


def test_perguntas_omitem_pontuacao(client: TestClient, db_session: Session):
    aluno, aplicacao, pergunta, _ = _seed_minimo(db_session)
    token = create_aluno_token(db_session, aluno)
    response = client.get(
        f"/api/v1/aplicacoes/{aplicacao.id}/categorias/{pergunta.categoria_id}/perguntas",
        headers={"Authorization": f"Bearer {token}"},
    )
    assert response.status_code == 200
    payload = response.json()
    data = payload.get("data", payload)
    if isinstance(data, dict):
        data = [data]
    for item in data:
        for opcao in item.get("opcoes", []):
            assert "pontuacao" not in opcao


def test_calcular_resultado(db_session: Session):
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
    resultado = calcular_resultado(db_session, aplicacao, aluno)
    assert str(pergunta.categoria_id) in {str(k) for k in resultado.totais_json.keys()} or pergunta.categoria_id in resultado.totais_json
