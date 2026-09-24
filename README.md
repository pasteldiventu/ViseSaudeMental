# Vise Saúde Mental

Monorepo: **PHP puro + MySQL** (`backend-php/`: API `/api/v1` + painel `/admin`) e **Flutter** local-first (`mobile/`) — Android + **Web**.

> O antigo backend FastAPI (`backend/`) foi mantido só como referência; o backend oficial é o `backend-php/`, com o mesmo contrato de API e o mesmo esquema de banco (um banco criado pelo Python funciona sem migração de dados).

## Subir o backend (Docker)

```bash
cd ViseSaudeMental
docker compose up --build -d
```

O container (PHP 8.2 + Apache) aguarda o MySQL, cria/atualiza as tabelas, roda o seed de demonstração e atende na porta **8000**.

> No Windows, se a porta `3306` estiver ocupada por um MySQL local, o Compose publica o MySQL do Docker em **`3307`**.

Sem Docker (PHP 8.0+ com `pdo_mysql` e um MySQL disponível):

```bash
cd backend-php
cp .env.example .env        # ajuste DB_* e SECRET_KEY
php bin/install.php --seed  # cria tabelas + dados demo
php -S 0.0.0.0:8000 index.php
```

| Recurso | URL / dado |
|---|---|
| Health | http://localhost:8000/up |
| Painel (CMS) | http://localhost:8000/admin |
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
docker compose logs -f app
docker compose exec app php bin/install.php --admin=voce@escola.br --password=senhaForte123
# testes de integração (APAGAM as tabelas do banco informado — use um banco só para testes)
TEST_DB_HOST=127.0.0.1 TEST_DB_PORT=3307 TEST_DB_NAME=vise_test TEST_DB_USER=root TEST_DB_PASSWORD=root \
  php backend-php/tests/run.php
```

## Deploy em servidor PHP (hospedagem compartilhada, cPanel, VPS)

Requisitos: **PHP 8.0+** com `pdo_mysql` (padrão em quase todas as hospedagens) e **MySQL 5.7+ / MariaDB 10.3+**. Não usa Composer nem framework.

1. Crie o banco MySQL (utf8mb4) e o usuário no painel da hospedagem.
2. Envie o **conteúdo** de `backend-php/` para a pasta pública (ex.: `public_html/` ou `public_html/vise/`). Os arquivos `.htaccess` fazem parte do deploy.
3. Copie `.env.example` para `.env` e preencha `DB_*`, uma `SECRET_KEY` longa, `PUBLIC_APP_URL` (endereço do app web, usado no link das salas) e `CORS_ORIGINS`.
4. Crie as tabelas de um destes jeitos:
   - **Instalador web:** coloque um valor em `INSTALL_KEY` no `.env`, abra `https://seu-dominio/install`, informe a chave, o e-mail/senha do admin geral e (opcional) os dados demo. Depois **apague o `INSTALL_KEY`**.
   - **phpMyAdmin:** importe `database/schema.sql` e crie o admin via SSH com `php bin/install.php --admin=... --password=...`.
5. Teste `https://seu-dominio/up` → `{"status":"ok"}` e entre em `https://seu-dominio/admin`.

Notas:
- **Apache/LiteSpeed:** o `.htaccess` já faz o roteamento, bloqueia `src/`, `database/`, `bin/`, `tests/` e o `.env`, e repassa o cabeçalho `Authorization`.
- **Sem `mod_rewrite`:** a API também responde em `https://seu-dominio/index.php/api/v1/...`.
- **Nginx:** aponte a raiz para a pasta e use `try_files $uri /index.php?$query_string;`, negando `location ~ ^/(src|database|bin|tests|docker)/` e arquivos `/\.`.
- O app envia o token também no cabeçalho `X-Auth-Token`, para hospedagens que descartam o `Authorization`.
- Tudo é gravado em UTC. Tokens da equipe são JWT HS256 e senhas bcrypt, compatíveis com o backend Python (mesma `SECRET_KEY`).

Build do app apontando para o servidor PHP:

```bash
cd mobile
flutter build web --release --dart-define=API_BASE_URL=https://seu-dominio/api/v1
flutter build apk --release --dart-define=API_BASE_URL=https://seu-dominio/api/v1
```

Se o backend estiver numa subpasta, inclua-a: `https://seu-dominio/vise/api/v1`.

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

Painel em PHP em `/admin` (menu e permissões por perfil; cada usuário só enxerga as escolas em que tem vínculo ativo).

Ações úteis:
- **Questionários:** selecionar itens → *Publicar* ou *Nova versão*
- **Aplicações:** o admin da escola **cria/libera** a aplicação (escola, turma ou aluno)
- **Aplicações:** *Encerrar* e *Limpar respostas (demo)* — esta última só para admin geral (apaga no servidor)
- **Importar alunos:** menu *Importar alunos* — CSV com coluna `escola` (nome ou INEP). Exemplo em [`docs/alunos_exemplo.csv`](docs/alunos_exemplo.csv)
- **Professor × turma:** liga o professor às turmas que ele acompanha

## OpenAPI

Contrato resumido em [`docs/openapi.yaml`](docs/openapi.yaml) (o backend PHP segue o mesmo contrato; erros vêm como `{"detail": "..."}`).

## Planejamento

Backlog e sprints (sequência de tasks): [`docs/SPRINTS.md`](docs/SPRINTS.md). Spec de produto: [`docs/Doc_Refatoracao_SMD.docx`](docs/Doc_Refatoracao_SMD.docx).
