import csv
import re
from datetime import datetime
from pathlib import Path

from sqlalchemy import select
from sqlalchemy.orm import Session

from app.models import Aluno, Turma


COLUNAS = {
    "nome",
    "cpf",
    "data_nascimento",
    "sexo",
    "matricula",
    "turma_nome",
    "responsavel",
    "contato_responsavel",
}


class ImportAlunosCsvService:
    @staticmethod
    def importar(
        db: Session, arquivo: str | Path, escola_id: int
    ) -> dict[str, int | list[str]]:
        path = Path(arquivo)
        if not path.is_file():
            raise RuntimeError("Não foi possível abrir o arquivo CSV.")

        with path.open("r", encoding="utf-8-sig", newline="") as handle:
            amostra = handle.read(4096)
            handle.seek(0)
            delimitador = ";" if amostra.count(";") > amostra.count(",") else ","
            leitor = csv.DictReader(handle, delimiter=delimitador)
            if leitor.fieldnames is None:
                raise RuntimeError("O arquivo CSV está vazio.")
            nomes = [nome.strip().lower() for nome in leitor.fieldnames]
            faltantes = COLUNAS.difference(nomes)
            if faltantes:
                raise RuntimeError(
                    "Colunas obrigatórias ausentes: " + ", ".join(sorted(faltantes))
                )
            leitor.fieldnames = nomes
            sucesso = 0
            erros: list[str] = []
            for linha, row in enumerate(leitor, start=2):
                if not any((value or "").strip() for value in row.values()):
                    continue
                try:
                    ImportAlunosCsvService._criar(db, row, escola_id)
                    db.commit()
                    sucesso += 1
                except Exception as exc:
                    db.rollback()
                    erros.append(f"Linha {linha}: {exc}")
            return {"success": sucesso, "errors": erros}

    @staticmethod
    def _criar(db: Session, row: dict[str, str | None], escola_id: int) -> None:
        nome = (row.get("nome") or "").strip()
        cpf = re.sub(r"\D", "", row.get("cpf") or "")
        turma_nome = (row.get("turma_nome") or "").strip()
        if not nome:
            raise ValueError("nome não informado.")
        if len(cpf) != 11:
            raise ValueError("CPF deve conter 11 dígitos.")
        if not turma_nome:
            raise ValueError("turma_nome não informado.")
        turma = db.scalar(
            select(Turma).where(
                Turma.escola_id == escola_id, Turma.nome == turma_nome
            )
        )
        if turma is None:
            raise ValueError(f"turma '{turma_nome}' não encontrada nesta escola.")
        data = ImportAlunosCsvService._data(row.get("data_nascimento") or "")
        nullable = lambda key: (row.get(key) or "").strip() or None
        db.add(
            Aluno(
                escola_id=escola_id,
                turma_id=turma.id,
                nome=nome,
                cpf=cpf,
                data_nascimento=data,
                sexo=nullable("sexo"),
                matricula=nullable("matricula"),
                responsavel=nullable("responsavel"),
                contato_responsavel=nullable("contato_responsavel"),
            )
        )

    @staticmethod
    def _data(value: str):
        value = value.strip()
        for formato in ("%Y-%m-%d", "%d/%m/%Y"):
            try:
                return datetime.strptime(value, formato).date()
            except ValueError:
                pass
        raise ValueError(
            "data_nascimento inválida; use AAAA-MM-DD ou DD/MM/AAAA."
        )


def importar_csv(
    db: Session, arquivo: str | Path, escola_id: int
) -> dict[str, int | list[str]]:
    return ImportAlunosCsvService.importar(db, arquivo, escola_id)
