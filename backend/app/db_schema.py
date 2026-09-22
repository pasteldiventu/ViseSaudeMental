"""Garante colunas novas em bancos já existentes (sem Alembic ainda)."""

from sqlalchemy import inspect, text
from sqlalchemy.engine import Engine


def ensure_schema(engine: Engine) -> None:
    inspector = inspect(engine)
    if "aplicacoes_questionario" not in inspector.get_table_names():
        return
    colunas = {col["name"] for col in inspector.get_columns("aplicacoes_questionario")}
    if "codigo_sala" in colunas:
        return
    dialect = engine.dialect.name
    with engine.begin() as conn:
        if dialect == "sqlite":
            conn.execute(
                text(
                    "ALTER TABLE aplicacoes_questionario "
                    "ADD COLUMN codigo_sala VARCHAR(12)"
                )
            )
        else:
            conn.execute(
                text(
                    "ALTER TABLE aplicacoes_questionario "
                    "ADD COLUMN codigo_sala VARCHAR(12) NULL UNIQUE"
                )
            )
