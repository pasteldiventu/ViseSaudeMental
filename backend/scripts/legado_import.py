#!/usr/bin/env python3
"""Stub de importação do legado — mapeamento documentado."""
from __future__ import annotations

import argparse


MAPEAMENTO = [
    ("escola", "escolas (parse cidade → municipio/uf)"),
    ("turma + aluno.escola_id", "turmas por escola (split obrigatório)"),
    ("serie", "series"),
    ("aluno", "alunos (CPF normalizado)"),
    ("categoria_pergunta (ativas)", "questionario + categorias"),
    ("pergunta / opcoes_resposta", "perguntas / opcoes_resposta"),
    ("regras_classificacao", "regras_classificacao"),
    ("respostas*", "2ª onda (exige aplicação sintética)"),
]


def main() -> None:
    parser = argparse.ArgumentParser(description="Importa dump legado (stub)")
    parser.add_argument("source", nargs="?", help="Caminho do dump SQL ou CSV")
    args = parser.parse_args()
    print("Comando em construção. Mapeamento previsto:\n")
    for legado, novo in MAPEAMENTO:
        print(f"  {legado:40} → {novo}")
    print(f"\nSource: {args.source or '(não informado)'}")


if __name__ == "__main__":
    main()
