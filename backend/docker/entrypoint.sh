#!/usr/bin/env sh
set -e
cd /app

echo "Aguardando MySQL..."
python - <<'PY'
import time
import pymysql
from app.config import settings

for i in range(60):
    try:
        conn = pymysql.connect(
            host=settings.db_host,
            port=settings.db_port,
            user=settings.db_user,
            password=settings.db_password,
            database=settings.db_name,
        )
        conn.close()
        print("MySQL OK")
        break
    except Exception as exc:
        print(f"tentativa {i+1}: {exc}")
        time.sleep(2)
else:
    raise SystemExit("MySQL indisponível")
PY

python scripts/seed.py
exec uvicorn app.main:app --host 0.0.0.0 --port 8000
