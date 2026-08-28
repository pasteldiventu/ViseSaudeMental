# Vise Saúde Mental

Monorepo: **FastAPI (Python) + SQLAdmin + MySQL** (`backend/`) e **Flutter** local-first (`mobile/`).

O backend Laravel anterior ficou arquivado em `backend-laravel/` (referência).

## Subir o backend (Docker)

```bash
cd /home/jp/ViseProjetos/ViseSaudeMental
cp backend/.env.example backend/.env
docker compose up --build -d
```

O entrypoint aguarda o MySQL, cria tabelas, roda o seed e sobe o Uvicorn na porta **8000**.

| Recurso | URL / dado |
|---|---|
| OpenAPI (Swagger) | http://localhost:8000/docs |
| Health | http://localhost:8000/up |
| SQLAdmin (CMS) | http://localhost:8000/admin |
| API aluno | http://localhost:8000/api/v1 |
| Admin | `admin@vise.local` / `password` |
| Aluno demo | CPF `07593256189` · nascimento `2009-10-21` |

### Comandos úteis

```bash
docker compose exec app pytest -q
docker compose exec app python scripts/legado_import.py
docker compose logs -f app
```

## App Flutter

Sem mudanças de contrato — continua apontando para `/api/v1`.

```bash
cd mobile
flutter pub get
flutter run
```

Fluxo: **login → termo → avatar → categorias → perguntas** (SQLite) → sync em lote → finalizar.

## Domínio

- Tenant = **Escola** (Organização)
- Vínculo ativo em `escola_user`
- Instrumento: questionário → categorias → subcategorias → perguntas → opções
- Pesquisador: próprios + `compartilhado_na_escola`
- Aluno **nunca** recebe pontuação/classificação na API

## CMS

Painel **SQLAdmin** em `/admin` (substitui Filament): escolas, vínculos, turmas, alunos, questionários, categorias, perguntas, opções, aplicações, respostas, resultados, avatares.

## OpenAPI

Nativo do FastAPI em `/docs` e `/redoc`. Espelho resumido em [`docs/openapi.yaml`](docs/openapi.yaml).
