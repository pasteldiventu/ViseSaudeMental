from html import escape

from sqladmin import BaseView, expose
from starlette.requests import Request
from starlette.responses import HTMLResponse

from app.admin.roles import can_write, escola_ids, is_superuser
from app.database import SessionLocal
from app.services.import_alunos import ImportAlunosCsvService


_FORM = """
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Importar alunos</title>
  <style>
    body {{ font-family: sans-serif; margin: 2rem; max-width: 720px; }}
    .ok {{ color: #157347; }}
    .err {{ color: #b02a37; }}
    code {{ background: #f4f4f4; padding: 2px 6px; }}
  </style>
</head>
<body>
  <h1>Importar alunos (CSV)</h1>
  <p>Colunas obrigatórias:
    <code>escola,nome,cpf,data_nascimento,sexo,matricula,turma_nome,responsavel,contato_responsavel</code>
  </p>
  <p><code>escola</code> aceita o <strong>nome</strong> ou o <strong>INEP</strong>.
     Delimitador <code>,</code> ou <code>;</code>.</p>
  {mensagem}
  <form method="post" enctype="multipart/form-data">
    <p><input type="file" name="arquivo" accept=".csv,text/csv" required></p>
    <button type="submit">Enviar CSV</button>
  </form>
  <p><a href="/admin/aluno/list">Voltar aos alunos</a></p>
</body>
</html>
"""


class ImportAlunosView(BaseView):
    name = "Importar alunos"
    identity = "importar-alunos"
    icon = "fa-solid fa-file-csv"

    def is_accessible(self, request: Request) -> bool:
        return is_superuser(request) or can_write(request, "aluno")

    def is_visible(self, request: Request) -> bool:
        return self.is_accessible(request)

    @expose("/importar-alunos", methods=["GET", "POST"])
    async def page(self, request: Request):
        mensagem = ""
        if request.method == "POST":
            form = await request.form()
            upload = form.get("arquivo")
            conteudo = await upload.read() if upload is not None else b""
            if not conteudo:
                mensagem = '<p class="err">Selecione um arquivo CSV.</p>'
            else:
                allowed = None if is_superuser(request) else escola_ids(request)
                with SessionLocal() as db:
                    resultado = ImportAlunosCsvService.importar_bytes(
                        db, conteudo, allowed_escola_ids=allowed
                    )
                erros = resultado.get("errors") or []
                linhas = [
                    f'<p class="ok">{resultado["success"]} aluno(s) importado(s).</p>'
                ]
                if erros:
                    itens = "".join(f"<li>{escape(str(item))}</li>" for item in erros)
                    linhas.append(f'<ul class="err">{itens}</ul>')
                mensagem = "".join(linhas)
        return HTMLResponse(_FORM.format(mensagem=mensagem))
