from pathlib import Path

from app.admin.setup import AlunoAdmin, PerguntaAdmin
from app.models.entities import Aluno, Categoria, Escola, Pergunta, _mascarar_cpf


def test_mascara_cpf():
    assert _mascarar_cpf("07593256189") == "075.932.561-89"
    assert _mascarar_cpf("075.932.561-89") == "075.932.561-89"


def test_str_mostra_texto_e_nao_endereco_de_memoria():
    pergunta = Pergunta(
        texto="Conte algo que ajudou você a se sentir bem.",
        tipo="texto",
        ordem=1,
    )
    categoria = Categoria(nome="Bem-estar", ordem=1)
    escola = Escola(nome="Escola Demo Vise", municipio="São Paulo", uf="SP")
    aluno = Aluno(nome="Maria Silva", cpf="07593256189")

    for obj in (pergunta, categoria, escola, aluno):
        for texto in (str(obj), repr(obj)):
            assert "object at" not in texto
            assert "0x" not in texto

    assert "sentir bem" in str(pergunta)
    assert "Bem-estar" in str(categoria)
    assert "Escola Demo Vise" in str(escola)
    assert "075.932.561-89" in str(aluno)


def test_pergunta_admin_lista_texto_e_tipo():
    chaves = [getattr(coluna, "key", None) for coluna in PerguntaAdmin.column_list]
    assert "texto" in chaves
    assert "tipo" in chaves
    assert "categoria" in chaves
    assert "id" not in chaves


def test_aluno_admin_lista_nome_e_mascara_cpf():
    chaves = [getattr(coluna, "key", None) for coluna in AlunoAdmin.column_list]
    assert "nome" in chaves
    assert "cpf" in chaves
    assert Aluno.cpf in AlunoAdmin.column_formatters


def test_turma_str_nao_lazy_load_quando_detached():
    from sqlalchemy.orm import Session, sessionmaker
    from sqlalchemy import create_engine
    from sqlalchemy.pool import StaticPool
    from app.database import Base
    from app.models.entities import Escola, Serie, Turma

    engine = create_engine(
        "sqlite+pysqlite:///:memory:",
        connect_args={"check_same_thread": False},
        poolclass=StaticPool,
    )
    Base.metadata.create_all(bind=engine)
    SessionLocal = sessionmaker(bind=engine)
    db: Session = SessionLocal()
    escola = Escola(nome="E1", municipio="X", uf="SP")
    serie = Serie(descricao="9º")
    db.add_all([escola, serie])
    db.flush()
    turma = Turma(nome="9A", serie_id=serie.id, turno="matutino", escola_id=escola.id)
    db.add(turma)
    db.commit()
    db.refresh(turma)
    db.expunge(turma)
    texto = str(turma)
    assert "object at" not in texto
    assert "9A" in texto
    db.close()


def test_layout_admin_mostra_marca_e_usuario():
    layout = Path(__file__).resolve().parents[1] / "app/admin/templates/sqladmin/layout.html"
    texto = layout.read_text(encoding="utf-8")
    assert "VISE-MT" in texto
    assert "admin_user_name" in texto
    assert "admin_user_email" in texto
    assert "logo.jpeg" not in texto
