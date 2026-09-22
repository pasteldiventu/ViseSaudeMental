import secrets
import string
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
    ProfessorTurma,
    Questionario,
    Serie,
    Turma,
    User,
)
from app.security.passwords import hash_password


def _codigo_sala(db: Session) -> str:
    alphabet = string.ascii_uppercase + string.digits
    for _ in range(40):
        codigo = "".join(secrets.choice(alphabet) for _ in range(6))
        if (
            db.scalar(
                select(AplicacaoQuestionario.id).where(
                    AplicacaoQuestionario.codigo_sala == codigo
                )
            )
            is None
        ):
            return codigo
    return secrets.token_hex(3).upper()


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

    def _staff(email: str, name: str, role: str) -> User:
        user = db.scalar(select(User).where(User.email == email))
        if user is None:
            user = User(
                name=name,
                email=email,
                password=hash_password("password"),
                is_superuser=False,
            )
            db.add(user)
            db.flush()
        else:
            user.name = name
            user.password = hash_password("password")
            user.is_superuser = False
        link = db.scalar(
            select(EscolaUser).where(
                EscolaUser.user_id == user.id,
                EscolaUser.escola_id == escola.id,
                EscolaUser.role == role,
            )
        )
        if link is None:
            db.add(
                EscolaUser(
                    user_id=user.id,
                    escola_id=escola.id,
                    role=role,
                    status="ativo",
                )
            )
        return user

    admin_escola = _staff(
        "admin.escola@vise.local",
        "Admin da Escola Demo",
        "admin_escola",
    )
    pesquisador = _staff(
        "pesquisador@vise.local",
        "Pesquisadora Demo",
        "pesquisador",
    )
    professor = _staff("professor@vise.local", "Professor Demo", "professor")
    del admin_escola

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

    vinculo_turma = db.scalar(
        select(ProfessorTurma).where(
            ProfessorTurma.user_id == professor.id,
            ProfessorTurma.turma_id == turma.id,
        )
    )
    if vinculo_turma is None:
        db.add(ProfessorTurma(user_id=professor.id, turma_id=turma.id))

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
            telefone="11999990000",
            responsavel="Responsável Demo",
            contato_responsavel="11988880000",
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
            pesquisador_id=pesquisador.id,
            nome="Bem-estar emocional - Demo",
            descricao="Instrumento demonstrativo do MVP",
            status="publicado",
            publico_alvo="9º ano",
            versao=1,
            compartilhado_na_escola=True,
        )
        db.add(questionario)
        db.flush()
    else:
        questionario.pesquisador_id = pesquisador.id
        questionario.compartilhado_na_escola = True
        questionario.status = "publicado"

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
        aplicacao = AplicacaoQuestionario(
            questionario_id=questionario.id,
            escola_id=escola.id,
            alvo_tipo="escola",
            status="ativa",
            codigo_sala=_codigo_sala(db),
            inicia_em=agora - timedelta(days=1),
            termina_em=agora + timedelta(days=365),
        )
        db.add(aplicacao)
    elif not aplicacao.codigo_sala:
        aplicacao.codigo_sala = _codigo_sala(db)

    questionario_b = db.scalar(
        select(Questionario).where(
            Questionario.escola_id == escola.id,
            Questionario.nome == "Convívio escolar - Demo",
        )
    )
    if questionario_b is None:
        questionario_b = Questionario(
            escola_id=escola.id,
            pesquisador_id=pesquisador.id,
            nome="Convívio escolar - Demo",
            descricao="Segundo instrumento para testar múltiplos questionários",
            status="publicado",
            publico_alvo="9º ano",
            versao=1,
            compartilhado_na_escola=False,
        )
        db.add(questionario_b)
        db.flush()

    categoria_b = db.scalar(
        select(Categoria).where(
            Categoria.questionario_id == questionario_b.id,
            Categoria.nome == "Na escola",
        )
    )
    if categoria_b is None:
        categoria_b = Categoria(
            questionario_id=questionario_b.id,
            escola_id=escola.id,
            nome="Na escola",
            ordem=1,
            cor="#38BDF8",
            mensagem_avatar="Pense no seu dia a dia na escola.",
        )
        db.add(categoria_b)
        db.flush()

    pergunta_b = db.scalar(
        select(Pergunta).where(
            Pergunta.categoria_id == categoria_b.id,
            Pergunta.texto == "Você se sente acolhido(a) na sua turma?",
        )
    )
    if pergunta_b is None:
        pergunta_b = Pergunta(
            categoria_id=categoria_b.id,
            escola_id=escola.id,
            tipo="multipla_escolha",
            texto="Você se sente acolhido(a) na sua turma?",
            ordem=1,
            obrigatoria=True,
            peso=1,
        )
        db.add(pergunta_b)
        db.flush()
        for ordem, (descricao, pontuacao, emoji) in enumerate(
            [("Pouco", 0, "😕"), ("Mais ou menos", 1, "😐"), ("Bastante", 2, "😊")],
            start=1,
        ):
            db.add(
                OpcaoResposta(
                    pergunta_id=pergunta_b.id,
                    escola_id=escola.id,
                    descricao=descricao,
                    pontuacao=pontuacao,
                    ordem=ordem,
                    emoji=emoji,
                )
            )

    aplicacao_b = db.scalar(
        select(AplicacaoQuestionario).where(
            AplicacaoQuestionario.questionario_id == questionario_b.id,
            AplicacaoQuestionario.escola_id == escola.id,
            AplicacaoQuestionario.alvo_tipo == "escola",
        )
    )
    if aplicacao_b is None:
        agora = datetime.utcnow()
        aplicacao_b = AplicacaoQuestionario(
            questionario_id=questionario_b.id,
            escola_id=escola.id,
            alvo_tipo="escola",
            status="ativa",
            codigo_sala=_codigo_sala(db),
            inicia_em=agora - timedelta(days=1),
            termina_em=agora + timedelta(days=365),
        )
        db.add(aplicacao_b)
    elif not aplicacao_b.codigo_sala:
        aplicacao_b.codigo_sala = _codigo_sala(db)
    db.commit()
