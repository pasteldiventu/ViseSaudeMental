<?php

declare(strict_types=1);

namespace Vise\Services;

use Vise\Db;
use Vise\Http\HttpError;

/** Publicação e versionamento de questionários. */
final class QuestionarioService
{
    public static function publicar(int $questionarioId): void
    {
        $status = Db::value('SELECT status FROM questionarios WHERE id = ?', [$questionarioId]);
        if ($status !== 'rascunho') {
            throw new HttpError(422, 'Somente questionários em rascunho podem ser publicados.');
        }
        Db::update('questionarios', ['status' => 'publicado'], $questionarioId);
    }

    public static function totalPerguntas(int $questionarioId): int
    {
        return (int) Db::value(
            'SELECT COUNT(p.id) FROM perguntas p
             JOIN categorias c ON c.id = p.categoria_id
             WHERE c.questionario_id = ? AND p.deleted_at IS NULL AND c.deleted_at IS NULL',
            [$questionarioId]
        );
    }

    /** Sala sem perguntas deixaria o aluno numa tela vazia. */
    public static function exigirPerguntas(int $questionarioId): void
    {
        if (self::totalPerguntas($questionarioId) === 0) {
            throw new HttpError(422, 'Este questionário ainda não tem perguntas. Cadastre ao menos uma pergunta antes de liberar a sala.');
        }
    }

    /** Copia o questionário publicado (categorias, subcategorias, perguntas, opções e regras) como rascunho v+1. */
    public static function criarNovaVersao(int $questionarioId): int
    {
        $original = Db::one('SELECT * FROM questionarios WHERE id = ?', [$questionarioId]);
        if ($original === null || $original['status'] !== 'publicado') {
            throw new HttpError(422, 'A nova versão deve partir de um questionário publicado.');
        }

        return Db::transaction(static function () use ($original): int {
            $now = Db::now();
            $stamp = ['created_at' => $now, 'updated_at' => $now];
            $novoId = Db::insert('questionarios', [
                'escola_id' => $original['escola_id'],
                'pesquisador_id' => $original['pesquisador_id'],
                'nome' => $original['nome'],
                'descricao' => $original['descricao'],
                'status' => 'rascunho',
                'publico_alvo' => $original['publico_alvo'],
                'versao' => (int) $original['versao'] + 1,
                'parent_id' => $original['id'],
                'compartilhado_na_escola' => (int) $original['compartilhado_na_escola'],
            ] + $stamp);

            $categoriasMap = [];
            $categorias = Db::all(
                'SELECT * FROM categorias WHERE questionario_id = ? AND deleted_at IS NULL ORDER BY ordem, id',
                [$original['id']]
            );
            foreach ($categorias as $categoria) {
                $novaCategoria = Db::insert('categorias', [
                    'questionario_id' => $novoId,
                    'escola_id' => $categoria['escola_id'],
                    'nome' => $categoria['nome'],
                    'ordem' => $categoria['ordem'],
                    'cor' => $categoria['cor'],
                    'mensagem_avatar' => $categoria['mensagem_avatar'],
                    'imagem_apoio' => $categoria['imagem_apoio'],
                ] + $stamp);
                $categoriasMap[(int) $categoria['id']] = $novaCategoria;

                $subcategoriasMap = [];
                $subcategorias = Db::all(
                    'SELECT * FROM subcategorias WHERE categoria_id = ? AND deleted_at IS NULL ORDER BY ordem, id',
                    [$categoria['id']]
                );
                foreach ($subcategorias as $sub) {
                    $subcategoriasMap[(int) $sub['id']] = Db::insert('subcategorias', [
                        'categoria_id' => $novaCategoria,
                        'escola_id' => $sub['escola_id'],
                        'nome' => $sub['nome'],
                        'ordem' => $sub['ordem'],
                    ] + $stamp);
                }

                $perguntas = Db::all(
                    'SELECT * FROM perguntas WHERE categoria_id = ? AND deleted_at IS NULL ORDER BY ordem, id',
                    [$categoria['id']]
                );
                foreach ($perguntas as $pergunta) {
                    $novaPergunta = Db::insert('perguntas', [
                        'categoria_id' => $novaCategoria,
                        'subcategoria_id' => $pergunta['subcategoria_id'] === null
                            ? null
                            : ($subcategoriasMap[(int) $pergunta['subcategoria_id']] ?? null),
                        'escola_id' => $pergunta['escola_id'],
                        'tipo' => $pergunta['tipo'],
                        'texto' => $pergunta['texto'],
                        'ordem' => $pergunta['ordem'],
                        'obrigatoria' => (int) $pergunta['obrigatoria'],
                        'peso' => $pergunta['peso'],
                        'imagem' => $pergunta['imagem'],
                    ] + $stamp);
                    $opcoes = Db::all(
                        'SELECT * FROM opcoes_resposta WHERE pergunta_id = ? AND deleted_at IS NULL ORDER BY ordem, id',
                        [$pergunta['id']]
                    );
                    foreach ($opcoes as $opcao) {
                        Db::insert('opcoes_resposta', [
                            'pergunta_id' => $novaPergunta,
                            'escola_id' => $opcao['escola_id'],
                            'descricao' => $opcao['descricao'],
                            'pontuacao' => $opcao['pontuacao'],
                            'ordem' => $opcao['ordem'],
                            'cor' => $opcao['cor'],
                            'emoji' => $opcao['emoji'],
                        ] + $stamp);
                    }
                }
            }

            $idsCategorias = array_keys($categoriasMap);
            $regras = Db::all(
                'SELECT * FROM regras_classificacao
                 WHERE deleted_at IS NULL AND (questionario_id = ? OR categoria_id IN (' . Db::in($idsCategorias) . '))',
                array_merge([$original['id']], $idsCategorias)
            );
            foreach ($regras as $regra) {
                Db::insert('regras_classificacao', [
                    'escola_id' => $regra['escola_id'],
                    'categoria_id' => $regra['categoria_id'] === null ? null : ($categoriasMap[(int) $regra['categoria_id']] ?? null),
                    'questionario_id' => (int) $regra['questionario_id'] === (int) $original['id']
                        ? $novoId
                        : $regra['questionario_id'],
                    'min_score' => $regra['min_score'],
                    'max_score' => $regra['max_score'],
                    'rotulo' => $regra['rotulo'],
                    'descricao' => $regra['descricao'],
                ] + $stamp);
            }
            return $novoId;
        });
    }

    /** Apaga respostas e resultados de uma aplicação (uso em demonstrações). */
    public static function limparAplicacao(int $aplicacaoId): array
    {
        return Db::transaction(static function () use ($aplicacaoId): array {
            $respostas = Db::run('DELETE FROM respostas WHERE aplicacao_id = ?', [$aplicacaoId])->rowCount();
            $resultados = Db::run('DELETE FROM resultados WHERE aplicacao_id = ?', [$aplicacaoId])->rowCount();
            return ['respostas' => $respostas, 'resultados' => $resultados];
        });
    }
}
