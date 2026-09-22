from sqlalchemy import select

from app.models import User
from tests.test_api import _seed_minimo


def test_staff_login_e_cria_sala(client, db_session):
    aluno, _, _, _ = _seed_minimo(db_session)
    user = db_session.scalar(select(User).where(User.email == "admin@test.local"))
    assert user is not None

    login = client.post(
        "/api/v1/staff/login",
        json={"email": "admin@test.local", "password": "password"},
    )
    assert login.status_code == 200, login.text
    token = login.json()["token"]
    headers = {"Authorization": f"Bearer {token}"}

    qs = client.get("/api/v1/staff/questionarios", headers=headers)
    assert qs.status_code == 200
    assert len(qs.json()) >= 1
    qid = qs.json()[0]["id"]

    turmas = client.get("/api/v1/staff/turmas", headers=headers)
    assert turmas.status_code == 200
    assert len(turmas.json()) >= 1
    tid = turmas.json()[0]["id"]

    sala = client.post(
        "/api/v1/staff/salas",
        headers=headers,
        json={
            "questionario_id": qid,
            "escola_id": aluno.escola_id,
            "alvo_tipo": "turma",
            "turma_id": tid,
        },
    )
    assert sala.status_code == 201, sala.text
    body = sala.json()
    assert body["codigo"]
    assert "sala=" in body["link"]
    codigo = body["codigo"]

    publica = client.get(f"/api/v1/salas/{codigo}")
    assert publica.status_code == 200
    assert publica.json()["codigo"] == codigo

    from app.security.tokens import create_aluno_token

    aluno_token = create_aluno_token(db_session, aluno)
    entrar = client.post(
        "/api/v1/aplicacoes/entrar-com-codigo",
        headers={"Authorization": f"Bearer {aluno_token}"},
        json={"codigo": codigo},
    )
    assert entrar.status_code == 200, entrar.text
    assert entrar.json()["aplicacao_id"] == body["id"]


def test_staff_login_invalido(client, db_session):
    _seed_minimo(db_session)
    response = client.post(
        "/api/v1/staff/login",
        json={"email": "admin@test.local", "password": "errada"},
    )
    assert response.status_code == 422
