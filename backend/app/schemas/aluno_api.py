from datetime import date, datetime
from uuid import UUID

from pydantic import BaseModel, ConfigDict, Field, field_validator


class LoginRequest(BaseModel):
    cpf: str
    data_nascimento: date

    @field_validator("cpf")
    @classmethod
    def normalizar_cpf(cls, value: str) -> str:
        cpf = "".join(character for character in value if character.isdigit())
        if len(cpf) != 11:
            raise ValueError("CPF deve conter 11 dígitos.")
        return cpf


class AlunoMe(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    id: int
    nome: str
    escola_id: int


class LoginResponse(BaseModel):
    token: str
    aluno: AlunoMe


class QuestionarioResumo(BaseModel):
    id: int
    nome: str
    versao: int


class AplicacaoItem(BaseModel):
    id: int
    questionario: QuestionarioResumo
    respondidas: int
    total: int
    concluido: bool


class AplicacoesResponse(BaseModel):
    data: list[AplicacaoItem]


class CategoriaItem(BaseModel):
    id: int
    nome: str
    ordem: int
    cor: str | None = None
    mensagem_avatar: str | None = None
    imagem_apoio: str | None = None


class CategoriasResponse(BaseModel):
    data: list[CategoriaItem]


class OpcaoItem(BaseModel):
    """Representação pública: pontuacao é deliberadamente omitida."""

    id: int
    descricao: str
    ordem: int
    cor: str | None = None
    emoji: str | None = None


class PerguntaItem(BaseModel):
    id: int
    categoria_id: int
    subcategoria_id: int | None = None
    tipo: str
    texto: str
    ordem: int
    obrigatoria: bool
    imagem: str | None = None
    opcoes: list[OpcaoItem] = Field(default_factory=list)


class PerguntasResponse(BaseModel):
    data: list[PerguntaItem]


class RespostaEntrada(BaseModel):
    pergunta_id: int
    opcao_id: int | None = None
    texto: str | None = Field(default=None, max_length=10_000)
    tempo_gasto_ms: int | None = Field(default=None, ge=0)
    dispositivo: str | None = Field(default=None, max_length=255)
    responded_at: datetime | None = None
    client_uuid: UUID | None = None


class RespostasLoteRequest(BaseModel):
    respostas: list[RespostaEntrada] = Field(max_length=500)


class RespostaSalvaResponse(BaseModel):
    status: str = "salvo"
    id: int


class RespostasLoteResponse(BaseModel):
    status: str = "sincronizado"
    quantidade: int


class FinalizarResponse(BaseModel):
    status: str = "concluido"


class TermoRequest(BaseModel):
    versao: str = Field(max_length=50)
    texto_hash: str = Field(min_length=64, max_length=64)


class StatusResponse(BaseModel):
    status: str


class AvatarItem(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    id: int
    nome: str
    imagem_path: str
    mensagem_padrao: str | None = None


class AvatarResponse(BaseModel):
    data: AvatarItem | None
