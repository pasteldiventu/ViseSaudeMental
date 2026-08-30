# Backlog e sprints — Vise SMA (3 sprints)

Plano sequencial a partir do monorepo atual (**FastAPI + SQLAdmin + Flutter Web/Android**, ver [README](../README.md)) e dos requisitos de [Doc_Refatoracao_SMD.docx](Doc_Refatoracao_SMD.docx).

> O doc original citava Laravel/Filament; o código usa FastAPI/SQLAdmin. Os **objetivos de produto** valem; as tasks usam a stack atual.

---

## Já entregue (baseline)

- [x] Docker (MySQL + app), seed demo, health `/up`
- [x] API aluno `/api/v1` (login, aplicações, categorias, perguntas sem score, lote, finalizar)
- [x] SQLAdmin CRUD básico
- [x] Flutter local-first (Drift + sync) — **Web + Android**
- [x] Fluxo: login → termo → avatar → categorias → perguntas → fim
- [x] Visual wellness + marca Vise
- [x] OpenAPI resumido + testes API mínimos

**Demo:** `admin@vise.local` / `password` · CPF `07593256189` · nasc. `2009-10-21`

---

## Sprint 1 — Instrumento editável + liberação

**Meta:** montar/alterar questionário no CMS e liberar para alunos reais (sem código).

| # | Task |
|---|---|
| 1.1 | CRUD hierárquico no SQLAdmin: questionário → categorias → subcategorias → perguntas → opções → regras |
| 1.2 | Ações **publicar** e **nova versão** no CMS (`PublicarQuestionarioService`) |
| 1.3 | API aluno só entrega questionário **publicado** via aplicação ativa |
| 1.4 | Questionário demo completo (várias categorias, MC + texto, regras de classificação) |
| 1.5 | Cadastros: escolas, turmas, séries, alunos (campos mínimos do doc) |
| 1.6 | Import CSV de alunos ligado ao admin ou script documentado |
| 1.7 | Aplicações: alvo escola / turma / aluno + período + status (mesma `escola_id`) |
| 1.8 | Checklist: editar pergunta → publicar → aluno vê no app |

**Fora:** PDF/Excel, condicionais (Mário), import legado.

---

## Sprint 2 — Validação Web/Mobile + visão staff

**Meta:** caminho feliz estável no browser e no Android; staff vê scores (aluno não).

| # | Task |
|---|---|
| 2.1 | **Testar web** (Chrome/Edge) ponta a ponta com backend local |
| 2.2 | **Testar mobile** (emulador Android + device se possível; `API_BASE_URL`) |
| 2.3 | Offline → outbox → lote → finalizar; corrigir bugs de sync |
| 2.4 | Smoke tests Flutter + gerar **APK** e documentar no README |
| 2.5 | UX: mensagem do avatar por categoria, progressos, demo “limpar respostas” |
| 2.6 | Resultados e respostas (leitura) no SQLAdmin, filtrados por escola/aplicação |
| 2.7 | Dashboard mínimo: alunos, aplicações ativas, % concluído |
| 2.8 | Atualizar `docs/openapi.yaml` com endpoints reais |

**Fora:** iOS, avatar áudio/Lottie, exportações.

---

## Sprint 3 — Isolamento, legado, relatórios e endurecimento

**Meta:** multi-usuário seguro, dados antigos, indicadores exportáveis.

| # | Task |
|---|---|
| 3.1 | Vínculos `escola_user` (ativar/inativar) + papéis admin_escola / pesquisador / professor |
| 3.2 | Isolamento pesquisador: próprios + `compartilhado_na_escola`; testes escola X ≠ Y |
| 3.3 | Implementar `legado_import.py` (hoje stub) e validar com dump de teste |
| 3.4 | Migrations Alembic confiáveis (não só `create_all` no boot) |
| 3.5 | Rate limit no login, CORS, `SECRET_KEY`; logs de login / termo / finalizar |
| 3.6 | Testes: lote offline, finalizar, scoring com faixas |
| 3.7 | Export **Excel** (e PDF se der tempo) de resultados/respostas |
| 3.8 | Relatórios por turma/escola; duplicar questionário para outra escola |
| 3.9 | Polimento visual + acessibilidade básica |

---

## Depois das 3 sprints (backlog)

| Item | Motivo |
|---|---|
| Avatar animado / áudio | “Verificar com a equipe” no doc |
| Perguntas condicionais / feedbacks | Pendência Mário |
| iOS | Futuro no doc |
| GET `/resultado` no app | **Proibido** — aluno nunca vê score |

---

## Sequência

```text
Sprint 1  Questionário editável + alunos + aplicação
    │
    ▼
Sprint 2  Testar Web/Mobile + resultados no CMS
    │
    ▼
Sprint 3  Isolamento + legado + relatórios + segurança
```

## Foco curto (demo)

1. Sprint **1.1–1.4** + **1.7** (instrumento + aplicação)  
2. Sprint **2.1–2.2** (testar web e mobile)  
3. Sprint **2.6** (staff vê resultado no admin)
