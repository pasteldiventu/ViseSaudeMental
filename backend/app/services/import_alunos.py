import csv
import re
import tempfile
from datetime import datetime
from pathlib import Path

from sqlalchemy import select
from sqlalchemy.orm import Session

from app.models import Aluno, Escola, Turma


COLUNAS = {
    "escola",
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
        db: Session,
        arquivo: str | Path,
        allowed_escola_ids: list[int] | None = None,
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
                    ImportAlunosCsvService._criar(
                        db, row, allowed_escola_ids=allowed_escola_ids
                    )
                    db.commit()
                    sucesso += 1
                except Exception as exc:
                    db.rollback()
                    erros.append(f"Linha {linha}: {exc}")
            return {"success": sucesso, "errors": erros}

    @staticmethod
    def importar_bytes(
        db: Session,
        conteudo: bytes,
        allowed_escola_ids: list[int] | None = None,
    ) -> dict[str, int | list[str]]:
        with tempfile.NamedTemporaryFile(
            suffix=".csv", delete=False
        ) as handle:
            handle.write(conteudo)
            path = handle.name
        try:
            return ImportAlunosCsvService.importar(
                db, path, allowed_escola_ids=allowed_escola_ids
            )
        finally:
            Path(path).unlink(missing_ok=True)

    @staticmethod
    def _resolver_escola(db: Session, valor: str) -> Escola:
        valor = (valor or "").strip()
        if not valor:
            raise ValueError("escola não informada.")
        digits = re.sub(r"\D", "", valor)
        escola = None
        if digits:
            escola = db.scalar(select(Escola).where(Escola.inep == digits))
            if escola is None and digits != valor:
                escola = db.scalar(select(Escola).where(Escola.inep == valor))
        if escola is None:
            escola = db.scalar(select(Escola).where(Escola.nome == valor))
        if escola is None:
            raise ValueError(f"escola '{valor}' não encontrada (use nome ou INEP).")
        return escola

    @staticmethod
    def _criar(
        db: Session,
        row: dict[str, str | None],
        allowed_escola_ids: list[int] | None,
    ) -> None:
        escola = ImportAlunosCsvService._resolver_escola(db, row.get("escola") or "")
        if allowed_escola_ids is not None and escola.id not in allowed_escola_ids:
            raise ValueError(
                f"sem permissão para importar na escola '{escola.nome}'."
            )
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
                Turma.escola_id == escola.id, Turma.nome == turma_nome
            )
        )
        if turma is None:
            raise ValueError(
                f"turma '{turma_nome}' não encontrada nesta escola."
            )
        existente = db.scalar(
            select(Aluno).where(
                Aluno.escola_id == escola.id,
                Aluno.cpf == cpf,
            )
        )
        data = ImportAlunosCsvService._data(row.get("data_nascimento") or "")
        nullable = lambda key: (row.get(key) or "").strip() or None
        if existente is None:
            db.add(
                Aluno(
                    escola_id=escola.id,
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
            return
        existente.turma_id = turma.id
        existente.nome = nome
        existente.data_nascimento = data
        existente.sexo = nullable("sexo") or existente.sexo
        existente.matricula = nullable("matricula") or existente.matricula
        existente.responsavel = nullable("responsavel") or existente.responsavel
        existente.contato_responsavel = (
            nullable("contato_responsavel") or existente.contato_responsavel
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
    db: Session,
    arquivo: str | Path,
    escola_id: int | None = None,
    allowed_escola_ids: list[int] | None = None,
) -> dict[str, int | list[str]]:
    ids = allowed_escola_ids
    if ids is None and escola_id is not None:
        ids = [escola_id]
    return ImportAlunosCsvService.importar(db, arquivo, allowed_escola_ids=ids)
