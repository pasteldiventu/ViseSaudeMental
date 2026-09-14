#!/usr/bin/env sh
set -eu
cd /app

echo "=== boot app ==="
echo "PWD=$(pwd)"
echo "PYTHONPATH=${PYTHONPATH:-}"
ls -la /app/app/config.py /app/docker/entrypoint.sh || true

echo "Aguardando MySQL..."
python - <<'PY'
import os
import sys
import time
import traceback

print("python", sys.version, flush=True)

try:
    import pymysql
except Exception:
    traceback.print_exc()
    raise SystemExit("Falha ao importar pymysql")

host = os.environ.get("DB_HOST", "mysql")
port = int(os.environ.get("DB_PORT", "3306"))
user = os.environ.get("DB_USER", "vise")
password = os.environ.get("DB_PASSWORD", "vise")
database = os.environ.get("DB_NAME", "vise")
print(f"conectando {user}@{host}:{port}/{database}", flush=True)

for i in range(60):
    try:
        conn = pymysql.connect(
            host=host,
            port=port,
            user=user,
            password=password,
            database=database,
        )
        conn.close()
        print("MySQL OK", flush=True)
        break
    except Exception as exc:
        print(f"tentativa {i+1}: {exc}", flush=True)
        time.sleep(2)
else:
    raise SystemExit("MySQL indisponível")
PY

echo "Rodando seed..."
python scripts/seed.py

echo "Validando import da aplicação..."
python - <<'PY'
import traceback
try:
    from app.main import app
    print("Import OK:", type(app).__name__, flush=True)
except Exception:
    traceback.print_exc()
    raise SystemExit(1)
PY

echo "Iniciando Uvicorn..."
exec uvicorn app.main:app --host 0.0.0.0 --port 8000
