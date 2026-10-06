# Vise Saúde Mental (VISE-MT)

Sistema de triagem de saúde mental em escolas. O **aluno** responde questionários lúdicos (com avatar) no celular ou no navegador, mesmo sem internet; a **equipe** (administradores, pesquisadores e professores) monta os questionários, libera salas por código/link e acompanha indicadores, níveis de atenção e relatórios em Excel.

| Parte | Pasta | Tecnologia |
|---|---|---|
| Backend: API REST + painel web (CMS) | [`backend-php/`](backend-php) | PHP 8 puro (sem Composer/framework) + MySQL |
| App do aluno e da equipe (Android + Web) | [`mobile/`](mobile) | Flutter, Riverpod, Dio, Drift (SQLite/IndexedDB) |
| Documentação | [`docs/`](docs) | OpenAPI, sprints, spec de produto, Shorebird |

**Produção:** painel em <https://visemt.com.br/api/admin> · API em `https://visemt.com.br/api/api/v1` (o backend está na subpasta `/api` do domínio). O APK de release já aponta para esse endereço.

## Estrutura

```text
backend-php/
  index.php            ponto de entrada (roteamento via .htaccess)
  src/Api/             API do aluno (/api/v1) e da equipe (/api/v1/staff)
  src/Admin/           painel /admin: login, CRUD genérico (Resources.php), dashboard, relatórios, importação
  src/Services/        regras de negócio: resultados, indicadores, relatório Excel, importação, códigos de sala
  src/Support/         utilitários: leitura/escrita de XLSX e ZIP sem dependências, datas, textos
  src/Install/         instalador (schema), dados demo, importação do sistema anterior
  src/Security/        JWT da equipe, tokens do aluno, senhas bcrypt
  database/schema.sql  esquema completo (idempotente)
  bin/                 install.php, importar-legado.php (linha de comando)
  static/              CSS e logo do painel
  tests/run.php        testes de integração contra um MySQL real
mobile/
  lib/core/            configuração (URL da API), cliente HTTP, tema, Shorebird
  lib/data/            banco local (Drift) e repositórios (auth, quiz, sync, staff, cadastros, painel)
  lib/features/        telas: entrada, login, termo, avatar, categorias, perguntas, área da equipe
  lib/providers/       estado do app (AppController)
  test/                testes de widget e de repositório
docs/                  openapi.yaml, SPRINTS.md, Doc_Refatoracao_SMD.pdf, shorebird.md, alunos_exemplo.csv
docker-compose.yml     MySQL 8 + PHP 8.2/Apache para desenvolvimento
```

## Domínio e perfis

- **Escola** é o tenant. Usuários da equipe têm vínculo (`escola_user`) com uma ou mais escolas e só enxergam dados delas.
- **Instrumento:** questionário → categorias → subcategorias → perguntas (múltipla escolha ou texto livre) → opções com pontuação. **Regras de classificação** por categoria transformam a soma em rótulo + nível de atenção (*Adequado*, *Atenção*, *Prioritário*).
- **Aplicação (sala):** libera um questionário para a escola toda, uma turma ou um aluno, com **código de sala** e link `/?sala=CODIGO`.
- O aluno **nunca** recebe pontuação nem classificação pela API.

| Perfil | O que faz |
|---|---|
| Admin geral (`is_superuser`) | Tudo, em todas as escolas; cadastra escolas; importação de escolas |
| Admin da escola | Equipe, séries, turmas, alunos, aplicações e avatares da escola; vê questionários e resultados |
| Pesquisador | Monta questionários (categorias, perguntas, opções, regras), publica, cria versões e libera salas; vê os próprios questionários e os compartilhados na escola |
| Professor | Consulta as turmas ligadas a ele, os alunos, as aplicações e os resultados |
| Aluno | Entra no app com **CPF + data de nascimento** (cadastrados pela equipe no painel, no app ou por planilha) |

## Fluxos

**Aluno** (app, Android ou web): *Sou aluno* → CPF + nascimento → aceite do termo → lista de questionários ou código da sala → apresentação do avatar → categorias → perguntas. As respostas ficam no aparelho (SQLite; IndexedDB na web) e são enviadas em lote quando há conexão; ao concluir, o servidor calcula o resultado. Sem internet, o aluno continua respondendo o que já foi baixado.

**Equipe** (app ou painel web): *Sou da equipe* → e-mail/senha → **Salas** (criar sala, copiar/compartilhar link, encerrar), **Painel**, **Relatórios**, **Importar planilha** e os **cadastros** permitidos ao perfil. No detalhe de um questionário publicado há o atalho **Liberar em sala e enviar link**.

## Rodar localmente

### Com Docker

```bash
docker compose up --build -d
```

O container (PHP 8.2 + Apache) espera o MySQL, cria/atualiza as tabelas, roda o seed de demonstração e atende em **http://localhost:8000**. O MySQL fica publicado na porta **3307**.

### Sem Docker

PHP 8.0+ com `pdo_mysql` e um MySQL 5.7+/MariaDB 10.3+:

```bash
cd backend-php
cp .env.example .env        # ajuste DB_* e SECRET_KEY
php bin/install.php --seed  # cria tabelas + dados demo
php -S 0.0.0.0:8000 index.php
```

| Recurso | URL / dado |
|---|---|
| Health | http://localhost:8000/up |
| Painel | http://localhost:8000/admin |
| API | http://localhost:8000/api/v1 |
| Admin geral | `admin@vise.local` / `password` |
| Admin escola | `admin.escola@vise.local` / `password` |
| Pesquisador | `pesquisador@vise.local` / `password` |
| Professor | `professor@vise.local` / `password` |
| Aluno demo | CPF `07593256189` · nascimento `2009-10-21` |

Outras opções do instalador:

```bash
php bin/install.php --admin=voce@escola.br --password=senhaForte123   # cria/atualiza admin geral
php bin/install.php --seed --seed-respostas=80                        # turmas, alunos e respostas fictícias (só demo)
```

### App Flutter

```bash
cd mobile
flutter pub get
flutter run -d chrome                                   # web, API em http://localhost:8000/api/v1
flutter run                                             # Android; o emulador usa http://10.0.2.2:8000/api/v1
flutter run --dart-define=API_BASE_URL=http://SEU_IP:8000/api/v1   # aparelho físico
```

URL da API (`lib/core/config/env.dart`): `--dart-define=API_BASE_URL=...` tem prioridade; sem ele, **builds de release usam a produção** (`https://visemt.com.br/api/api/v1`) e o modo debug usa o backend local.

## Build e deploy

### App

```bash
cd mobile
flutter build apk --release        # APK em build/app/outputs/flutter-apk/app-release.apk (aponta para a produção)
flutter build web --release        # site estático em build/web
# outro servidor: acrescente --dart-define=API_BASE_URL=https://seu-dominio/api/v1
```

Atualizações OTA do código Dart no Android via **Shorebird**: ver [`docs/shorebird.md`](docs/shorebird.md) e [`mobile/README.md`](mobile/README.md).

### Backend em servidor PHP (hospedagem compartilhada, cPanel, VPS)

1. Crie o banco MySQL (utf8mb4) e o usuário.
2. Envie o **conteúdo** de `backend-php/` para a pasta pública (ex.: `public_html/api/`). Os arquivos `.htaccess` fazem parte do deploy.
3. Copie `.env.example` para `.env` e preencha `DB_*`, uma `SECRET_KEY` longa, `PUBLIC_APP_URL` (endereço do app web, usado no link das salas), `CORS_ORIGINS` e `APP_TIMEZONE` (padrão `America/Cuiaba`).
4. Crie as tabelas:
   - **Instalador web:** defina `INSTALL_KEY` no `.env`, abra `https://seu-dominio/install`, informe a chave, o admin geral e (opcional) os dados demo. Depois **apague o `INSTALL_KEY`**.
   - **SSH:** `php bin/install.php --admin=... --password=...`.
5. Teste `https://seu-dominio/up` → `{"status":"ok"}` e entre em `/admin`.

**Atualizar uma instalação existente:** substitua as pastas `src/`, `static/`, `database/` e `bin/` (mantenha o `.env`) e rode o instalador de novo (`php bin/install.php` ou `/install`). Ele só cria o que falta e aplica ajustes de colunas; não apaga dados.

Notas:
- **Apache/LiteSpeed:** o `.htaccess` faz o roteamento, bloqueia `src/`, `database/`, `bin/`, `tests/` e o `.env`, e repassa o cabeçalho `Authorization`. O app também envia o token em `X-Auth-Token`, para hospedagens que descartam o `Authorization`.
- **Sem `mod_rewrite`:** a API responde em `https://seu-dominio/index.php/api/v1/...`.
- **Nginx:** raiz na pasta, `try_files $uri /index.php?$query_string;`, negando `location ~ ^/(src|database|bin|tests|docker)/` e arquivos `/\.`.
- Tudo é gravado em **UTC**; painel e relatórios exibem no fuso de `APP_TIMEZONE`. Tokens da equipe são JWT HS256 e senhas bcrypt (compatíveis com o antigo backend Python; um banco criado por ele funciona sem migração de dados).

## Painel, relatórios e importação

Disponíveis no painel web e no app (área da equipe), com as mesmas regras de acesso.

- **Painel** — indicadores do recorte (escola, turma, questionário, período): alunos, participação, concluídos, aplicações ativas, alunos em *Prioritário* e *Atenção*; conclusões por dia/mês; níveis por categoria; participação por turma; aplicações em andamento e alertas recentes.
- **Relatórios** — filtros por escola, turma, série, turno, sexo, questionário e período. Gera **Excel (.xlsx)** com as abas *Resumo*, *Por escola*, *Por turma*, *Classificação*, *Resultados por aluno*, *Respostas por pergunta* e, opcionalmente, *Pendentes* e *Respostas detalhadas*. **Anonimizar alunos** troca nomes por códigos e remove CPF, matrícula e contatos.
- **Importar planilha** — escolas (admin geral), turmas, alunos e equipe, em `.xlsx` ou `.csv` (até 10.000 linhas), com **planilha modelo** por tipo e botão **Simular**. Cria ou atualiza (escola por INEP, turma por escola+nome, aluno por escola+CPF, equipe por e-mail). Exemplo: [`docs/alunos_exemplo.csv`](docs/alunos_exemplo.csv).
- **Exportar listas** — toda listagem do painel tem *Exportar Excel*, respeitando busca e filtros.
- **Outras ações** — questionários: *Publicar* e *Nova versão*; aplicações: *Encerrar* e *Limpar respostas (demo)* (só admin geral); professor × turma.

Os níveis vêm do campo **Nível de atenção** das regras de classificação (baixo / moderado / alto). Regras sem nível aparecem como "Sem nível".

Boas práticas: compartilhe relatórios nominais só com quem acompanha os alunos (LGPD) e prefira a versão anonimizada para pesquisa e gestão; sempre rode **Simular** antes de importar.

## Importar o backup do sistema anterior

O backup `.sql` do sistema antigo (tabelas `aluno`, `escola`, `categoria_pergunta`, `pergunta`, `respostas`…) pode ser trazido para o sistema novo:

- **Pelo navegador:** com `INSTALL_KEY` no `.env`, abra `/install`, cartão **Importar backup do sistema anterior**. Envie sem marcar *Gravar no banco* para ver o relatório e depois marcando para gravar.
- **Por SSH:** `php bin/importar-legado.php backup.sql` (simula) e `php bin/importar-legado.php backup.sql --aplicar` (grava). `--pesquisador=email` define o dono dos questionários (padrão: primeiro admin geral).

Como os dados são convertidos: cada "categoria" antiga é um questionário inteiro e vira um questionário publicado com uma categoria (as regras verde/amarelo/laranja/vermelho viram os níveis), copiado para cada escola que o usava; as respostas antigas ficam numa aplicação **encerrada** com as datas originais, e o resultado é calculado para quem completou. Os alunos continuam entrando com o mesmo CPF e nascimento. O relatório aponta CPFs ausentes ou inválidos, CPFs repetidos e turmas sem nome no backup. A importação pode ser repetida sem duplicar (tabela `legado_map`).

> O backup contém dados pessoais de alunos: não o coloque no repositório.

## API

Contrato em [`docs/openapi.yaml`](docs/openapi.yaml). Erros vêm como `{"detail": "..."}`. Principais rotas (prefixo `/api/v1`):

| Área | Rotas |
|---|---|
| Aluno | `POST /login` (CPF + nascimento), `POST /logout`, `GET /me`, `POST /termos`, `GET /avatar` |
| Questionários do aluno | `GET /aplicacoes`, `POST /aplicacoes/entrar-com-codigo`, `GET /salas/{codigo}` (público), `GET /aplicacoes/{id}/categorias`, `GET /aplicacoes/{id}/categorias/{c}/perguntas`, `POST /aplicacoes/{id}/respostas`, `POST /aplicacoes/{id}/respostas/lote`, `POST /aplicacoes/{id}/finalizar` |
| Equipe | `POST /staff/login`, `GET /staff/me`, `GET /staff/questionarios`, `GET /staff/turmas`, `GET/POST /staff/salas`, `POST /staff/salas/{id}/encerrar` |
| Cadastros da equipe | `GET /staff/cadastros`, `GET/POST /staff/cadastros/{recurso}`, `GET /staff/cadastros/{recurso}/formulario`, `GET/POST /staff/cadastros/{recurso}/{id}`, `POST .../{id}/excluir`, `POST .../acoes/{acao}` |
| Painel e relatórios | `GET /staff/painel`, `GET /staff/relatorios/opcoes`, `GET /staff/relatorios/exportar`, `GET /staff/importacao`, `GET /staff/importacao/{tipo}/modelo`, `POST /staff/importacao/{tipo}` |

## Testes

```bash
# Backend: testes de integração (APAGAM as tabelas do banco informado — use um banco só para testes)
TEST_DB_HOST=127.0.0.1 TEST_DB_PORT=3307 TEST_DB_NAME=vise_test TEST_DB_USER=root TEST_DB_PASSWORD=root \
  php backend-php/tests/run.php            # opcional: um filtro pelo nome do teste como argumento

# App
cd mobile && flutter analyze && flutter test
```

## Documentação e planejamento

- Backlog e sprints: [`docs/SPRINTS.md`](docs/SPRINTS.md)
- Spec de produto: [`docs/Doc_Refatoracao_SMD.pdf`](docs/Doc_Refatoracao_SMD.pdf)
- Arquitetura original: [`arquitetura_vise_sma_ccfc2ae7.plan.md`](arquitetura_vise_sma_ccfc2ae7.plan.md)
- Shorebird (code push Android): [`docs/shorebird.md`](docs/shorebird.md)
