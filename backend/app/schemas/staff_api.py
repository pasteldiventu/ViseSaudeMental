from datetime import datetime
from typing import Literal

from pydantic import BaseModel, ConfigDict, Field


class StaffLoginRequest(BaseModel):
    email: str = Field(min_length=3, max_length=255)
    password: str = Field(min_length=1, max_length=255)


class StaffEscolaResumo(BaseModel):
    id: int
    nome: str
    role: str
    role_label: str


class StaffMe(BaseModel):
    id: int
    name: str
    email: str
    is_superuser: bool
    escolas: list[StaffEscolaResumo]


class StaffLoginResponse(BaseModel):
    token: str
    user: StaffMe


class QuestionarioStaffItem(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    id: int
    nome: str
    versao: int
    status: str
    escola_id: int


class TurmaStaffItem(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    id: int
    nome: str
    turno: str
    escola_id: int
    serie: str | None = None


class CriarSalaRequest(BaseModel):
    questionario_id: int
    escola_id: int
    alvo_tipo: Literal["escola", "turma"] = "turma"
    turma_id: int | None = None


class SalaItem(BaseModel):
    id: int
    codigo: str
    link: str
    status: str
    alvo_tipo: str
    questionario_id: int
    questionario_nome: str
    escola_id: int
    escola_nome: str
    turma_id: int | None = None
    turma_nome: str | None = None
    inicia_em: datetime | None = None
    termina_em: datetime | None = None
    respondentes: int = 0
    concluidos: int = 0


class SalasResponse(BaseModel):
    data: list[SalaItem]


class SalaPublica(BaseModel):
    codigo: str
    questionario_nome: str
    escola_nome: str
    alvo_tipo: str
    status: str


class EntrarSalaRequest(BaseModel):
    codigo: str = Field(min_length=4, max_length=12)


class EntrarSalaResponse(BaseModel):
    aplicacao_id: int
    questionario_nome: str
    codigo: str
