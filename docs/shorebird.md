# Shorebird Code Push — VISE-MT

O app Android em [`mobile/`](../mobile/) usa [Shorebird](https://shorebird.dev) para publicar correções Dart **sem passar de novo pela Play Store** (OTA / code push).

| Arquivo | Função |
|---|---|
| [`mobile/shorebird.yaml`](../mobile/shorebird.yaml) | `app_id` (público) + `auto_update` |
| [`mobile/pubspec.yaml`](../mobile/pubspec.yaml) | asset `shorebird.yaml` + `shorebird_code_push` |
| [`mobile/lib/core/shorebird/shorebird_service.dart`](../mobile/lib/core/shorebird/shorebird_service.dart) | log do patch atual (não bloqueia startup) |

**Web não usa Shorebird** — só Android (e iOS, quando houver).

## Pré-requisitos

```bash
# CLI (se ainda não tiver)
curl --proto '=https' --tlsv1.2 https://raw.githubusercontent.com/shorebirdtech/install/main/install.sh -sSf | bash

shorebird login
shorebird doctor
```

O projeto já foi inicializado (`shorebird init`) com o app **VISE-MT**.

## Release (binário da loja)

Sempre use `shorebird release` (não só `flutter build`) para o AAB/APK que for para produção — o updater precisa desse baseline.

```bash
cd mobile

# Exemplo com API de produção (obrigatório repetir o MESMO dart-define no patch)
shorebird release android -- \
  --dart-define=API_BASE_URL=https://api.saudeemmovimentonaescola.com.br/api/v1
```

Artefato típico: `build/app/outputs/bundle/release/app-release.aab`  
Envie esse AAB à Play Console.

## Patch (code push)

Depois de mudar **só Dart** (sem nativo novo, sem asset novo obrigatório, sem upgrade de Flutter do Shorebird):

```bash
cd mobile

shorebird patch android -- \
  --dart-define=API_BASE_URL=https://api.saudeemmovimentonaescola.com.br/api/v1
```

O patch mira a release correspondente (versão em `pubspec.yaml`, ex. `1.0.0+1`).  
Usuários baixam em background; com `auto_update` padrão, o patch entra no **próximo cold start**.

## O que NÃO pode ir só de patch

- Mudança nativa (AndroidManifest, Kotlin, plugins novos)
- Novos assets obrigatórios (em geral)
- Troca de versão do Flutter engine do Shorebird → novo **release** na loja

## Staging (opcional)

```bash
shorebird patch android --track staging -- \
  --dart-define=API_BASE_URL=https://api.saudeemmovimentonaescola.com.br/api/v1
```

Para forçar download manual / UI de “atualizar”, use `auto_update: false` em `shorebird.yaml` e `ShorebirdUpdater` (já no `pubspec`).

## Comandos úteis

```bash
shorebird preview          # testar release localmente
shorebird releases list
shorebird patches list
shorebird upgrade          # atualizar a CLI
```
