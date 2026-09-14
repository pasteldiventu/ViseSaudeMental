from datetime import datetime, date
from decimal import Decimal

from sqlalchemy import inspect as sa_inspect
from sqlalchemy.orm import Mapped, mapped_column, relationship
from sqlalchemy import (
    String, Text, Boolean, Integer, ForeignKey, Date, DateTime, Numeric, JSON,
    UniqueConstraint, Index, func,
)
from app.database import Base


def _txt(value, fallback: str = "—") -> str:
    text = str(value).strip() if value is not None else ""
    return text or fallback


def _mascarar_cpf(value: str | None) -> str:
    digits = "".join(character for character in (value or "") if character.isdigit())
    if len(digits) != 11:
        return _txt(value)
    return f"{digits[:3]}.{digits[3:6]}.{digits[6:9]}-{digits[9:]}"


def _rel_attr(obj, relation: str, attr: str, fallback=None):
    """Lê atributo de relação só se já estiver carregada (evita DetachedInstanceError)."""
    try:
        state = sa_inspect(obj)
    except Exception:
        return fallback
    if relation in state.unloaded:
        return fallback
    related = getattr(obj, relation, None)
    if related is None:
        return fallback
    return getattr(related, attr, fallback)


class TimestampMixin:
    created_at: Mapped[datetime] = mapped_column(DateTime, server_default=func.now())
    updated_at: Mapped[datetime] = mapped_column(
        DateTime, server_default=func.now(), onupdate=func.now()
    )


class SoftDeleteMixin:
    deleted_at: Mapped[datetime | None] = mapped_column(DateTime, nullable=True)


class User(TimestampMixin, SoftDeleteMixin, Base):
    __tablename__ = "users"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    name: Mapped[str] = mapped_column(String(255))
    email: Mapped[str] = mapped_column(String(255), unique=True, index=True)
    password: Mapped[str] = mapped_column(String(255))
    is_superuser: Mapped[bool] = mapped_column(
        Boolean, default=False, server_default="0"
    )

    escolas_vinculos: Mapped[list["EscolaUser"]] = relationship(
        back_populates="user", cascade="all, delete-orphan"
    )
    questionarios: Mapped[list["Questionario"]] = relationship(
        back_populates="pesquisador"
    )
    turmas_professor: Mapped[list["ProfessorTurma"]] = relationship(
        back_populates="user", cascade="all, delete-orphan"
    )

    def __str__(self) -> str:
        return f"{_txt(self.name)} ({_txt(self.email)})"


class Escola(TimestampMixin, SoftDeleteMixin, Base):
    __tablename__ = "escolas"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    nome: Mapped[str] = mapped_column(String(255))
    municipio: Mapped[str] = mapped_column(String(255))
    uf: Mapped[str] = mapped_column(String(2))
    inep: Mapped[str | None] = mapped_column(String(20), unique=True, nullable=True)
    responsavel: Mapped[str | None] = mapped_column(String(255), nullable=True)
    telefone: Mapped[str | None] = mapped_column(String(30), nullable=True)
    endereco: Mapped[str | None] = mapped_column(String(500), nullable=True)
    ativo: Mapped[bool] = mapped_column(Boolean, default=True, server_default="1")

    usuarios_vinculos: Mapped[list["EscolaUser"]] = relationship(
        back_populates="escola", cascade="all, delete-orphan"
    )
    turmas: Mapped[list["Turma"]] = relationship(back_populates="escola")
    alunos: Mapped[list["Aluno"]] = relationship(back_populates="escola")
    questionarios: Mapped[list["Questionario"]] = relationship(back_populates="escola")
    categorias: Mapped[list["Categoria"]] = relationship(back_populates="escola")
    subcategorias: Mapped[list["Subcategoria"]] = relationship(back_populates="escola")
    perguntas: Mapped[list["Pergunta"]] = relationship(back_populates="escola")
    opcoes_resposta: Mapped[list["OpcaoResposta"]] = relationship(
        back_populates="escola"
    )
    regras_classificacao: Mapped[list["RegraClassificacao"]] = relationship(
        back_populates="escola"
    )
    aplicacoes: Mapped[list["AplicacaoQuestionario"]] = relationship(
        back_populates="escola"
    )
    avatars: Mapped[list["Avatar"]] = relationship(back_populates="escola")

    def __str__(self) -> str:
        inep = f" · INEP {self.inep}" if self.inep else ""
        return f"{_txt(self.nome)}{inep}"


class EscolaUser(TimestampMixin, Base):
    __tablename__ = "escola_user"
    __table_args__ = (
        UniqueConstraint(
            "user_id", "escola_id", "role", name="uq_escola_user_user_escola_role"
        ),
    )

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    user_id: Mapped[int] = mapped_column(ForeignKey("users.id"), index=True)
    escola_id: Mapped[int] = mapped_column(ForeignKey("escolas.id"), index=True)
    role: Mapped[str] = mapped_column(String(30))
    status: Mapped[str] = mapped_column(
        String(20), default="ativo", server_default="ativo"
    )
    vinculado_em: Mapped[datetime] = mapped_column(DateTime, server_default=func.now())

    user: Mapped["User"] = relationship(back_populates="escolas_vinculos")
    escola: Mapped["Escola"] = relationship(back_populates="usuarios_vinculos")

    def __str__(self) -> str:
        user = _rel_attr(self, "user", "name") or f"user #{self.user_id}"
        escola = _rel_attr(self, "escola", "nome") or f"escola #{self.escola_id}"
        return f"{user} — {escola} ({_txt(self.role)})"


class ProfessorTurma(TimestampMixin, Base):
    """Liga professor a turmas específicas da escola."""

    __tablename__ = "professor_turma"
    __table_args__ = (
        UniqueConstraint(
            "user_id", "turma_id", name="uq_professor_turma_user_turma"
        ),
    )

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    user_id: Mapped[int] = mapped_column(ForeignKey("users.id"), index=True)
    turma_id: Mapped[int] = mapped_column(ForeignKey("turmas.id"), index=True)

    user: Mapped["User"] = relationship(back_populates="turmas_professor")
    turma: Mapped["Turma"] = relationship(back_populates="professores")

    def __str__(self) -> str:
        user = _rel_attr(self, "user", "name") or f"user #{self.user_id}"
        turma = _rel_attr(self, "turma", "nome") or f"turma #{self.turma_id}"
        return f"{user} → {turma}"


class Serie(TimestampMixin, SoftDeleteMixin, Base):
    __tablename__ = "series"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    descricao: Mapped[str] = mapped_column(String(255), unique=True)

    turmas: Mapped[list["Turma"]] = relationship(back_populates="serie")

    def __str__(self) -> str:
        return _txt(self.descricao)


class Turma(TimestampMixin, SoftDeleteMixin, Base):
    __tablename__ = "turmas"
    __table_args__ = (
        UniqueConstraint("escola_id", "nome", name="uq_turmas_escola_nome"),
    )

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    nome: Mapped[str] = mapped_column(String(255))
    serie_id: Mapped[int] = mapped_column(ForeignKey("series.id"), index=True)
    turno: Mapped[str] = mapped_column(String(50))
    escola_id: Mapped[int] = mapped_column(ForeignKey("escolas.id"), index=True)

    serie: Mapped["Serie"] = relationship(back_populates="turmas")
    escola: Mapped["Escola"] = relationship(back_populates="turmas")
    alunos: Mapped[list["Aluno"]] = relationship(back_populates="turma")
    aplicacoes: Mapped[list["AplicacaoQuestionario"]] = relationship(
        back_populates="turma"
    )
    professores: Mapped[list["ProfessorTurma"]] = relationship(
        back_populates="turma", cascade="all, delete-orphan"
    )

    def __str__(self) -> str:
        escola = _rel_attr(self, "escola", "nome")
        prefix = f"{escola} · " if escola else ""
        return f"{prefix}{_txt(self.nome)} ({_txt(self.turno)})"


class Aluno(TimestampMixin, SoftDeleteMixin, Base):
    __tablename__ = "alunos"
    __table_args__ = (
        Index("ix_alunos_cpf_data_nascimento", "cpf", "data_nascimento"),
    )

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    nome: Mapped[str] = mapped_column(String(255))
    sexo: Mapped[str | None] = mapped_column(String(30), nullable=True)
    data_nascimento: Mapped[date] = mapped_column(Date)
    cpf: Mapped[str] = mapped_column(String(11))
    matricula: Mapped[str | None] = mapped_column(String(100), nullable=True)
    turma_id: Mapped[int | None] = mapped_column(
        ForeignKey("turmas.id"), nullable=True, index=True
    )
    escola_id: Mapped[int] = mapped_column(ForeignKey("escolas.id"), index=True)
    telefone: Mapped[str | None] = mapped_column(String(30), nullable=True)
    responsavel: Mapped[str | None] = mapped_column(String(255), nullable=True)
    contato_responsavel: Mapped[str | None] = mapped_column(String(255), nullable=True)

    turma: Mapped["Turma | None"] = relationship(back_populates="alunos")
    escola: Mapped["Escola"] = relationship(back_populates="alunos")
    aplicacoes: Mapped[list["AplicacaoQuestionario"]] = relationship(
        back_populates="aluno"
    )
    respostas: Mapped[list["Resposta"]] = relationship(back_populates="aluno")
    resultados: Mapped[list["Resultado"]] = relationship(back_populates="aluno")
    termos_aceite: Mapped[list["TermoAceite"]] = relationship(
        back_populates="aluno", cascade="all, delete-orphan"
    )

    def __str__(self) -> str:
        return f"{_txt(self.nome)} (CPF {_mascarar_cpf(self.cpf)})"


class Questionario(TimestampMixin, SoftDeleteMixin, Base):
    __tablename__ = "questionarios"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    escola_id: Mapped[int] = mapped_column(ForeignKey("escolas.id"), index=True)
    pesquisador_id: Mapped[int] = mapped_column(ForeignKey("users.id"), index=True)
    nome: Mapped[str] = mapped_column(String(255))
    descricao: Mapped[str | None] = mapped_column(Text, nullable=True)
    status: Mapped[str] = mapped_column(
        String(30), default="rascunho", server_default="rascunho"
    )
    publico_alvo: Mapped[str | None] = mapped_column(String(255), nullable=True)
    versao: Mapped[int] = mapped_column(Integer, default=1, server_default="1")
    parent_id: Mapped[int | None] = mapped_column(
        ForeignKey("questionarios.id"), nullable=True, index=True
    )
    compartilhado_na_escola: Mapped[bool] = mapped_column(
        Boolean, default=False, server_default="0"
    )

    escola: Mapped["Escola"] = relationship(back_populates="questionarios")
    pesquisador: Mapped["User"] = relationship(back_populates="questionarios")
    parent: Mapped["Questionario | None"] = relationship(
        back_populates="versoes", remote_side=[id]
    )
    versoes: Mapped[list["Questionario"]] = relationship(back_populates="parent")
    categorias: Mapped[list["Categoria"]] = relationship(back_populates="questionario")
    regras_classificacao: Mapped[list["RegraClassificacao"]] = relationship(
        back_populates="questionario"
    )
    aplicacoes: Mapped[list["AplicacaoQuestionario"]] = relationship(
        back_populates="questionario"
    )

    def __str__(self) -> str:
        return f"{_txt(self.nome)} (v{self.versao} · {_txt(self.status)})"


class Categoria(TimestampMixin, SoftDeleteMixin, Base):
    __tablename__ = "categorias"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    questionario_id: Mapped[int] = mapped_column(
        ForeignKey("questionarios.id"), index=True
    )
    escola_id: Mapped[int] = mapped_column(ForeignKey("escolas.id"), index=True)
    nome: Mapped[str] = mapped_column(String(255))
    ordem: Mapped[int] = mapped_column(Integer)
    cor: Mapped[str | None] = mapped_column(String(30), nullable=True)
    mensagem_avatar: Mapped[str | None] = mapped_column(Text, nullable=True)
    imagem_apoio: Mapped[str | None] = mapped_column(String(500), nullable=True)

    questionario: Mapped["Questionario"] = relationship(back_populates="categorias")
    escola: Mapped["Escola"] = relationship(back_populates="categorias")
    subcategorias: Mapped[list["Subcategoria"]] = relationship(
        back_populates="categoria"
    )
    perguntas: Mapped[list["Pergunta"]] = relationship(back_populates="categoria")
    regras_classificacao: Mapped[list["RegraClassificacao"]] = relationship(
        back_populates="categoria"
    )

    def __str__(self) -> str:
        q = _rel_attr(self, "questionario", "nome")
        prefix = f"{q} · " if q else ""
        return f"{prefix}{_txt(self.nome)}"


class Subcategoria(TimestampMixin, SoftDeleteMixin, Base):
    __tablename__ = "subcategorias"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    categoria_id: Mapped[int] = mapped_column(ForeignKey("categorias.id"), index=True)
    escola_id: Mapped[int] = mapped_column(ForeignKey("escolas.id"), index=True)
    nome: Mapped[str] = mapped_column(String(255))
    ordem: Mapped[int] = mapped_column(Integer)

    categoria: Mapped["Categoria"] = relationship(back_populates="subcategorias")
    escola: Mapped["Escola"] = relationship(back_populates="subcategorias")
    perguntas: Mapped[list["Pergunta"]] = relationship(back_populates="subcategoria")

    def __str__(self) -> str:
        return _txt(self.nome)


class Pergunta(TimestampMixin, SoftDeleteMixin, Base):
    __tablename__ = "perguntas"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    categoria_id: Mapped[int] = mapped_column(ForeignKey("categorias.id"), index=True)
    subcategoria_id: Mapped[int | None] = mapped_column(
        ForeignKey("subcategorias.id"), nullable=True, index=True
    )
    escola_id: Mapped[int] = mapped_column(ForeignKey("escolas.id"), index=True)
    tipo: Mapped[str] = mapped_column(String(50))
    texto: Mapped[str] = mapped_column(Text)
    ordem: Mapped[int] = mapped_column(Integer)
    obrigatoria: Mapped[bool] = mapped_column(
        Boolean, default=False, server_default="0"
    )
    peso: Mapped[Decimal] = mapped_column(
        Numeric(8, 2), default=Decimal("1.00"), server_default="1.00"
    )
    imagem: Mapped[str | None] = mapped_column(String(500), nullable=True)

    categoria: Mapped["Categoria"] = relationship(back_populates="perguntas")
    subcategoria: Mapped["Subcategoria | None"] = relationship(
        back_populates="perguntas"
    )
    escola: Mapped["Escola"] = relationship(back_populates="perguntas")
    opcoes_resposta: Mapped[list["OpcaoResposta"]] = relationship(
        back_populates="pergunta"
    )
    respostas: Mapped[list["Resposta"]] = relationship(back_populates="pergunta")

    def __str__(self) -> str:
        texto = _txt(self.texto)
        if len(texto) > 80:
            texto = texto[:77] + "..."
        return texto


class OpcaoResposta(TimestampMixin, SoftDeleteMixin, Base):
    __tablename__ = "opcoes_resposta"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    pergunta_id: Mapped[int] = mapped_column(ForeignKey("perguntas.id"), index=True)
    escola_id: Mapped[int] = mapped_column(ForeignKey("escolas.id"), index=True)
    descricao: Mapped[str] = mapped_column(String(500))
    pontuacao: Mapped[int] = mapped_column(Integer)
    ordem: Mapped[int] = mapped_column(Integer)
    cor: Mapped[str | None] = mapped_column(String(30), nullable=True)
    emoji: Mapped[str | None] = mapped_column(String(50), nullable=True)

    pergunta: Mapped["Pergunta"] = relationship(back_populates="opcoes_resposta")
    escola: Mapped["Escola"] = relationship(back_populates="opcoes_resposta")
    respostas: Mapped[list["Resposta"]] = relationship(back_populates="opcao")

    def __str__(self) -> str:
        emoji = f"{self.emoji} " if self.emoji else ""
        return f"{emoji}{_txt(self.descricao)}"


class RegraClassificacao(TimestampMixin, SoftDeleteMixin, Base):
    __tablename__ = "regras_classificacao"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    escola_id: Mapped[int] = mapped_column(ForeignKey("escolas.id"), index=True)
    categoria_id: Mapped[int | None] = mapped_column(
        ForeignKey("categorias.id"), nullable=True, index=True
    )
    questionario_id: Mapped[int | None] = mapped_column(
        ForeignKey("questionarios.id"), nullable=True, index=True
    )
    min_score: Mapped[Decimal] = mapped_column(Numeric(10, 2))
    max_score: Mapped[Decimal] = mapped_column(Numeric(10, 2))
    rotulo: Mapped[str] = mapped_column(String(255))
    descricao: Mapped[str | None] = mapped_column(Text, nullable=True)

    escola: Mapped["Escola"] = relationship(back_populates="regras_classificacao")
    categoria: Mapped["Categoria | None"] = relationship(
        back_populates="regras_classificacao"
    )
    questionario: Mapped["Questionario | None"] = relationship(
        back_populates="regras_classificacao"
    )

    def __str__(self) -> str:
        return f"{_txt(self.rotulo)} ({self.min_score}–{self.max_score})"


class AplicacaoQuestionario(TimestampMixin, SoftDeleteMixin, Base):
    __tablename__ = "aplicacoes_questionario"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    questionario_id: Mapped[int] = mapped_column(
        ForeignKey("questionarios.id"), index=True
    )
    escola_id: Mapped[int] = mapped_column(ForeignKey("escolas.id"), index=True)
    alvo_tipo: Mapped[str] = mapped_column(String(50))
    turma_id: Mapped[int | None] = mapped_column(
        ForeignKey("turmas.id"), nullable=True, index=True
    )
    aluno_id: Mapped[int | None] = mapped_column(
        ForeignKey("alunos.id"), nullable=True, index=True
    )
    inicia_em: Mapped[datetime | None] = mapped_column(DateTime, nullable=True)
    termina_em: Mapped[datetime | None] = mapped_column(DateTime, nullable=True)
    status: Mapped[str] = mapped_column(
        String(30), default="ativa", server_default="ativa"
    )

    questionario: Mapped["Questionario"] = relationship(back_populates="aplicacoes")
    escola: Mapped["Escola"] = relationship(back_populates="aplicacoes")
    turma: Mapped["Turma | None"] = relationship(back_populates="aplicacoes")
    aluno: Mapped["Aluno | None"] = relationship(back_populates="aplicacoes")
    respostas: Mapped[list["Resposta"]] = relationship(back_populates="aplicacao")
    resultados: Mapped[list["Resultado"]] = relationship(back_populates="aplicacao")

    def __str__(self) -> str:
        titulo = (
            _rel_attr(self, "questionario", "nome") or f"Aplicação #{self.id}"
        )
        return f"{titulo} · {_txt(self.alvo_tipo)} · {_txt(self.status)}"


class Resposta(TimestampMixin, Base):
    __tablename__ = "respostas"
    __table_args__ = (
        UniqueConstraint(
            "aplicacao_id", "aluno_id", "pergunta_id",
            name="uq_respostas_aplicacao_aluno_pergunta",
        ),
    )

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    aplicacao_id: Mapped[int] = mapped_column(
        ForeignKey("aplicacoes_questionario.id"), index=True
    )
    aluno_id: Mapped[int] = mapped_column(ForeignKey("alunos.id"), index=True)
    pergunta_id: Mapped[int] = mapped_column(ForeignKey("perguntas.id"), index=True)
    opcao_id: Mapped[int | None] = mapped_column(
        ForeignKey("opcoes_resposta.id"), nullable=True, index=True
    )
    texto: Mapped[str | None] = mapped_column(Text, nullable=True)
    tempo_gasto_ms: Mapped[int | None] = mapped_column(Integer, nullable=True)
    dispositivo: Mapped[str | None] = mapped_column(String(255), nullable=True)
    responded_at: Mapped[datetime] = mapped_column(DateTime, server_default=func.now())
    client_uuid: Mapped[str | None] = mapped_column(String(36), nullable=True)

    aplicacao: Mapped["AplicacaoQuestionario"] = relationship(
        back_populates="respostas"
    )
    aluno: Mapped["Aluno"] = relationship(back_populates="respostas")
    pergunta: Mapped["Pergunta"] = relationship(back_populates="respostas")
    opcao: Mapped["OpcaoResposta | None"] = relationship(back_populates="respostas")

    def __str__(self) -> str:
        aluno = _rel_attr(self, "aluno", "nome") or f"aluno #{self.aluno_id}"
        if self.opcao_id:
            opcao = _rel_attr(self, "opcao", "descricao")
            return f"{aluno} → {_txt(opcao)}"
        texto = _txt(self.texto)
        if len(texto) > 40:
            texto = texto[:37] + "..."
        return f"{aluno} → {texto}"


class Resultado(TimestampMixin, Base):
    __tablename__ = "resultados"
    __table_args__ = (
        UniqueConstraint(
            "aplicacao_id", "aluno_id", name="uq_resultados_aplicacao_aluno"
        ),
    )

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    aplicacao_id: Mapped[int] = mapped_column(
        ForeignKey("aplicacoes_questionario.id"), index=True
    )
    aluno_id: Mapped[int] = mapped_column(ForeignKey("alunos.id"), index=True)
    totais_json: Mapped[dict] = mapped_column(JSON)
    classificacao_json: Mapped[dict] = mapped_column(JSON)

    aplicacao: Mapped["AplicacaoQuestionario"] = relationship(
        back_populates="resultados"
    )
    aluno: Mapped["Aluno"] = relationship(back_populates="resultados")

    def __str__(self) -> str:
        aluno = _rel_attr(self, "aluno", "nome") or f"aluno #{self.aluno_id}"
        return f"Resultado de {aluno}"


class Avatar(TimestampMixin, Base):
    __tablename__ = "avatars"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    escola_id: Mapped[int | None] = mapped_column(
        ForeignKey("escolas.id"), nullable=True, index=True
    )
    nome: Mapped[str] = mapped_column(String(255))
    imagem_path: Mapped[str] = mapped_column(String(500))
    mensagem_padrao: Mapped[str | None] = mapped_column(Text, nullable=True)

    escola: Mapped["Escola | None"] = relationship(back_populates="avatars")

    def __str__(self) -> str:
        return _txt(self.nome)


class TermoAceite(Base):
    __tablename__ = "termos_aceite"
    __table_args__ = (
        UniqueConstraint("aluno_id", "versao", name="uq_termos_aceite_aluno_versao"),
    )

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    aluno_id: Mapped[int] = mapped_column(ForeignKey("alunos.id"), index=True)
    versao: Mapped[int] = mapped_column(Integer)
    texto_hash: Mapped[str] = mapped_column(String(64))
    ip: Mapped[str | None] = mapped_column(String(45), nullable=True)
    user_agent: Mapped[str | None] = mapped_column(Text, nullable=True)
    accepted_at: Mapped[datetime] = mapped_column(DateTime, server_default=func.now())

    aluno: Mapped["Aluno"] = relationship(back_populates="termos_aceite")

    def __str__(self) -> str:
        aluno = _rel_attr(self, "aluno", "nome") or f"aluno #{self.aluno_id}"
        return f"{aluno} · termo v{self.versao}"


class PersonalAccessToken(TimestampMixin, Base):
    __tablename__ = "personal_access_tokens"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    tokenable_type: Mapped[str] = mapped_column(String(255))
    tokenable_id: Mapped[int] = mapped_column(Integer)
    name: Mapped[str] = mapped_column(String(255))
    token: Mapped[str] = mapped_column(String(64), unique=True)
    abilities: Mapped[str | None] = mapped_column(Text, nullable=True)
    last_used_at: Mapped[datetime | None] = mapped_column(DateTime, nullable=True)
    expires_at: Mapped[datetime | None] = mapped_column(DateTime, nullable=True)

    __table_args__ = (
        Index(
            "ix_personal_access_tokens_tokenable",
            "tokenable_type",
            "tokenable_id",
        ),
    )
