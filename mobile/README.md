# App Flutter — Vise SMA

Cliente **aluno + equipe** (Android + Web), local-first com Drift/SQLite e sync em lote.

## Rodar (dev)

Backend em `http://localhost:8000` (ver README da raiz).

```bash
flutter pub get
flutter run -d chrome          # web
flutter run -d web-server --web-port=8081 \
  --dart-define=API_BASE_URL=http://localhost:8000/api/v1
flutter run                    # android (emulador → 10.0.2.2:8000)
```

- Aluno demo: CPF `07593256189` · nasc. `2009-10-21`
- Equipe: `professor@vise.local` / `password` (ou admin / pesquisador)

## Shorebird (code push Android)

Configurado em [`shorebird.yaml`](shorebird.yaml). Guia completo: [`docs/shorebird.md`](../docs/shorebird.md).

```bash
# Binário para a Play Store (baseline)
shorebird release android -- \
  --dart-define=API_BASE_URL=https://SEU_DOMINIO/api/v1

# Correção Dart OTA (mesmo dart-define da release)
shorebird patch android -- \
  --dart-define=API_BASE_URL=https://SEU_DOMINIO/api/v1
```

`flutter run` normal **não** inclui o updater Shorebird; use `shorebird release` / `shorebird preview` para testar patches.
