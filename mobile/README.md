# App Flutter — Vise Saúde Mental

Cliente **aluno + equipe** (Android + Web). O aluno responde offline (Drift: SQLite no Android, IndexedDB na web) e as respostas são sincronizadas em lote; a equipe cria salas, compartilha o link e acessa painel, relatórios, importação e cadastros. Visão geral do projeto no [README da raiz](../README.md).

## Rodar (dev)

Backend local em `http://localhost:8000` (ver README da raiz).

```bash
flutter pub get
flutter run -d chrome          # web → http://localhost:8000/api/v1
flutter run                    # android (emulador → http://10.0.2.2:8000/api/v1)
flutter run --dart-define=API_BASE_URL=http://SEU_IP:8000/api/v1   # aparelho físico
```

- Aluno demo: CPF `07593256189` · nasc. `2009-10-21`
- Equipe: `professor@vise.local` / `password` (ou admin / pesquisador)

## URL da API

Definida em [`lib/core/config/env.dart`](lib/core/config/env.dart): `--dart-define=API_BASE_URL=...` tem prioridade; sem ele, **release usa a produção** (`https://visemt.com.br/api/api/v1`) e debug usa o backend local.

## Build

```bash
flutter build apk --release    # build/app/outputs/flutter-apk/app-release.apk
flutter build web --release    # build/web
```

Depois de mudar tabelas do banco local (`lib/data/local/tables.dart`), regenere o código do Drift e suba o `schemaVersion` com a migração em `app_database.dart`:

```bash
dart run build_runner build --delete-conflicting-outputs
```

## Testes

```bash
flutter analyze
flutter test
```

## Estrutura

- `lib/core/` — configuração, cliente HTTP (`ApiClient`, envia `Authorization` e `X-Auth-Token`), tema, Shorebird
- `lib/data/local/` — banco local (cache de questionários, progresso e fila de respostas)
- `lib/data/repositories/` — auth do aluno, quiz (cache), sync, equipe, cadastros, painel
- `lib/providers/app_providers.dart` — `AppController` (sessão, modo aluno/equipe, sala aberta)
- `lib/features/` — telas do aluno (entrada, login, termo, avatar, categorias, perguntas) e da equipe (`staff/`)

## Shorebird (code push Android)

Configurado em [`shorebird.yaml`](shorebird.yaml). Guia completo: [`docs/shorebird.md`](../docs/shorebird.md).

```bash
# Binário para a Play Store (baseline)
shorebird release android -- \
  --dart-define=API_BASE_URL=https://visemt.com.br/api/api/v1

# Correção Dart OTA (mesmo dart-define da release)
shorebird patch android -- \
  --dart-define=API_BASE_URL=https://visemt.com.br/api/api/v1
```

`flutter run` normal **não** inclui o updater Shorebird; use `shorebird release` / `shorebird preview` para testar patches.
