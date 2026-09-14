"""Papéis do CMS (staff). Aluno NÃO usa este módulo — só a API /api/v1."""

from __future__ import annotations

from starlette.requests import Request

ROLE_ADMIN_ESCOLA = "admin_escola"
ROLE_PESQUISADOR = "pesquisador"
ROLE_PROFESSOR = "professor"

STAFF_ROLES = frozenset(
    {ROLE_ADMIN_ESCOLA, ROLE_PESQUISADOR, ROLE_PROFESSOR}
)

ROLE_LABELS = {
    ROLE_ADMIN_ESCOLA: "Administrador da Escola",
    ROLE_PESQUISADOR: "Pesquisador",
    ROLE_PROFESSOR: "Professor",
}

# "write" inclui create/edit/delete; "read" só listagem/detalhe.
PERMISSIONS: dict[str, dict[str, str]] = {
    ROLE_ADMIN_ESCOLA: {
        "escola": "read",
        "user": "write",
        "escola_user": "write",
        "professor_turma": "write",
        "serie": "write",
        "turma": "write",
        "aluno": "write",
        "questionario": "read",
        "aplicacao": "write",
        "resposta": "read",
        "resultado": "read",
        "avatar": "write",
        "termo": "read",
    },
    ROLE_PESQUISADOR: {
        "questionario": "write",
        "categoria": "write",
        "subcategoria": "write",
        "pergunta": "write",
        "opcao": "write",
        "regra": "write",
        "aplicacao": "write",
        "resposta": "read",
        "resultado": "read",
        "avatar": "read",
    },
    ROLE_PROFESSOR: {
        "turma": "read",
        "aluno": "read",
        "aplicacao": "read",
        "resposta": "read",
        "resultado": "read",
    },
}


def session_roles(request: Request) -> set[str]:
    raw = request.session.get("roles") or []
    return {str(role) for role in raw}


def is_superuser(request: Request) -> bool:
    return bool(request.session.get("is_superuser"))


def can_access(request: Request, resource: str) -> bool:
    if is_superuser(request):
        return True
    for role in session_roles(request):
        if resource in PERMISSIONS.get(role, {}):
            return True
    return False


def can_write(request: Request, resource: str) -> bool:
    if is_superuser(request):
        return True
    for role in session_roles(request):
        if PERMISSIONS.get(role, {}).get(resource) == "write":
            return True
    return False


def is_pesquisador_only(request: Request) -> bool:
    """Pesquisador sem admin_escola: isola questionários próprios."""
    if is_superuser(request):
        return False
    roles = session_roles(request)
    return ROLE_PESQUISADOR in roles and ROLE_ADMIN_ESCOLA not in roles


def is_professor_only(request: Request) -> bool:
    if is_superuser(request):
        return False
    roles = session_roles(request)
    return ROLE_PROFESSOR in roles and ROLE_ADMIN_ESCOLA not in roles


def escola_ids(request: Request) -> list[int]:
    return [int(item) for item in (request.session.get("escola_ids") or [])]


def turma_ids(request: Request) -> list[int]:
    return [int(item) for item in (request.session.get("turma_ids") or [])]
