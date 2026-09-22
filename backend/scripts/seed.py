#!/usr/bin/env python3
import sys
from pathlib import Path


BACKEND_DIR = Path(__file__).resolve().parents[1]
if str(BACKEND_DIR) not in sys.path:
    sys.path.insert(0, str(BACKEND_DIR))

from app.database import Base, SessionLocal, engine  # noqa: E402
from app.db_schema import ensure_schema  # noqa: E402
from app.models import *  # noqa: E402,F403
from app.seed import seed_demo  # noqa: E402


def main() -> None:
    Base.metadata.create_all(bind=engine)
    ensure_schema(engine)
    with SessionLocal() as db:
        seed_demo(db)
    print("Dados de demonstração criados com sucesso.")


if __name__ == "__main__":
    main()
