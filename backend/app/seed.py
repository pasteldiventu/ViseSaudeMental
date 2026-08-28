from datetime import date, datetime, timedelta

from sqlalchemy import select
from sqlalchemy.orm import Session

from app.models import (
    Aluno,
    AplicacaoQuestionario,
    Categoria,
    Escola,
    EscolaUser,
    OpcaoResposta,
    Pergunta,
    Questionario,
    Serie,
    Turma,
    User,
)
from app.security.passwords import hash_password


def seed_demo(db: Session) -> None:
    admin = db.scalar(select(User).where(User.email == "admin@vise.local"))
    if admin is None:
        admin = User(
            name="Administrador Vise",
            email="admin@vise.local",
            password=hash_password("password"),
            is_superuser=True,
        )
        db.add(admin)
        db.flush()
    else:
        admin.name = "Administrador Vise"
        admin.password = hash_password("password")
        admin.is_superuser = True

    escola = db.scalar(select(Escola).where(Escola.inep == "00000001"))
    if escola is None:
        escola = Escola(
            nome="Escola Demo Vise",
            municipio="São Paulo",
            uf="SP",
            inep="00000001",
            responsavel="Coordenação Demo",
            ativo=True,
        )
        db.add(escola)
        db.flush()

    vinculo = db.scalar(
        select(EscolaUser).where(
            EscolaUser.user_id == admin.id,
            EscolaUser.escola_id == escola.id,
            EscolaUser.role == "admin_escola",
        )
    )
    if vinculo is None:
        db.add(
            EscolaUser(
                user_id=admin.id,
                escola_id=escola.id,
                role="admin_escola",
                status="ativo",
            )
        )

    serie = db.scalar(select(Serie).where(Serie.descricao == "9º Ano"))
    if serie is None:
        serie = Serie(descricao="9º Ano")
        db.add(serie)
        db.flush()

    turma = db.scalar(
        select(Turma).where(
            Turma.escola_id == escola.id, Turma.nome == "9º Ano A"
        )
    )
    if turma is None:
        turma = Turma(
            escola_id=escola.id,
            serie_id=serie.id,
            nome="9º Ano A",
            turno="matutino",
        )
        db.add(turma)
        db.flush()

    aluno = db.scalar(
        select(Aluno).where(
            Aluno.cpf == "07593256189",
            Aluno.data_nascimento == date(2009, 10, 21),
        )
    )
    if aluno is None:
        aluno = Aluno(
            nome="Aluno Demo",
            sexo="outro",
            data_nascimento=date(2009, 10, 21),
            cpf="07593256189",
            matricula="DEMO001",
            turma_id=turma.id,
            escola_id=escola.id,
            responsavel="Responsável Demo",
        )
        db.add(aluno)
        db.flush()

    questionario = db.scalar(
        select(Questionario).where(
            Questionario.escola_id == escola.id,
            Questionario.nome == "Bem-estar emocional - Demo",
        )
    )
    if questionario is None:
        questionario = Questionario(
            escola_id=escola.id,
            pesquisador_id=admin.id,
            nome="Bem-estar emocional - Demo",
            descricao="Instrumento demonstrativo do MVP",
            status="publicado",
            publico_alvo="9º ano",
            versao=1,
            compartilhado_na_escola=True,
        )
        db.add(questionario)
        db.flush()

    categoria = db.scalar(
        select(Categoria).where(
            Categoria.questionario_id == questionario.id,
            Categoria.nome == "Como você tem se sentido?",
        )
    )
    if categoria is None:
        categoria = Categoria(
            questionario_id=questionario.id,
            escola_id=escola.id,
            nome="Como você tem se sentido?",
            ordem=1,
            cor="#14B8A6",
            mensagem_avatar=(
                "Não existem respostas certas ou erradas. "
                "Responda com sinceridade."
            ),
        )
        db.add(categoria)
        db.flush()

    pergunta_mc = db.scalar(
        select(Pergunta).where(
            Pergunta.categoria_id == categoria.id,
            Pergunta.texto
            == "Com que frequência você se sentiu tranquilo nesta semana?",
        )
    )
    if pergunta_mc is None:
        pergunta_mc = Pergunta(
            categoria_id=categoria.id,
            escola_id=escola.id,
            tipo="multipla_escolha",
            texto="Com que frequência você se sentiu tranquilo nesta semana?",
            ordem=1,
            obrigatoria=True,
            peso=1,
        )
        db.add(pergunta_mc)
        db.flush()

    for ordem, (descricao, pontuacao, emoji) in enumerate(
        [("Nunca", 0, "😟"), ("Às vezes", 1, "😐"), ("Frequentemente", 2, "🙂")],
        start=1,
    ):
        opcao = db.scalar(
            select(OpcaoResposta).where(
                OpcaoResposta.pergunta_id == pergunta_mc.id,
                OpcaoResposta.descricao == descricao,
            )
        )
        if opcao is None:
            db.add(
                OpcaoResposta(
                    pergunta_id=pergunta_mc.id,
                    escola_id=escola.id,
                    descricao=descricao,
                    pontuacao=pontuacao,
                    ordem=ordem,
                    emoji=emoji,
                )
            )

    pergunta_texto = db.scalar(
        select(Pergunta).where(
            Pergunta.categoria_id == categoria.id,
            Pergunta.texto == "Conte algo que ajudou você a se sentir bem.",
        )
    )
    if pergunta_texto is None:
        db.add(
            Pergunta(
                categoria_id=categoria.id,
                escola_id=escola.id,
                tipo="texto",
                texto="Conte algo que ajudou você a se sentir bem.",
                ordem=2,
                obrigatoria=False,
                peso=1,
            )
        )

    aplicacao = db.scalar(
        select(AplicacaoQuestionario).where(
            AplicacaoQuestionario.questionario_id == questionario.id,
            AplicacaoQuestionario.escola_id == escola.id,
            AplicacaoQuestionario.alvo_tipo == "escola",
        )
    )
    if aplicacao is None:
        agora = datetime.utcnow()
        db.add(
            AplicacaoQuestionario(
                questionario_id=questionario.id,
                escola_id=escola.id,
                alvo_tipo="escola",
                status="ativa",
                inicia_em=agora - timedelta(days=1),
                termina_em=agora + timedelta(days=365),
            )
        )
    db.commit()
