# Vise Saúde Mental

Monorepo: **FastAPI (Python) + SQLAdmin + MySQL** (`backend/`) e **Flutter** local-first (`mobile/`) — Android + **Web**.

## Subir o backend (Docker)

```bash
cd ViseSaudeMental
cp backend/.env.example backend/.env
docker compose up --build -d
```

O entrypoint aguarda o MySQL, cria tabelas, roda o seed e sobe o Uvicorn na porta **8000**.

> No Windows, se a porta `3306` estiver ocupada por um MySQL local, o Compose publica o MySQL do Docker em **`3307`**.

| Recurso | URL / dado |
|---|---|
| OpenAPI (Swagger) | http://localhost:8000/docs |
| Health | http://localhost:8000/up |
| SQLAdmin (CMS) | http://localhost:8000/admin |
| API aluno | http://localhost:8000/api/v1 |
| Admin geral | `admin@vise.local` / `password` (acesso total) |
| Admin escola | `admin.escola@vise.local` / `password` |
| Pesquisador | `pesquisador@vise.local` / `password` |
| Professor | `professor@vise.local` / `password` |
| Aluno demo | CPF `07593256189` · nascimento `2009-10-21` |

### App Flutter — aluno e equipe

O mesmo app serve **aluno** e **equipe** (admin / pesquisador / professor):

1. Na abertura: escolha **Sou aluno** ou **Sou da equipe**
2. Equipe: login e-mail/senha → **Nova sala** → gera **código + link** (`/?sala=CODIGO`)
3. Aluno: após login, usa o código na home ou abre o link compartilhado

```bash
cd mobile
flutter pub get
flutter run -d web-server --web-port=8081 \
  --dart-define=API_BASE_URL=http://localhost:8000/api/v1
```

API staff: `POST /api/v1/staff/login`, `GET/POST /api/v1/staff/salas`, `POST /api/v1/aplicacoes/entrar-com-codigo`.


### Comandos úteis

```bash
docker compose exec app pytest -q
docker compose exec app python scripts/legado_import.py
docker compose logs -f app
```

## App Flutter (Android + Web)

Mesmo código em `mobile/`. Contrato da API: `/api/v1`.

```bash
cd mobile
flutter pub get
```

### Web (MVP)

Com o backend no ar:

```bash
flutter run -d chrome
# ou
flutter run -d edge
```

API padrão na web: `http://localhost:8000/api/v1`.

Build estático:

```bash
flutter build web --release
# saída em mobile/build/web
```

### Android

```bash
flutter run
# emulador usa http://10.0.2.2:8000/api/v1 por padrão
```

Device físico / outra URL:

```bash
flutter run --dart-define=API_BASE_URL=http://SEU_IP:8000/api/v1
```

### Shorebird (code push Android)

O app Android está integrado ao **Shorebird** para atualizações OTA de código Dart. Ver [`docs/shorebird.md`](docs/shorebird.md).

```bash
cd mobile
shorebird release android -- --dart-define=API_BASE_URL=https://api.seudominio/api/v1
shorebird patch android -- --dart-define=API_BASE_URL=https://api.seudominio/api/v1
```

Fluxo: **login → termo → avatar → categorias → perguntas** (SQLite / IndexedDB na web) → sync em lote → finalizar.

## Domínio

- Tenant = **Escola** (Organização)
- Vínculo ativo em `escola_user`
- Instrumento: questionário → categorias → subcategorias → perguntas → opções
- Pesquisador: próprios + `compartilhado_na_escola`
- Aluno **nunca** recebe pontuação/classificação na API

## CMS

Painel **SQLAdmin** em `/admin`.

Ações úteis:
- **Questionários:** selecionar itens → *Publicar* ou *Nova versão*
- **Aplicações:** o admin da escola **cria/libera** a aplicação (escola, turma ou aluno)
- **Aplicações:** *Limpar respostas (demo)* — só `admin@vise.local` (apaga no servidor)
- **Importar alunos:** menu *Importar alunos* — CSV com coluna `escola` (nome ou INEP). Exemplo em [`docs/alunos_exemplo.csv`](docs/alunos_exemplo.csv)
- **Professor × turma:** liga o professor às turmas que ele acompanha

## OpenAPI

Nativo do FastAPI em `/docs` e `/redoc`. Espelho resumido em [`docs/openapi.yaml`](docs/openapi.yaml).

## Planejamento

Backlog e sprints (sequência de tasks): [`docs/SPRINTS.md`](docs/SPRINTS.md). Spec de produto: [`docs/Doc_Refatoracao_SMD.docx`](docs/Doc_Refatoracao_SMD.docx).
