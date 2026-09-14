from app.admin.roles import (
    ROLE_ADMIN_ESCOLA,
    ROLE_PESQUISADOR,
    ROLE_PROFESSOR,
    PERMISSIONS,
    can_access,
    can_write,
)


class _SessionRequest:
    def __init__(self, *, superuser=False, roles=None):
        self.session = {
            "is_superuser": superuser,
            "roles": roles or [],
        }


def test_admin_escola_nao_cria_questionario():
    request = _SessionRequest(roles=[ROLE_ADMIN_ESCOLA])
    assert can_access(request, "aluno")
    assert can_write(request, "aluno")
    assert can_access(request, "resultado")
    assert not can_write(request, "resultado")
    assert can_access(request, "questionario")
    assert not can_write(request, "questionario")
    assert can_write(request, "aplicacao")
    assert can_write(request, "professor_turma")


def test_pesquisador_escreve_instrumento_e_nao_aluno():
    request = _SessionRequest(roles=[ROLE_PESQUISADOR])
    assert can_write(request, "questionario")
    assert can_write(request, "pergunta")
    assert not can_access(request, "aluno")


def test_professor_somente_leitura_relatorios():
    request = _SessionRequest(roles=[ROLE_PROFESSOR])
    assert can_access(request, "turma")
    assert not can_write(request, "turma")
    assert can_access(request, "resultado")
    assert not can_write(request, "resultado")
    assert not can_access(request, "questionario")


def test_superuser_tudo():
    request = _SessionRequest(superuser=True)
    for resource in {
        key for perms in PERMISSIONS.values() for key in perms
    }:
        assert can_access(request, resource)
        assert can_write(request, resource)
