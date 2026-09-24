<?php

declare(strict_types=1);

namespace Vise\Install;

use Vise\Db;
use Vise\Security\Password;
use Vise\Services\CodigoSala;

/**
 * Dados de demonstração (idempotente). Não altera senhas de usuários que já existem.
 * Usuários: admin@vise.local, admin.escola@vise.local, pesquisador@vise.local,
 * professor@vise.local (senha "password"). Aluno: CPF 07593256189, nascimento 2009-10-21.
 */
final class Seed
{
    public static function demo(): void
    {
        Db::transaction(static function (): void {
            $admin = self::user('admin@vise.local', 'Administrador Vise', true);
            $escola = self::firstOrCreate('escolas', ['inep' => '00000001'], [
                'nome' => 'Escola Demo Vise',
                'municipio' => 'São Paulo',
                'uf' => 'SP',
                'responsavel' => 'Coordenação Demo',
                'ativo' => 1,
            ]);
            self::vinculo($admin, $escola, 'admin_escola');
            self::vinculo(self::user('admin.escola@vise.local', 'Admin da Escola Demo'), $escola, 'admin_escola');
            $pesquisador = self::user('pesquisador@vise.local', 'Pesquisadora Demo');
            self::vinculo($pesquisador, $escola, 'pesquisador');
            $professor = self::user('professor@vise.local', 'Professor Demo');
            self::vinculo($professor, $escola, 'professor');

            $serie = self::firstOrCreate('series', ['descricao' => '9º Ano'], []);
            $turma = self::firstOrCreate('turmas', ['escola_id' => $escola, 'nome' => '9º Ano A'], [
                'serie_id' => $serie,
                'turno' => 'matutino',
            ]);
            self::firstOrCreate('professor_turma', ['user_id' => $professor, 'turma_id' => $turma], []);

            self::firstOrCreate('alunos', ['cpf' => '07593256189', 'data_nascimento' => '2009-10-21'], [
                'nome' => 'Aluno Demo',
                'sexo' => 'outro',
                'matricula' => 'DEMO001',
                'turma_id' => $turma,
                'escola_id' => $escola,
                'telefone' => '11999990000',
                'responsavel' => 'Responsável Demo',
                'contato_responsavel' => '11988880000',
            ]);

            $questionario = self::firstOrCreate('questionarios', ['escola_id' => $escola, 'nome' => 'Bem-estar emocional - Demo'], [
                'pesquisador_id' => $pesquisador,
                'descricao' => 'Instrumento demonstrativo do MVP',
                'status' => 'publicado',
                'publico_alvo' => '9º ano',
                'versao' => 1,
                'compartilhado_na_escola' => 1,
            ]);
            $categoria = self::firstOrCreate('categorias', ['questionario_id' => $questionario, 'nome' => 'Como você tem se sentido?'], [
                'escola_id' => $escola,
                'ordem' => 1,
                'cor' => '#14B8A6',
                'mensagem_avatar' => 'Não existem respostas certas ou erradas. Responda com sinceridade.',
            ]);
            $perguntaMc = self::firstOrCreate('perguntas', [
                'categoria_id' => $categoria,
                'texto' => 'Com que frequência você se sentiu tranquilo nesta semana?',
            ], ['escola_id' => $escola, 'tipo' => 'multipla_escolha', 'ordem' => 1, 'obrigatoria' => 1, 'peso' => '1.00']);
            foreach ([['Nunca', 0, '😟'], ['Às vezes', 1, '😐'], ['Frequentemente', 2, '🙂']] as $i => [$descricao, $pontos, $emoji]) {
                self::firstOrCreate('opcoes_resposta', ['pergunta_id' => $perguntaMc, 'descricao' => $descricao], [
                    'escola_id' => $escola, 'pontuacao' => $pontos, 'ordem' => $i + 1, 'emoji' => $emoji,
                ]);
            }
            self::firstOrCreate('perguntas', [
                'categoria_id' => $categoria,
                'texto' => 'Conte algo que ajudou você a se sentir bem.',
            ], ['escola_id' => $escola, 'tipo' => 'texto', 'ordem' => 2, 'obrigatoria' => 0, 'peso' => '1.00']);
            self::aplicacao($questionario, $escola);

            $questionarioB = self::firstOrCreate('questionarios', ['escola_id' => $escola, 'nome' => 'Convívio escolar - Demo'], [
                'pesquisador_id' => $pesquisador,
                'descricao' => 'Segundo instrumento para testar múltiplos questionários',
                'status' => 'publicado',
                'publico_alvo' => '9º ano',
                'versao' => 1,
                'compartilhado_na_escola' => 0,
            ]);
            $categoriaB = self::firstOrCreate('categorias', ['questionario_id' => $questionarioB, 'nome' => 'Na escola'], [
                'escola_id' => $escola,
                'ordem' => 1,
                'cor' => '#38BDF8',
                'mensagem_avatar' => 'Pense no seu dia a dia na escola.',
            ]);
            $perguntaB = self::firstOrCreate('perguntas', [
                'categoria_id' => $categoriaB,
                'texto' => 'Você se sente acolhido(a) na sua turma?',
            ], ['escola_id' => $escola, 'tipo' => 'multipla_escolha', 'ordem' => 1, 'obrigatoria' => 1, 'peso' => '1.00']);
            foreach ([['Pouco', 0, '😕'], ['Mais ou menos', 1, '😐'], ['Bastante', 2, '😊']] as $i => [$descricao, $pontos, $emoji]) {
                self::firstOrCreate('opcoes_resposta', ['pergunta_id' => $perguntaB, 'descricao' => $descricao], [
                    'escola_id' => $escola, 'pontuacao' => $pontos, 'ordem' => $i + 1, 'emoji' => $emoji,
                ]);
            }
            self::aplicacao($questionarioB, $escola);
        });
    }

    private static function user(string $email, string $name, bool $superuser = false): int
    {
        $id = Db::value('SELECT id FROM users WHERE email = ?', [$email]);
        if ($id !== null) {
            return (int) $id;
        }
        $now = Db::now();
        return Db::insert('users', [
            'name' => $name,
            'email' => $email,
            'password' => Password::hash('password'),
            'is_superuser' => $superuser ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private static function vinculo(int $user, int $escola, string $role): void
    {
        self::firstOrCreate('escola_user', ['user_id' => $user, 'escola_id' => $escola, 'role' => $role], [
            'status' => 'ativo',
            'vinculado_em' => Db::now(),
        ]);
    }

    private static function aplicacao(int $questionario, int $escola): void
    {
        $existente = Db::one(
            "SELECT id, codigo_sala FROM aplicacoes_questionario WHERE questionario_id = ? AND escola_id = ? AND alvo_tipo = 'escola'",
            [$questionario, $escola]
        );
        if ($existente !== null) {
            if (empty($existente['codigo_sala'])) {
                Db::update('aplicacoes_questionario', ['codigo_sala' => CodigoSala::gerar()], (int) $existente['id']);
            }
            return;
        }
        $now = Db::now();
        Db::insert('aplicacoes_questionario', [
            'questionario_id' => $questionario,
            'escola_id' => $escola,
            'alvo_tipo' => 'escola',
            'status' => 'ativa',
            'codigo_sala' => CodigoSala::gerar(),
            'inicia_em' => gmdate('Y-m-d H:i:s', strtotime('-1 day')),
            'termina_em' => gmdate('Y-m-d H:i:s', strtotime('+365 days')),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param array<string, mixed> $where
     * @param array<string, mixed> $values
     */
    private static function firstOrCreate(string $table, array $where, array $values): int
    {
        $conds = implode(' AND ', array_map(static fn ($column) => "`$column` = ?", array_keys($where)));
        $id = Db::value("SELECT id FROM `$table` WHERE $conds LIMIT 1", array_values($where));
        if ($id !== null) {
            return (int) $id;
        }
        $now = Db::now();
        $stamp = $table === 'termos_aceite' ? [] : ['created_at' => $now, 'updated_at' => $now];
        return Db::insert($table, $where + $values + $stamp);
    }
}
