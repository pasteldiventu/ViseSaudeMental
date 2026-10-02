<?php

declare(strict_types=1);

/*
 * Testes de integração contra um MySQL real. APAGA as tabelas do banco informado.
 *
 *   TEST_DB_HOST=127.0.0.1 TEST_DB_PORT=3306 TEST_DB_NAME=vise_test \
 *   TEST_DB_USER=root TEST_DB_PASSWORD= php tests/run.php
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$env = [
    'DB_HOST' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
    'DB_PORT' => getenv('TEST_DB_PORT') ?: '3306',
    'DB_NAME' => getenv('TEST_DB_NAME') ?: 'vise_test',
    'DB_USER' => getenv('TEST_DB_USER') ?: 'root',
    'DB_PASSWORD' => getenv('TEST_DB_PASSWORD') ?: '',
    'SECRET_KEY' => 'segredo-dos-testes',
    'PUBLIC_APP_URL' => 'http://app.test',
    'CORS_ORIGINS' => '*',
    'APP_DEBUG' => 'true',
    'INSTALL_KEY' => '',
];
foreach ($env as $key => $value) {
    putenv("$key=$value");
}

require dirname(__DIR__) . '/src/bootstrap.php';

use Vise\Db;
use Vise\Install\Installer;
use Vise\Install\Seed;
use Vise\Security\AlunoTokens;
use Vise\Security\Password;
use Vise\Services\FiltrosRelatorio;
use Vise\Services\ImportacaoService;
use Vise\Services\ImportAlunosService;
use Vise\Services\Indicadores;
use Vise\Support\Planilha;
use Vise\Support\XlsxReader;
use Vise\Support\XlsxWriter;
use Vise\Support\Zip;
use Vise\Services\QuestionarioService;
use Vise\Services\ResultadoService;

// ---------------------------------------------------------------- infraestrutura

final class T
{
    public static int $ok = 0;
    /** @var list<string> */
    public static array $falhas = [];
    public static string $atual = '';
    public static string $base = '';
    public static string $cookies = '';

    public static function check(bool $cond, string $msg): void
    {
        if ($cond) {
            self::$ok++;
            return;
        }
        self::$falhas[] = self::$atual . ': ' . $msg;
        echo "  FALHOU: $msg\n";
    }

    public static function eq(mixed $esperado, mixed $obtido, string $msg): void
    {
        self::check($esperado === $obtido, $msg . ' (esperado ' . json_encode($esperado) . ', obtido ' . json_encode($obtido) . ')');
    }

    /**
     * @param array<string, string> $headers
     * @return array{status: int, body: string, json: mixed, headers: array<string, string>}
     */
    public static function http(string $method, string $path, mixed $json = null, array $headers = [], ?array $form = null): array
    {
        $ch = curl_init(self::$base . $path);
        $hdr = [];
        foreach ($headers as $name => $value) {
            $hdr[] = "$name: $value";
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_COOKIEFILE => self::$cookies,
            CURLOPT_COOKIEJAR => self::$cookies,
        ]);
        if ($json !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($json) ? $json : json_encode($json));
            $hdr[] = 'Content-Type: application/json';
        } elseif ($form !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $form);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $hdr);
        $raw = curl_exec($ch);
        if ($raw === false) {
            throw new RuntimeException('HTTP falhou: ' . curl_error($ch));
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $headersOut = [];
        foreach (explode("\r\n", substr($raw, 0, $headerSize)) as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headersOut[strtolower(trim($name))] = trim($value);
            }
        }
        $body = substr($raw, $headerSize);
        return ['status' => $status, 'body' => $body, 'json' => json_decode($body, true), 'headers' => $headersOut];
    }

    public static function limparCookies(): void
    {
        file_put_contents(self::$cookies, '');
    }

    public static function csrf(string $html): string
    {
        preg_match('/name="_csrf" value="([^"]+)"/', $html, $m);
        return $m[1] ?? '';
    }
}

function resetarBanco(): void
{
    Db::run('SET FOREIGN_KEY_CHECKS = 0');
    foreach (Db::column('SHOW TABLES') as $tabela) {
        Db::run("DROP TABLE `$tabela`");
    }
    Db::run('SET FOREIGN_KEY_CHECKS = 1');
    Installer::migrate();
}

/** Equivalente ao _seed_minimo dos testes Python. */
function seedMinimo(): array
{
    $now = Db::now();
    $ts = ['created_at' => $now, 'updated_at' => $now];
    $user = Db::insert('users', ['name' => 'Admin', 'email' => 'admin@test.local', 'password' => Password::hash('password'), 'is_superuser' => 1] + $ts);
    $escola = Db::insert('escolas', ['nome' => 'E', 'municipio' => 'SP', 'uf' => 'SP', 'inep' => '1', 'ativo' => 1] + $ts);
    $serie = Db::insert('series', ['descricao' => '9º'] + $ts);
    $turma = Db::insert('turmas', ['nome' => 'A', 'serie_id' => $serie, 'turno' => 'matutino', 'escola_id' => $escola] + $ts);
    $aluno = Db::insert('alunos', [
        'nome' => 'Ana', 'cpf' => '07593256189', 'data_nascimento' => '2009-10-21', 'escola_id' => $escola,
        'turma_id' => $turma, 'matricula' => 'T001', 'sexo' => 'outro', 'telefone' => '11999990000',
        'responsavel' => 'Responsável', 'contato_responsavel' => '11988880000',
    ] + $ts);
    $q = Db::insert('questionarios', ['escola_id' => $escola, 'pesquisador_id' => $user, 'nome' => 'Q', 'status' => 'publicado', 'versao' => 1, 'compartilhado_na_escola' => 1] + $ts);
    $cat = Db::insert('categorias', ['questionario_id' => $q, 'escola_id' => $escola, 'nome' => 'Cat', 'ordem' => 1] + $ts);
    $pergunta = Db::insert('perguntas', ['categoria_id' => $cat, 'escola_id' => $escola, 'tipo' => 'multipla_escolha', 'texto' => 'P?', 'ordem' => 1, 'obrigatoria' => 1, 'peso' => '1'] + $ts);
    $opcao = Db::insert('opcoes_resposta', ['pergunta_id' => $pergunta, 'escola_id' => $escola, 'descricao' => 'Sim', 'pontuacao' => 2, 'ordem' => 1] + $ts);
    Db::insert('regras_classificacao', ['escola_id' => $escola, 'categoria_id' => $cat, 'min_score' => '0', 'max_score' => '10', 'rotulo' => 'verde'] + $ts);
    $aplicacao = Db::insert('aplicacoes_questionario', ['questionario_id' => $q, 'escola_id' => $escola, 'alvo_tipo' => 'escola', 'status' => 'ativa'] + $ts);
    return compact('user', 'escola', 'serie', 'turma', 'aluno', 'q', 'cat', 'pergunta', 'opcao', 'aplicacao');
}

/** @var array<string, callable> $testes */
$testes = [];

// ---------------------------------------------------------------- API do aluno

$testes['login aluno com CPF mascarado'] = function (): void {
    seedMinimo();
    $r = T::http('POST', '/api/v1/login', ['cpf' => '075.932.561-89', 'data_nascimento' => '2009-10-21']);
    T::eq(200, $r['status'], 'status');
    T::check(isset($r['json']['token']), 'retorna token');
    T::eq('Ana', $r['json']['aluno']['nome'] ?? null, 'nome do aluno');

    $me = T::http('GET', '/api/v1/me', null, ['Authorization' => 'Bearer ' . $r['json']['token']]);
    T::eq(200, $me['status'], '/me com Authorization');
    $me2 = T::http('GET', '/api/v1/me', null, ['X-Auth-Token' => $r['json']['token']]);
    T::eq(200, $me2['status'], '/me com X-Auth-Token');

    $novo = T::http('POST', '/api/v1/login', ['cpf' => '07593256189', 'data_nascimento' => '2009-10-21']);
    $antigo = T::http('GET', '/api/v1/me', null, ['Authorization' => 'Bearer ' . $r['json']['token']]);
    T::eq(401, $antigo['status'], 'novo login invalida token anterior');

    $out = T::http('POST', '/api/v1/logout', null, ['Authorization' => 'Bearer ' . $novo['json']['token']]);
    T::eq(['status' => 'ok'], $out['json'], 'logout');
    T::eq(401, T::http('GET', '/api/v1/me', null, ['Authorization' => 'Bearer ' . $novo['json']['token']])['status'], 'token revogado');
};

$testes['login aluno com data errada/CPF inválido'] = function (): void {
    seedMinimo();
    $r = T::http('POST', '/api/v1/login', ['cpf' => '07593256189', 'data_nascimento' => '2009-10-22']);
    T::eq(422, $r['status'], 'data errada');
    T::eq('Credenciais inválidas.', $r['json']['detail'] ?? null, 'mensagem');
    T::eq(422, T::http('POST', '/api/v1/login', ['cpf' => '123', 'data_nascimento' => '2009-10-21'])['status'], 'CPF curto');
    T::eq(422, T::http('POST', '/api/v1/login', '{json quebrado')['status'], 'JSON inválido');
    $semToken = T::http('GET', '/api/v1/me');
    T::eq(401, $semToken['status'], 'sem token');
    T::eq('Bearer', $semToken['headers']['www-authenticate'] ?? null, 'WWW-Authenticate');
};

$testes['fluxo completo do aluno'] = function (): void {
    $s = seedMinimo();
    $auth = ['Authorization' => 'Bearer ' . AlunoTokens::create($s['aluno'])];

    $lista = T::http('GET', '/api/v1/aplicacoes', null, $auth);
    T::eq(200, $lista['status'], 'lista aplicações');
    T::eq($s['aplicacao'], $lista['json']['data'][0]['id'] ?? null, 'aplicação listada');
    T::eq(false, $lista['json']['data'][0]['concluido'] ?? null, 'não concluída');

    $cats = T::http('GET', "/api/v1/aplicacoes/{$s['aplicacao']}/categorias", null, $auth);
    T::eq(200, $cats['status'], 'categorias');
    T::eq('Cat', $cats['json']['data'][0]['nome'] ?? null, 'nome categoria');

    $perg = T::http('GET', "/api/v1/aplicacoes/{$s['aplicacao']}/categorias/{$s['cat']}/perguntas", null, $auth);
    T::eq(200, $perg['status'], 'perguntas');
    $item = $perg['json']['data'][0] ?? [];
    T::eq(true, $item['obrigatoria'] ?? null, 'obrigatoria é bool');
    T::check(($item['opcoes'] ?? []) !== [], 'tem opções');
    foreach ($item['opcoes'] ?? [] as $opcao) {
        T::check(!array_key_exists('pontuacao', $opcao), 'opção sem pontuacao');
    }

    $fin = T::http('POST', "/api/v1/aplicacoes/{$s['aplicacao']}/finalizar", null, $auth);
    T::eq(422, $fin['status'], 'finalizar sem responder');

    $salvar = T::http('POST', "/api/v1/aplicacoes/{$s['aplicacao']}/respostas", [
        'pergunta_id' => $s['pergunta'], 'opcao_id' => $s['opcao'], 'responded_at' => '2026-01-10T12:00:00Z',
    ], $auth);
    T::eq(200, $salvar['status'], 'salvar resposta: ' . $salvar['body']);
    T::eq('salvo', $salvar['json']['status'] ?? null, 'status salvo');

    $lote = T::http('POST', "/api/v1/aplicacoes/{$s['aplicacao']}/respostas/lote", ['respostas' => [
        ['pergunta_id' => $s['pergunta'], 'opcao_id' => $s['opcao'], 'responded_at' => '2026-01-09T12:00:00Z', 'client_uuid' => '6f1c1c9e-6d2a-4b7e-9d1a-000000000001'],
    ]], $auth);
    T::eq(200, $lote['status'], 'lote: ' . $lote['body']);
    T::eq(['status' => 'sincronizado', 'quantidade' => 1], $lote['json'], 'retorno do lote');
    T::eq(1, (int) Db::value('SELECT COUNT(*) FROM respostas'), 'resposta não duplicada');
    T::eq('2026-01-10 12:00:00', Db::value('SELECT responded_at FROM respostas'), 'mantém resposta mais recente');

    $invalida = T::http('POST', "/api/v1/aplicacoes/{$s['aplicacao']}/respostas", ['pergunta_id' => 999999, 'opcao_id' => $s['opcao']], $auth);
    T::check(in_array($invalida['status'], [404, 422], true), 'pergunta de outra aplicação rejeitada');

    $fin = T::http('POST', "/api/v1/aplicacoes/{$s['aplicacao']}/finalizar", null, $auth);
    T::eq(200, $fin['status'], 'finalizar: ' . $fin['body']);
    T::eq('concluido', $fin['json']['status'] ?? null, 'status concluido');
    $resultado = Db::one('SELECT * FROM resultados');
    T::check($resultado !== null, 'resultado gravado');
    $totais = json_decode((string) ($resultado['totais_json'] ?? '{}'), true);
    T::eq(2.0, (float) ($totais[(string) $s['cat']] ?? -1), 'total da categoria');
    T::check(str_contains((string) ($resultado['classificacao_json'] ?? ''), 'verde'), 'classificação verde');

    $lista = T::http('GET', '/api/v1/aplicacoes', null, $auth);
    T::eq(true, $lista['json']['data'][0]['concluido'] ?? null, 'concluída após finalizar');

    $avatar = T::http('GET', '/api/v1/avatar', null, $auth);
    T::eq(200, $avatar['status'], 'avatar');
    T::check(array_key_exists('data', (array) $avatar['json']), 'avatar tem data');

    $termo = T::http('POST', '/api/v1/termos', ['versao' => '1.0', 'texto_hash' => str_repeat('a', 64)], $auth);
    T::eq(201, $termo['status'], 'aceitar termo: ' . $termo['body']);
    T::eq(201, T::http('POST', '/api/v1/termos', ['versao' => '1.0', 'texto_hash' => str_repeat('b', 64)], $auth)['status'], 'termo idempotente');
    T::eq(1, (int) Db::value('SELECT COUNT(*) FROM termos_aceite'), 'um aceite por versão');
    T::eq(422, T::http('POST', '/api/v1/termos', ['versao' => '1.0', 'texto_hash' => 'curto'], $auth)['status'], 'hash inválido');
};

$testes['aluno não acessa aplicação de outra escola'] = function (): void {
    $s = seedMinimo();
    $now = Db::now();
    $outra = Db::insert('escolas', ['nome' => 'Outra', 'municipio' => 'X', 'uf' => 'MT', 'ativo' => 1, 'created_at' => $now, 'updated_at' => $now]);
    $apl = Db::insert('aplicacoes_questionario', ['questionario_id' => $s['q'], 'escola_id' => $outra, 'alvo_tipo' => 'escola', 'status' => 'ativa', 'created_at' => $now, 'updated_at' => $now]);
    $auth = ['Authorization' => 'Bearer ' . AlunoTokens::create($s['aluno'])];
    T::eq(404, T::http('GET', "/api/v1/aplicacoes/$apl/categorias", null, $auth)['status'], 'categorias 404');
    T::eq(1, count(T::http('GET', '/api/v1/aplicacoes', null, $auth)['json']['data'] ?? []), 'lista só a própria');
};

$testes['resultado via serviço'] = function (): void {
    $s = seedMinimo();
    Db::insert('respostas', ['aplicacao_id' => $s['aplicacao'], 'aluno_id' => $s['aluno'], 'pergunta_id' => $s['pergunta'], 'opcao_id' => $s['opcao'], 'responded_at' => Db::now(), 'created_at' => Db::now(), 'updated_at' => Db::now()]);
    $r = ResultadoService::calcular($s['aplicacao'], $s['aluno']);
    T::check(array_key_exists((string) $s['cat'], $r['totais'] ?? $r['totais_json'] ?? $r), 'categoria presente nos totais: ' . json_encode($r));
};

// ---------------------------------------------------------------- API da equipe

$testes['staff login e cria sala'] = function (): void {
    $s = seedMinimo();
    $login = T::http('POST', '/api/v1/staff/login', ['email' => 'ADMIN@test.local', 'password' => 'password']);
    T::eq(200, $login['status'], 'login: ' . $login['body']);
    $h = ['Authorization' => 'Bearer ' . ($login['json']['token'] ?? '')];

    T::eq(200, T::http('GET', '/api/v1/staff/me', null, $h)['status'], 'staff/me');
    $qs = T::http('GET', '/api/v1/staff/questionarios', null, $h);
    T::eq(200, $qs['status'], 'questionarios');
    T::check(count((array) $qs['json']) >= 1, 'ao menos um questionário');
    $turmas = T::http('GET', '/api/v1/staff/turmas', null, $h);
    T::eq(200, $turmas['status'], 'turmas');
    T::check(count((array) $turmas['json']) >= 1, 'ao menos uma turma');

    $sala = T::http('POST', '/api/v1/staff/salas', [
        'questionario_id' => $qs['json'][0]['id'], 'escola_id' => $s['escola'], 'alvo_tipo' => 'turma', 'turma_id' => $turmas['json'][0]['id'],
    ], $h);
    T::eq(201, $sala['status'], 'criar sala: ' . $sala['body']);
    $codigo = $sala['json']['codigo'] ?? '';
    T::check($codigo !== '', 'código gerado');
    T::check(str_contains((string) ($sala['json']['link'] ?? ''), 'http://app.test/?sala='), 'link da sala');

    $publica = T::http('GET', '/api/v1/salas/' . strtolower($codigo));
    T::eq(200, $publica['status'], 'sala pública');
    T::eq($codigo, $publica['json']['codigo'] ?? null, 'código da sala pública');

    $alunoAuth = ['Authorization' => 'Bearer ' . AlunoTokens::create($s['aluno'])];
    $entrar = T::http('POST', '/api/v1/aplicacoes/entrar-com-codigo', ['codigo' => $codigo], $alunoAuth);
    T::eq(200, $entrar['status'], 'entrar com código: ' . $entrar['body']);
    T::eq($sala['json']['id'] ?? null, $entrar['json']['aplicacao_id'] ?? null, 'aplicacao_id');

    $salas = T::http('GET', '/api/v1/staff/salas', null, $h);
    T::eq(200, $salas['status'], 'listar salas');
    $enc = T::http('POST', "/api/v1/staff/salas/{$sala['json']['id']}/encerrar", null, $h);
    T::eq(200, $enc['status'], 'encerrar: ' . $enc['body']);
    T::eq(404, T::http('POST', '/api/v1/aplicacoes/entrar-com-codigo', ['codigo' => $codigo], $alunoAuth)['status'], 'sala encerrada');

    T::eq(401, T::http('GET', '/api/v1/staff/me', null, $alunoAuth)['status'], 'token de aluno não vale como staff');
};

$testes['staff login inválido'] = function (): void {
    seedMinimo();
    $r = T::http('POST', '/api/v1/staff/login', ['email' => 'admin@test.local', 'password' => 'errada']);
    T::eq(422, $r['status'], 'senha errada');
    T::eq('E-mail ou senha inválidos.', $r['json']['detail'] ?? null, 'mensagem');
};

$testes['compatibilidade com hash bcrypt do Python ($2b$)'] = function (): void {
    seedMinimo();
    $hash = str_replace('$2y$', '$2b$', password_hash('senhaPython', PASSWORD_BCRYPT));
    Db::run('UPDATE users SET password = ? WHERE email = ?', [$hash, 'admin@test.local']);
    T::eq(200, T::http('POST', '/api/v1/staff/login', ['email' => 'admin@test.local', 'password' => 'senhaPython'])['status'], 'login com $2b$');
};

$testes['CORS e rotas utilitárias'] = function (): void {
    $pre = T::http('OPTIONS', '/api/v1/login', null, ['Origin' => 'http://x.test', 'Access-Control-Request-Method' => 'POST']);
    T::eq(204, $pre['status'], 'preflight');
    T::eq('*', $pre['headers']['access-control-allow-origin'] ?? null, 'allow-origin');
    T::eq(200, T::http('GET', '/up')['status'], '/up');
    T::eq(404, T::http('GET', '/api/v1/nao-existe')['status'], '404 JSON');
    T::eq(200, T::http('GET', '/index.php/up')['status'], 'fallback /index.php/...');
    T::eq(404, T::http('GET', '/install')['status'], 'instalador desativado sem INSTALL_KEY');
    T::eq(200, T::http('GET', '/static/admin.css')['status'], 'arquivo estático');
};

function staffAuth(string $email): array
{
    $r = T::http('POST', '/api/v1/staff/login', ['email' => $email, 'password' => 'password']);
    return ['Authorization' => 'Bearer ' . ($r['json']['token'] ?? '')];
}

$testes['cadastros pelo app: pesquisador monta questionário e libera sala'] = function (): void {
    Seed::demo();
    $h = staffAuth('pesquisador@vise.local');
    $escola = (int) Db::value("SELECT id FROM escolas WHERE inep = '00000001'");

    $menu = T::http('GET', '/api/v1/staff/cadastros', null, $h);
    T::eq(200, $menu['status'], 'menu');
    $keys = array_column($menu['json']['data'] ?? [], 'key');
    T::check(in_array('questionarios', $keys, true) && in_array('opcoes', $keys, true), 'instrumentos no menu');
    T::check(!in_array('escolas', $keys, true) && !in_array('usuarios', $keys, true), 'sem escolas/usuários');
    $metaQ = array_values(array_filter($menu['json']['data'], static fn ($m) => $m['key'] === 'questionarios'))[0] ?? [];
    T::eq(true, $metaQ['pode_criar'] ?? null, 'pode criar questionário');
    T::check(in_array('publicar', array_column($metaQ['acoes'] ?? [], 'nome'), true), 'ação publicar disponível');

    $form = T::http('GET', '/api/v1/staff/cadastros/questionarios/formulario', null, $h);
    T::eq(200, $form['status'], 'formulário');
    $campos = array_column($form['json']['campos'] ?? [], null, 'name');
    T::check(!isset($campos['pesquisador_id']), 'pesquisador não escolhe o dono');
    T::eq((string) $escola, $campos['escola_id']['opcoes'][0]['valor'] ?? null, 'opções de escola no escopo');
    T::eq('rascunho', $campos['status']['valor'] ?? null, 'valor padrão do status');

    $q = T::http('POST', '/api/v1/staff/cadastros/questionarios', ['nome' => 'Ansiedade - App', 'escola_id' => $escola, 'status' => 'rascunho', 'versao' => 1, 'compartilhado_na_escola' => false], $h);
    T::eq(201, $q['status'], 'cria questionário: ' . $q['body']);
    $qid = (int) ($q['json']['id'] ?? 0);
    T::eq((int) Db::value("SELECT id FROM users WHERE email = 'pesquisador@vise.local'"), (int) Db::value('SELECT pesquisador_id FROM questionarios WHERE id = ?', [$qid]), 'dono é o pesquisador');

    $semPerguntas = T::http('POST', '/api/v1/staff/cadastros/questionarios', ['nome' => 'Sem perguntas', 'escola_id' => $escola, 'status' => 'rascunho', 'versao' => 1, 'compartilhado_na_escola' => false], $h);
    $vazioId = (int) ($semPerguntas['json']['id'] ?? 0);
    T::http('POST', '/api/v1/staff/cadastros/questionarios/acoes/publicar', ['ids' => [$vazioId]], $h);
    $salaVazia = T::http('POST', '/api/v1/staff/salas', ['questionario_id' => $vazioId, 'escola_id' => $escola, 'alvo_tipo' => 'escola'], $h);
    T::eq(422, $salaVazia['status'], 'sala exige perguntas: ' . $salaVazia['body']);
    T::check(str_contains((string) ($salaVazia['json']['detail'] ?? ''), 'ainda não tem perguntas'), 'mensagem de sala sem perguntas');

    $vazio = T::http('POST', '/api/v1/staff/cadastros/categorias', ['questionario_id' => $qid, 'nome' => ''], $h);
    T::eq(422, $vazio['status'], 'validação');
    T::eq('Preencha o campo Nome.', $vazio['json']['detail'] ?? null, 'mensagem de validação');

    $prefill = T::http('GET', "/api/v1/staff/cadastros/categorias/formulario?questionario_id=$qid", null, $h);
    T::eq((string) $qid, array_column($prefill['json']['campos'] ?? [], null, 'name')['questionario_id']['valor'] ?? null, 'formulário pré-preenchido');

    $cat = T::http('POST', '/api/v1/staff/cadastros/categorias', ['questionario_id' => $qid, 'nome' => 'Preocupações', 'ordem' => 1, 'cor' => '#38BDF8'], $h);
    T::eq(201, $cat['status'], 'cria categoria: ' . $cat['body']);
    $cid = (int) $cat['json']['id'];
    T::eq($escola, (int) Db::value('SELECT escola_id FROM categorias WHERE id = ?', [$cid]), 'escola derivada');
    $perg = T::http('POST', '/api/v1/staff/cadastros/perguntas', ['categoria_id' => $cid, 'tipo' => 'multipla_escolha', 'texto' => 'Você se preocupa com facilidade?', 'ordem' => 1, 'obrigatoria' => true, 'peso' => '1'], $h);
    T::eq(201, $perg['status'], 'cria pergunta: ' . $perg['body']);
    $pid = (int) $perg['json']['id'];
    foreach ([['Nunca', 0], ['Às vezes', 1], ['Sempre', 2]] as $i => [$descricao, $pontos]) {
        $op = T::http('POST', '/api/v1/staff/cadastros/opcoes', ['pergunta_id' => $pid, 'descricao' => $descricao, 'pontuacao' => $pontos, 'ordem' => $i + 1], $h);
        T::eq(201, $op['status'], "cria opção $descricao");
    }
    $regra = T::http('POST', '/api/v1/staff/cadastros/regras', ['categoria_id' => $cid, 'min_score' => '0', 'max_score' => '1', 'rotulo' => 'Baixo'], $h);
    T::eq(201, $regra['status'], 'cria regra: ' . $regra['body']);
    T::eq(422, T::http('POST', '/api/v1/staff/cadastros/regras', ['categoria_id' => $cid, 'min_score' => '5', 'max_score' => '1', 'rotulo' => 'X'], $h)['status'], 'regra inválida');

    $edit = T::http('POST', "/api/v1/staff/cadastros/categorias/$cid", ['questionario_id' => $qid, 'nome' => 'Preocupações do dia a dia', 'ordem' => 1], $h);
    T::eq(200, $edit['status'], 'edita categoria: ' . $edit['body']);
    T::eq('Preocupações do dia a dia', Db::value('SELECT nome FROM categorias WHERE id = ?', [$cid]), 'nome editado');

    $det = T::http('GET', "/api/v1/staff/cadastros/questionarios/$qid", null, $h);
    T::eq(200, $det['status'], 'detalhe');
    T::eq(true, $det['json']['pode_editar'] ?? null, 'pode editar');
    $rel = array_column($det['json']['relacionados'] ?? [], null, 'key');
    T::eq(1, $rel['categorias']['count'] ?? null, 'relacionados: categorias');
    T::eq(true, $rel['categorias']['can_add'] ?? null, 'pode adicionar categoria');

    $lista = T::http('GET', "/api/v1/staff/cadastros/perguntas?categoria_id=$cid", null, $h);
    T::eq(1, $lista['json']['total'] ?? null, 'lista filtrada');
    T::eq('Categoria', $lista['json']['filtros'][0]['label'] ?? null, 'filtro informado');

    $pub = T::http('POST', '/api/v1/staff/cadastros/questionarios/acoes/publicar', ['ids' => [$qid]], $h);
    T::eq(200, $pub['status'], 'publicar: ' . $pub['body']);
    T::eq('publicado', Db::value('SELECT status FROM questionarios WHERE id = ?', [$qid]), 'publicado');

    $sala = T::http('POST', '/api/v1/staff/salas', ['questionario_id' => $qid, 'escola_id' => $escola, 'alvo_tipo' => 'escola'], $h);
    T::eq(201, $sala['status'], 'cria sala: ' . $sala['body']);
    $login = T::http('POST', '/api/v1/login', ['cpf' => '07593256189', 'data_nascimento' => '2009-10-21']);
    $alunoH = ['Authorization' => 'Bearer ' . $login['json']['token']];
    $entrar = T::http('POST', '/api/v1/aplicacoes/entrar-com-codigo', ['codigo' => $sala['json']['codigo']], $alunoH);
    T::eq(200, $entrar['status'], 'aluno entra na sala');
    $perguntas = T::http('GET', "/api/v1/aplicacoes/{$entrar['json']['aplicacao_id']}/categorias/$cid/perguntas", null, $alunoH);
    T::eq(3, count($perguntas['json']['data'][0]['opcoes'] ?? []), 'aluno vê a pergunta criada no app');

    $opcao = (int) Db::value('SELECT id FROM opcoes_resposta WHERE pergunta_id = ? ORDER BY id DESC LIMIT 1', [$pid]);
    T::eq(200, T::http('POST', "/api/v1/staff/cadastros/opcoes/$opcao/excluir", null, $h)['status'], 'exclui opção');

    $now = Db::now();
    $outra = Db::insert('escolas', ['nome' => 'Outra', 'municipio' => 'X', 'uf' => 'MT', 'ativo' => 1, 'created_at' => $now, 'updated_at' => $now]);
    $alheio = Db::insert('questionarios', ['escola_id' => $outra, 'pesquisador_id' => (int) Db::value('SELECT id FROM users LIMIT 1'), 'nome' => 'Alheio', 'status' => 'publicado', 'versao' => 1, 'compartilhado_na_escola' => 1, 'created_at' => $now, 'updated_at' => $now]);
    T::eq(404, T::http('GET', "/api/v1/staff/cadastros/questionarios/$alheio", null, $h)['status'], 'questionário de outra escola invisível');
    T::eq(422, T::http('POST', '/api/v1/staff/cadastros/categorias', ['questionario_id' => $alheio, 'nome' => 'Invasão', 'ordem' => 1], $h)['status'], 'não cria categoria em questionário alheio');
    T::eq(401, T::http('GET', '/api/v1/staff/cadastros', null, $alunoH)['status'], 'token de aluno recusado');
};

$testes['cadastros pelo app: admin da escola e professor'] = function (): void {
    Seed::demo();
    $escola = (int) Db::value("SELECT id FROM escolas WHERE inep = '00000001'");
    $serie = (int) Db::value('SELECT id FROM series LIMIT 1');
    $h = staffAuth('admin.escola@vise.local');
    $turma = T::http('POST', '/api/v1/staff/cadastros/turmas', ['nome' => '8º Ano B', 'escola_id' => $escola, 'serie_id' => $serie, 'turno' => 'vespertino'], $h);
    T::eq(201, $turma['status'], 'cria turma: ' . $turma['body']);
    $aluno = T::http('POST', '/api/v1/staff/cadastros/alunos', [
        'nome' => 'Carla App', 'cpf' => '529.982.247-25', 'data_nascimento' => '2011-03-04', 'escola_id' => $escola, 'turma_id' => $turma['json']['id'],
    ], $h);
    T::eq(201, $aluno['status'], 'cria aluno: ' . $aluno['body']);
    T::eq(200, T::http('POST', '/api/v1/login', ['cpf' => '52998224725', 'data_nascimento' => '2011-03-04'])['status'], 'aluno criado pelo app consegue entrar');
    $busca = T::http('GET', '/api/v1/staff/cadastros/alunos?q=529.982', null, $h);
    T::eq(1, $busca['json']['total'] ?? null, 'busca por CPF');
    T::eq('529.982.247-25', $busca['json']['data'][0]['colunas'][1] ?? null, 'CPF formatado na lista');
    T::eq(403, T::http('POST', '/api/v1/staff/cadastros/questionarios', ['nome' => 'X', 'escola_id' => $escola, 'status' => 'rascunho', 'versao' => 1], $h)['status'], 'admin escola só lê questionários');

    $p = staffAuth('professor@vise.local');
    $menu = T::http('GET', '/api/v1/staff/cadastros', null, $p);
    $keys = array_column($menu['json']['data'] ?? [], 'key');
    T::check(in_array('resultados', $keys, true) && !in_array('questionarios', $keys, true), 'menu do professor');
    T::eq(false, array_column($menu['json']['data'], null, 'key')['alunos']['pode_criar'] ?? null, 'professor só lê alunos');
    T::eq(403, T::http('POST', '/api/v1/staff/cadastros/alunos', ['nome' => 'X'], $p)['status'], 'professor não cria aluno');
    T::eq(403, T::http('GET', '/api/v1/staff/cadastros/usuarios', null, $p)['status'], 'professor sem usuários');
};

// ---------------------------------------------------------------- serviços

$testes['publicar só rascunho'] = function (): void {
    $s = seedMinimo();
    Db::run("UPDATE questionarios SET status = 'rascunho' WHERE id = ?", [$s['q']]);
    QuestionarioService::publicar($s['q']);
    T::eq('publicado', Db::value('SELECT status FROM questionarios WHERE id = ?', [$s['q']]), 'publicado');
    try {
        QuestionarioService::publicar($s['q']);
        T::check(false, 'deveria rejeitar publicar de novo');
    } catch (Throwable $e) {
        T::check(true, 'rejeita');
    }
};

$testes['nova versão copia opções'] = function (): void {
    $s = seedMinimo();
    $novo = QuestionarioService::criarNovaVersao($s['q']);
    $q = Db::one('SELECT * FROM questionarios WHERE id = ?', [$novo]);
    T::eq(2, (int) $q['versao'], 'versão');
    T::eq('rascunho', $q['status'], 'status');
    T::eq($s['q'], (int) $q['parent_id'], 'parent_id');
    $cats = Db::column('SELECT id FROM categorias WHERE questionario_id = ?', [$novo]);
    T::eq(1, count($cats), 'categorias');
    $pergs = Db::column('SELECT id FROM perguntas WHERE categoria_id = ?', [(int) $cats[0]]);
    T::eq(1, count($pergs), 'perguntas');
    T::eq(1, (int) Db::value('SELECT COUNT(*) FROM opcoes_resposta WHERE pergunta_id = ?', [(int) $pergs[0]]), 'opções');
    T::eq(1, (int) Db::value('SELECT COUNT(*) FROM regras_classificacao WHERE categoria_id = ?', [(int) $cats[0]]), 'regras');
};

$csv = "escola,nome,cpf,data_nascimento,sexo,matricula,turma_nome,responsavel,contato_responsavel\n"
    . "1,Bruno,52998224725,2010-05-01,outro,M2,A,Resp,119\n";

$testes['importar CSV com coluna escola'] = function () use ($csv): void {
    seedMinimo();
    $r = ImportAlunosService::importar($csv);
    T::eq(1, $r['success'], 'sucesso');
    T::eq([], $r['errors'], 'sem erros');
    T::eq('Bruno', Db::value("SELECT nome FROM alunos WHERE cpf = '52998224725'"), 'aluno criado');
    $r = ImportAlunosService::importar("\xEF\xBB\xBF" . str_replace([',', 'Bruno', '2010-05-01'], [';', 'Bruno Silva', '01/05/2010'], $csv));
    T::eq(1, $r['success'], 'reimportação com ; BOM e data BR');
    T::eq('Bruno Silva', Db::value("SELECT nome FROM alunos WHERE cpf = '52998224725'"), 'atualizou');
    T::eq(1, (int) Db::value("SELECT COUNT(*) FROM alunos WHERE cpf = '52998224725'"), 'sem duplicar');
};

$testes['importar CSV fora do escopo'] = function () use ($csv): void {
    seedMinimo();
    $r = ImportAlunosService::importar($csv, [999]);
    T::eq(0, $r['success'], 'nenhum importado');
    T::check($r['errors'] !== [], 'erro informado');
};

$testes['limpar aplicação remove respostas e resultados'] = function (): void {
    $s = seedMinimo();
    Db::insert('respostas', ['aplicacao_id' => $s['aplicacao'], 'aluno_id' => $s['aluno'], 'pergunta_id' => $s['pergunta'], 'opcao_id' => $s['opcao'], 'responded_at' => Db::now(), 'created_at' => Db::now(), 'updated_at' => Db::now()]);
    ResultadoService::calcular($s['aplicacao'], $s['aluno']);
    QuestionarioService::limparAplicacao($s['aplicacao']);
    T::eq(0, (int) Db::value('SELECT COUNT(*) FROM respostas WHERE aplicacao_id = ?', [$s['aplicacao']]), 'respostas');
    T::eq(0, (int) Db::value('SELECT COUNT(*) FROM resultados WHERE aplicacao_id = ?', [$s['aplicacao']]), 'resultados');
};

// ---------------------------------------------------------------- painel

function adminLogin(string $email, string $senha = 'password'): int
{
    T::limparCookies();
    $form = T::http('GET', '/admin/login');
    $r = T::http('POST', '/admin/login', null, [], ['_csrf' => T::csrf($form['body']), 'email' => $email, 'password' => $senha]);
    return $r['status'];
}

$testes['painel: todas as páginas por perfil'] = function (): void {
    Seed::demo();
    T::eq(302, T::http('GET', '/admin')['status'], 'sem login redireciona');
    T::eq(419, T::http('POST', '/admin/login', null, [], ['email' => 'admin@vise.local', 'password' => 'password'])['status'], 'login sem CSRF');
    T::eq(422, adminLogin('admin@vise.local', 'errada'), 'senha errada volta ao formulário');

    foreach (['admin@vise.local', 'admin.escola@vise.local', 'pesquisador@vise.local', 'professor@vise.local'] as $email) {
        T::eq(302, adminLogin($email), "login $email");
        $home = T::http('GET', '/admin');
        T::eq(200, $home['status'], "dashboard $email");
        foreach (array_keys(\Vise\Admin\Resources::all()) as $key) {
            $idx = T::http('GET', "/admin/$key");
            T::check(in_array($idx['status'], [200, 403], true), "$email /admin/$key -> {$idx['status']} " . substr(strip_tags($idx['body']), 0, 200));
            if ($idx['status'] !== 200) {
                continue;
            }
            $busca = T::http('GET', "/admin/$key?q=demo&sort=id&dir=desc&page=1");
            T::eq(200, $busca['status'], "$email busca $key");
            $novo = T::http('GET', "/admin/$key/create");
            T::check(in_array($novo['status'], [200, 403], true), "$email /admin/$key/create -> {$novo['status']}");
            if (preg_match('#/admin/' . preg_quote($key, '#') . '/(\d+)"#', $idx['body'], $m)) {
                $show = T::http('GET', "/admin/$key/{$m[1]}");
                T::eq(200, $show['status'], "$email detalhe $key/{$m[1]}");
                $edit = T::http('GET', "/admin/$key/{$m[1]}/edit");
                T::check(in_array($edit['status'], [200, 403], true), "$email edição $key/{$m[1]} -> {$edit['status']}");
            }
        }
        $imp = T::http('GET', '/admin/importar-alunos');
        T::check(in_array($imp['status'], [200, 403], true), "$email importar -> {$imp['status']}");
        T::eq(302, T::http('GET', '/admin/logout')['status'], "logout $email");
    }
};

$testes['painel: criar, editar, ações e escopo'] = function (): void {
    Seed::demo();
    T::eq(302, adminLogin('admin@vise.local'), 'login admin geral');
    $form = T::http('GET', '/admin/escolas/create');
    $csrf = T::csrf($form['body']);
    $r = T::http('POST', '/admin/escolas/create', null, [], [
        '_csrf' => $csrf, 'nome' => 'Escola Nova', 'municipio' => 'Cuiabá', 'uf' => 'mt', 'inep' => '51000001', 'ativo' => '1',
    ]);
    T::eq(302, $r['status'], 'cria escola: ' . substr(strip_tags($r['body']), 0, 300));
    $nova = Db::one("SELECT * FROM escolas WHERE inep = '51000001'");
    T::eq('MT', $nova['uf'] ?? null, 'UF em maiúsculas');

    $inval = T::http('POST', '/admin/escolas/create', null, [], ['_csrf' => $csrf, 'nome' => '', 'municipio' => 'X', 'uf' => 'MT']);
    T::eq(422, $inval['status'], 'campo obrigatório');

    $edit = T::http('POST', "/admin/escolas/{$nova['id']}/edit", null, [], [
        '_csrf' => $csrf, 'nome' => 'Escola Renomeada', 'municipio' => 'Cuiabá', 'uf' => 'MT', 'inep' => '51000001',
    ]);
    T::eq(302, $edit['status'], 'edita escola');
    T::eq('Escola Renomeada', Db::value('SELECT nome FROM escolas WHERE id = ?', [$nova['id']]), 'nome atualizado');
    T::eq(0, (int) Db::value('SELECT ativo FROM escolas WHERE id = ?', [$nova['id']]), 'checkbox desmarcado');

    $u = T::http('POST', '/admin/usuarios/create', null, [], [
        '_csrf' => $csrf, 'name' => 'Prof Novo', 'email' => 'Prof.Novo@Vise.Local', 'password' => 'senha1234',
        'vinculo_escola_id' => (string) $nova['id'], 'vinculo_role' => 'professor',
    ]);
    T::eq(302, $u['status'], 'cria usuário: ' . substr(strip_tags($u['body']), 0, 300));
    $uid = (int) Db::value("SELECT id FROM users WHERE email = 'prof.novo@vise.local'");
    T::check($uid > 0, 'e-mail normalizado');
    T::eq('professor', Db::value('SELECT role FROM escola_user WHERE user_id = ?', [$uid]), 'vínculo criado');
    T::check(Password::verify('senha1234', (string) Db::value('SELECT password FROM users WHERE id = ?', [$uid])), 'senha com hash');

    $q = (int) Db::value("SELECT id FROM questionarios WHERE nome = 'Bem-estar emocional - Demo'");
    $acao = T::http('POST', '/admin/questionarios/action', null, [], ['_csrf' => $csrf, 'action' => 'nova-versao', 'ids[0]' => (string) $q]);
    T::eq(302, $acao['status'], 'ação nova versão');
    $v2 = (int) Db::value('SELECT id FROM questionarios WHERE parent_id = ?', [$q]);
    T::check($v2 > 0, 'nova versão criada');
    T::http('POST', '/admin/questionarios/action', null, [], ['_csrf' => $csrf, 'action' => 'publicar', 'ids[0]' => (string) $v2]);
    T::eq('publicado', Db::value('SELECT status FROM questionarios WHERE id = ?', [$v2]), 'ação publicar');

    $del = T::http('POST', "/admin/escolas/{$nova['id']}/delete", null, [], ['_csrf' => $csrf]);
    T::eq(302, $del['status'], 'exclui escola');

    // Admin da escola demo não enxerga outra escola.
    $now = Db::now();
    $outra = Db::insert('escolas', ['nome' => 'Escola Alheia', 'municipio' => 'X', 'uf' => 'MT', 'ativo' => 1, 'created_at' => $now, 'updated_at' => $now]);
    T::eq(302, adminLogin('admin.escola@vise.local'), 'login admin escola');
    T::eq(404, T::http('GET', "/admin/escolas/$outra")['status'], 'escola alheia invisível');
    $lista = T::http('GET', '/admin/escolas');
    T::check(!str_contains($lista['body'], 'Escola Alheia'), 'lista sem escola alheia');
    $form = T::http('GET', '/admin/turmas/create');
    T::check(!str_contains($form['body'], 'Escola Alheia'), 'select de escola filtrado');
    $csrf = T::csrf($form['body']);
    $serie = (int) Db::value('SELECT id FROM series LIMIT 1');
    $r = T::http('POST', '/admin/turmas/create', null, [], ['_csrf' => $csrf, 'nome' => 'Invasora', 'escola_id' => (string) $outra, 'serie_id' => (string) $serie, 'turno' => 'matutino']);
    T::eq(422, $r['status'], 'não cria turma em escola alheia');

    T::eq(302, adminLogin('professor@vise.local'), 'login professor');
    T::eq(403, T::http('GET', '/admin/usuarios')['status'], 'professor sem usuários');
    $apl = T::http('GET', '/admin/aplicacoes');
    T::eq(200, $apl['status'], 'professor vê aplicações');
};

$testes['painel: importação CSV'] = function () use ($csv): void {
    Seed::demo();
    T::eq(302, adminLogin('admin.escola@vise.local'), 'login');
    $form = T::http('GET', '/admin/importar-alunos');
    $arquivo = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($arquivo, str_replace(['1,Bruno', ',A,'], ['00000001,Bruno', ',9º Ano A,'], $csv));
    $r = T::http('POST', '/admin/importar-alunos', null, [], [
        '_csrf' => T::csrf($form['body']),
        'arquivo' => new CURLFile($arquivo, 'text/csv', 'alunos.csv'),
    ]);
    unlink($arquivo);
    T::check(str_contains($r['body'], '1 aluno(s) importado(s)'), 'importação: ' . substr(strip_tags($r['body']), -400));
    T::eq('Bruno', Db::value("SELECT nome FROM alunos WHERE cpf = '52998224725'"), 'aluno importado');
};

// ---------------------------------------------------------------- planilhas, indicadores e relatórios

/** Texto de todas as abas de um .xlsx (para procurar valores). */
function xlsxTexto(string $binario): string
{
    return implode("\n", Zip::ler($binario, static fn (string $nome) => str_starts_with($nome, 'xl/worksheets/') || $nome === 'xl/workbook.xml'));
}

function arquivoTemp(string $conteudo, string $extensao): string
{
    $arquivo = tempnam(sys_get_temp_dir(), 'imp') . $extensao;
    file_put_contents($arquivo, $conteudo);
    return $arquivo;
}

$testes['xlsx: escrita, leitura e CSV Windows-1252'] = function (): void {
    $bin = (new XlsxWriter())->aba('Dados', [
        [XlsxWriter::c('Nome', 'header'), XlsxWriter::c('CPF', 'header'), XlsxWriter::c('Nascimento', 'header'), XlsxWriter::c('Nota', 'header')],
        ['João Ação & <Cia>', XlsxWriter::c('07593256189', 'text'), XlsxWriter::c('2009-10-21', 'date'), 7.5],
        ['Zé', XlsxWriter::c('00000001', 'text'), null, 10],
    ], ['congelar' => 1, 'filtro' => 1])->aba('Dados', [['segunda aba com nome repetido']])->gerar();
    T::eq("PK\x03\x04", substr($bin, 0, 4), 'zip');
    $linhas = XlsxReader::ler($bin);
    T::eq(['Nome', 'CPF', 'Nascimento', 'Nota'], $linhas[1] ?? null, 'cabeçalho');
    T::eq(['João Ação & <Cia>', '07593256189', '2009-10-21', '7.5'], $linhas[2] ?? null, 'linha com acentos, texto e data');
    T::eq('10', $linhas[3][3] ?? null, 'inteiro sem casas decimais');
    T::check(str_contains(implode('', Zip::ler($bin, static fn ($n) => $n === 'xl/workbook.xml')), 'Dados (2)'), 'nome de aba único');

    $p = Planilha::ler($bin);
    T::eq('xlsx', $p['formato'], 'formato xlsx');
    T::eq(2, count($p['linhas']), 'linhas sem o cabeçalho');
    $csv = Planilha::ler(mb_convert_encoding("nome;município\nJosé;Cuiabá\n", 'Windows-1252', 'UTF-8'));
    T::eq(['nome', 'município'], $csv['cabecalho'], 'CSV Windows-1252 convertido');
    T::eq(['José', 'Cuiabá'], array_values($csv['linhas'])[0] ?? null, 'CSV com ;');
    T::eq('data_de_nascimento', Planilha::chave(' Data de Nascimento '), 'chave normalizada');
    try {
        Planilha::ler("\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" . str_repeat("\0", 100));
        T::check(false, '.xls deveria ser recusado');
    } catch (RuntimeException $e) {
        T::check(str_contains($e->getMessage(), '.xlsx'), '.xls recusado com orientação');
    }
};

$testes['importação: alunos por xlsx com simulação, INEP e CPF sem zeros'] = function (): void {
    Seed::demo();
    $escola = (int) Db::value("SELECT id FROM escolas WHERE inep = '00000001'");
    $bin = (new XlsxWriter())->aba('Planilha1', [
        ['Escola', 'Nome do Aluno', 'CPF', 'Nascimento', 'Turma', 'Série', 'Turno', 'Sexo', 'Coluna Estranha'],
        [1, 'Carla Nova', 52998224725, XlsxWriter::c('2011-03-04', 'date'), '7º Ano C', '7º Ano', 'manhã', 'F', 'x'],
        ['Escola Demo Vise', 'Aluno Demo Atualizado', 7593256189, '21/10/2009', '9º Ano A', '', '', 'masc', ''],
        ['00000001', 'Sem CPF', '', '01/01/2010', '9º Ano A', '', '', '', ''],
        ['Escola Inexistente', 'Fulano', '11144477735', '01/01/2010', '9º Ano A', '', '', '', ''],
    ])->gerar();
    $antes = (int) Db::value('SELECT COUNT(*) FROM alunos');

    $sim = ImportacaoService::importar('alunos', $bin, null, true);
    T::eq([true, 4, 1, 1, 2], [$sim['simulacao'], $sim['total'], $sim['criados'], $sim['atualizados'], $sim['erros']], 'simulação: ' . json_encode($sim['linhas'], JSON_UNESCAPED_UNICODE));
    T::eq(['Coluna Estranha'], $sim['colunas_ignoradas'], 'coluna desconhecida ignorada');
    T::eq($antes, (int) Db::value('SELECT COUNT(*) FROM alunos'), 'simulação não grava');
    T::eq(null, Db::value("SELECT id FROM turmas WHERE nome = '7º Ano C'"), 'simulação não cria turma');

    $r = ImportacaoService::importar('alunos', $bin, [$escola]);
    T::eq([1, 1, 2], [$r['criados'], $r['atualizados'], $r['erros']], 'importação');
    $carla = Db::one("SELECT a.*, t.nome AS turma, t.turno FROM alunos a JOIN turmas t ON t.id = a.turma_id WHERE a.cpf = '52998224725'");
    T::eq(['Carla Nova', '2011-03-04', '7º Ano C', 'matutino', 'feminino'], [$carla['nome'] ?? null, $carla['data_nascimento'] ?? null, $carla['turma'] ?? null, $carla['turno'] ?? null, $carla['sexo'] ?? null], 'aluna criada com turma nova');
    T::eq('Aluno Demo Atualizado', Db::value("SELECT nome FROM alunos WHERE cpf = '07593256189'"), 'CPF sem zero à esquerda corrigido');
    $erros = array_values(array_filter($r['linhas'], static fn ($l) => $l['status'] === 'erro'));
    T::eq([4, 5], array_column($erros, 'linha'), 'número das linhas com erro');
    T::check(str_contains($erros[1]['mensagem'], 'Escola'), 'mensagem de escola: ' . $erros[1]['mensagem']);

    $fora = ImportacaoService::importar('alunos', $bin, [999999]);
    T::eq(0, $fora['criados'] + $fora['atualizados'], 'escola fora do escopo não importa');

    $modelo = XlsxReader::ler(ImportacaoService::modelo('alunos', [$escola]));
    T::eq('Escola *', $modelo[1][0] ?? null, 'modelo começa pela coluna escola');
    $reimport = ImportacaoService::importar('alunos', ImportacaoService::modelo('alunos', [$escola]), [$escola]);
    T::eq(0, $reimport['total'], 'modelo vazio não gera linhas');
};

$testes['importação: turmas, escolas e equipe'] = function (): void {
    Seed::demo();
    $escola = (int) Db::value("SELECT id FROM escolas WHERE inep = '00000001'");
    $csv = "escola;turma;serie;turno\n00000001;6º Ano A;6º Ano;Vespertino\n00000001;9º Ano A;9º Ano;noite\n";
    $r = ImportacaoService::importar('turmas', $csv, [$escola]);
    T::eq([1, 1, 0], [$r['criados'], $r['atualizados'], $r['erros']], 'turmas: ' . json_encode($r['linhas'], JSON_UNESCAPED_UNICODE));
    T::eq('noturno', Db::value("SELECT turno FROM turmas WHERE escola_id = ? AND nome = '9º Ano A'", [$escola]), 'turno atualizado');

    $r = ImportacaoService::importar('escolas', "nome,municipio,uf,inep\nEE Nova,Sinop,mt,51009999\nEE Nova,Sinop,MT,51009999\n");
    T::eq([1, 1], [$r['criados'], $r['atualizados']], 'escolas criada e depois atualizada pelo INEP');
    T::eq('MT', Db::value("SELECT uf FROM escolas WHERE inep = '51009999'"), 'UF em maiúsculas');

    $csv = "nome,email,perfil,escola,senha,turmas\n"
        . "Paula Prof,Paula@Escola.br,Professor,00000001,senhaForte1,9º Ano A; 6º Ano A\n"
        . "Sem Senha,semsenha@escola.br,professor,00000001,,\n"
        . "Professor Demo,professor@vise.local,Professor,00000001,,\n";
    $r = ImportacaoService::importar('equipe', $csv, [$escola]);
    T::eq([1, 1, 1], [$r['criados'], $r['atualizados'], $r['erros']], 'equipe: ' . json_encode($r['linhas'], JSON_UNESCAPED_UNICODE));
    $paula = (int) Db::value("SELECT id FROM users WHERE email = 'paula@escola.br'");
    T::check(Password::verify('senhaForte1', (string) Db::value('SELECT password FROM users WHERE id = ?', [$paula])), 'senha inicial');
    T::eq(2, (int) Db::value('SELECT COUNT(*) FROM professor_turma WHERE user_id = ?', [$paula]), 'turmas do professor');
    T::check(Password::verify('password', (string) Db::value("SELECT password FROM users WHERE email = 'professor@vise.local'")), 'usuário existente mantém a senha');

    $ctxEscola = \Vise\Admin\Auth::buildContext((int) Db::value("SELECT id FROM users WHERE email = 'admin.escola@vise.local'"));
    T::eq(['alunos', 'turmas', 'equipe'], array_keys(ImportacaoService::tiposPermitidos($ctxEscola)), 'admin da escola não importa escolas');
    $ctxProf = \Vise\Admin\Auth::buildContext((int) Db::value("SELECT id FROM users WHERE email = 'professor@vise.local'"));
    T::eq([], array_keys(ImportacaoService::tiposPermitidos($ctxProf)), 'professor não importa');
};

$testes['indicadores: níveis, filtros e escopo do professor'] = function (): void {
    Seed::respostasDemo(40);
    $admin = \Vise\Admin\Auth::buildContext((int) Db::value("SELECT id FROM users WHERE email = 'admin@vise.local'"));
    $prof = \Vise\Admin\Auth::buildContext((int) Db::value("SELECT id FROM users WHERE email = 'professor@vise.local'"));

    $geral = new Indicadores($admin, FiltrosRelatorio::deQuery([]));
    $r = $geral->resumo();
    T::eq((int) Db::value('SELECT COUNT(*) FROM alunos WHERE deleted_at IS NULL'), $r['alunos'], 'alunos');
    T::eq((int) Db::value('SELECT COUNT(*) FROM resultados'), $r['concluidos'], 'concluídos');
    T::check($r['prioritarios'] > 0 && $r['atencao'] > 0, 'há alunos em cada nível: ' . json_encode($r));
    T::check($r['participacao'] > 0 && $r['participacao'] <= 1, 'participação entre 0 e 1');
    $classificacao = $geral->classificacao();
    T::eq(2, count($classificacao), 'duas categorias');
    foreach ($classificacao as $c) {
        T::eq($c['avaliacoes'], array_sum($c['niveis']), "níveis somam as avaliações de {$c['categoria']}");
        T::eq(0, $c['niveis']['sem'], 'todas as faixas têm nível');
    }
    T::eq($r['concluidos'], array_sum(array_column($geral->evolucao()['pontos'], 'valor')), 'série soma os concluídos');
    T::eq($r['alunos'], array_sum(array_column($geral->porTurma(), 'alunos')), 'turmas somam os alunos');
    T::check(count($geral->alertas(5)) <= 5 && $geral->alertas(5) !== [], 'alertas limitados');
    T::check($geral->distribuicaoRespostas() !== [], 'distribuição por pergunta');

    $turmaB = (int) Db::value("SELECT id FROM turmas WHERE nome = '9º Ano B'");
    $soB = new Indicadores($admin, FiltrosRelatorio::deQuery(['turma_id' => (string) $turmaB]));
    T::eq(['9º Ano B'], array_column($soB->porTurma(), 'turma'), 'filtro de turma');
    T::eq((int) Db::value('SELECT COUNT(*) FROM alunos WHERE turma_id = ?', [$turmaB]), $soB->resumo()['alunos'], 'alunos da turma');

    Db::run("UPDATE resultados SET created_at = '2020-01-01 12:00:00'");
    $semana = new Indicadores($admin, FiltrosRelatorio::deQuery(['periodo' => '7']));
    T::eq(0, $semana->resumo()['concluidos'], 'período exclui resultados antigos');
    $antigo = new Indicadores($admin, FiltrosRelatorio::deQuery(['inicio' => '2020-01-01', 'fim' => '2020-01-01']));
    T::eq($r['concluidos'], $antigo->resumo()['concluidos'], 'período personalizado (dia local)');

    $doProf = new Indicadores($prof, FiltrosRelatorio::deQuery([])->noEscopo($prof));
    T::eq(['9º Ano A'], array_values(array_unique(array_column($doProf->porTurma(), 'turma'))), 'professor só vê a própria turma');
    $invasor = FiltrosRelatorio::deQuery(['turma_id' => (string) $turmaB])->noEscopo($prof);
    T::eq(null, $invasor->turmaId, 'filtro de turma alheia descartado');
};

$testes['painel web: dashboard, relatórios, exportações e importação'] = function (): void {
    Seed::respostasDemo(30);
    foreach (['admin@vise.local', 'admin.escola@vise.local', 'pesquisador@vise.local', 'professor@vise.local'] as $email) {
        T::eq(302, adminLogin($email), "login $email");
        $home = T::http('GET', '/admin?periodo=90');
        T::eq(200, $home['status'], "dashboard $email");
        T::check(str_contains($home['body'], 'Participação') && str_contains($home['body'], 'Níveis de atenção'), "dashboard com indicadores ($email)");
        T::eq(200, T::http('GET', '/admin?periodo=7&questionario_id=1&turma_id=999')['status'], "dashboard filtrado ($email)");
        $rel = T::http('GET', '/admin/relatorios?periodo=personalizado&inicio=2020-01-01&fim=2030-01-01');
        T::eq(200, $rel['status'], "relatórios $email");
        $xlsx = T::http('GET', '/admin/relatorios/exportar?abas[]=turmas&abas[]=alunos&abas[]=perguntas&abas[]=pendentes&abas[]=respostas');
        T::eq(200, $xlsx['status'], "exportar $email: " . substr(strip_tags($xlsx['body']), 0, 300));
        T::check(str_contains($xlsx['headers']['content-type'] ?? '', 'spreadsheetml'), 'content-type xlsx');
        T::check(str_contains($xlsx['headers']['content-disposition'] ?? '', '.xlsx'), 'nome do arquivo');
        $texto = xlsxTexto($xlsx['body']);
        T::check(str_contains($texto, 'Relatório de resultados'), 'aba resumo');
        T::check(str_contains($texto, '9º Ano A'), 'turma do recorte');
        T::eq($email !== 'professor@vise.local', str_contains($texto, '9º Ano B'), "escopo de turmas no relatório ($email)");
    }

    T::eq(302, adminLogin('admin.escola@vise.local'), 'login admin escola');
    $nome = (string) Db::value("SELECT a.nome FROM alunos a JOIN resultados r ON r.aluno_id = a.id ORDER BY a.id LIMIT 1");
    $normal = xlsxTexto(T::http('GET', '/admin/relatorios/exportar?abas=alunos')['body']);
    T::check(str_contains($normal, htmlspecialchars($nome, ENT_XML1)), 'nome do aluno no relatório');
    $anon = xlsxTexto(T::http('GET', '/admin/relatorios/exportar?abas=alunos,respostas,pendentes&anonimizar=1')['body']);
    T::check(!str_contains($anon, htmlspecialchars($nome, ENT_XML1)) && str_contains($anon, 'Aluno '), 'relatório anonimizado');
    T::check(!str_contains($anon, 'CPF'), 'sem coluna de CPF');

    $lista = T::http('GET', '/admin/alunos/exportar?q=Silva');
    T::eq(200, $lista['status'], 'exportar lista de alunos');
    $linhas = XlsxReader::ler($lista['body']);
    T::eq('ID', $linhas[1][0] ?? null, 'cabeçalho da lista');
    T::check(count($linhas) > 1 && !array_filter(array_slice($linhas, 1), static fn ($l) => !str_contains($l[1] ?? '', 'Silva')), 'busca aplicada na exportação');

    $form = T::http('GET', '/admin/importar?tipo=turmas');
    T::eq(200, $form['status'], 'página de importação');
    T::eq(403, T::http('GET', '/admin/importar?tipo=escolas')['status'], 'admin da escola sem importação de escolas');
    $modelo = T::http('GET', '/admin/importar/modelo/alunos');
    T::eq('Escola *', XlsxReader::ler($modelo['body'])[1][0] ?? null, 'modelo baixado');
    $arquivo = arquivoTemp((new XlsxWriter())->aba('T', [['escola', 'turma', 'serie', 'turno'], ['00000001', '5º Ano Z', '5º Ano', 'integral']])->gerar(), '.xlsx');
    $sim = T::http('POST', '/admin/importar', null, [], ['_csrf' => T::csrf($form['body']), 'tipo' => 'turmas', 'modo' => 'simular', 'arquivo' => new CURLFile($arquivo, 'application/octet-stream', 'turmas.xlsx')]);
    T::check(str_contains($sim['body'], 'Simulação (nada foi gravado)'), 'simulação pelo painel: ' . substr(strip_tags($sim['body']), -300));
    T::eq(null, Db::value("SELECT id FROM turmas WHERE nome = '5º Ano Z'"), 'simulação não grava');
    $imp = T::http('POST', '/admin/importar', null, [], ['_csrf' => T::csrf($form['body']), 'tipo' => 'turmas', 'modo' => 'importar', 'arquivo' => new CURLFile($arquivo, 'application/octet-stream', 'turmas.xlsx')]);
    unlink($arquivo);
    T::check(str_contains($imp['body'], '1 turma(s) importado(s)'), 'importação pelo painel');
    T::check(Db::value("SELECT id FROM turmas WHERE nome = '5º Ano Z'") !== null, 'turma gravada');

    T::eq(302, adminLogin('professor@vise.local'), 'login professor');
    T::eq(403, T::http('GET', '/admin/importar')['status'], 'professor sem importação');
    T::check(!str_contains(T::http('GET', '/admin')['body'], 'Importar planilha'), 'menu sem importação para professor');
};

$testes['API staff: painel, opções, relatório e importação'] = function (): void {
    Seed::respostasDemo(20);
    $h = staffAuth('admin.escola@vise.local');
    $painel = T::http('GET', '/api/v1/staff/painel?periodo=90', null, $h);
    T::eq(200, $painel['status'], 'painel: ' . substr($painel['body'], 0, 300));
    T::check(($painel['json']['data']['resumo']['alunos'] ?? 0) > 0, 'resumo');
    foreach (['evolucao', 'classificacao', 'turmas', 'aplicacoes', 'alertas', 'filtros'] as $chave) {
        T::check(isset($painel['json']['data'][$chave]), "painel tem $chave");
    }
    $opcoes = T::http('GET', '/api/v1/staff/relatorios/opcoes', null, $h);
    T::eq(200, $opcoes['status'], 'opções');
    T::check(count($opcoes['json']['data']['turmas'] ?? []) >= 4 && count($opcoes['json']['data']['abas'] ?? []) === count(\Vise\Services\RelatorioService::ABAS), 'turmas e abas');
    $xlsx = T::http('GET', '/api/v1/staff/relatorios/exportar?abas=turmas,alunos&anonimizar=true', null, $h);
    T::eq(200, $xlsx['status'], 'exportar pela API');
    T::check(str_contains(xlsxTexto($xlsx['body']), 'Resultados por aluno'), 'abas escolhidas');
    T::check(str_contains($xlsx['headers']['access-control-expose-headers'] ?? '', 'Content-Disposition'), 'nome do arquivo visível no navegador');

    $tipos = T::http('GET', '/api/v1/staff/importacao', null, $h);
    T::eq(['alunos', 'turmas', 'equipe'], array_column($tipos['json']['data'] ?? [], 'tipo'), 'tipos permitidos');
    $modelo = T::http('GET', '/api/v1/staff/importacao/alunos/modelo', null, $h);
    T::eq("PK\x03\x04", substr($modelo['body'], 0, 4), 'modelo xlsx');
    $arquivo = arquivoTemp("escola,nome,cpf,data_nascimento,turma_nome\n00000001,Nova Aluna,11144477735,05/06/2011,9º Ano B\n", '.csv');
    $sim = T::http('POST', '/api/v1/staff/importacao/alunos', null, $h, ['simular' => '1', 'arquivo' => new CURLFile($arquivo, 'text/csv', 'a.csv')]);
    T::eq([true, 1], [$sim['json']['data']['simulacao'] ?? null, $sim['json']['data']['criados'] ?? null], 'simulação pela API: ' . $sim['body']);
    T::eq(null, Db::value("SELECT id FROM alunos WHERE cpf = '11144477735'"), 'simulação não grava');
    $imp = T::http('POST', '/api/v1/staff/importacao/alunos', null, $h, ['arquivo' => new CURLFile($arquivo, 'text/csv', 'a.csv')]);
    unlink($arquivo);
    T::eq(1, $imp['json']['data']['criados'] ?? null, 'importação pela API');
    T::eq(422, T::http('POST', '/api/v1/staff/importacao/alunos', null, $h, ['simular' => '1'])['status'], 'sem arquivo');
    T::eq(403, T::http('POST', '/api/v1/staff/importacao/escolas', null, $h, ['simular' => '1'])['status'], 'escolas proibido');

    $prof = staffAuth('professor@vise.local');
    T::eq(200, T::http('GET', '/api/v1/staff/painel', null, $prof)['status'], 'professor vê o painel');
    T::eq([], T::http('GET', '/api/v1/staff/importacao', null, $prof)['json']['data'] ?? null, 'professor sem tipos de importação');
    T::eq(401, T::http('GET', '/api/v1/staff/painel')['status'], 'painel exige login');
};

// ---------------------------------------------------------------- execução

$port = (int) (getenv('TEST_PORT') ?: 18765);
T::$base = "http://127.0.0.1:$port";
T::$cookies = tempnam(sys_get_temp_dir(), 'vise-cookies');

$filtro = $argv[1] ?? '';
$root = dirname(__DIR__);
$log = sys_get_temp_dir() . '/vise-test-server.log';
$proc = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:$port", 'index.php'],
    [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'w'], 2 => ['file', $log, 'w']],
    $pipes,
    $root,
    array_merge(getenv(), $env)
);
register_shutdown_function(static function () use ($proc): void {
    proc_terminate($proc);
});

for ($i = 0; $i < 50; $i++) {
    $sock = @fsockopen('127.0.0.1', $port);
    if ($sock) {
        fclose($sock);
        break;
    }
    usleep(100000);
}

foreach ($testes as $nome => $teste) {
    if ($filtro !== '' && stripos($nome, $filtro) === false) {
        continue;
    }
    T::$atual = $nome;
    echo "- $nome\n";
    resetarBanco();
    try {
        $teste();
    } catch (Throwable $e) {
        T::$falhas[] = "$nome: exceção " . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
        echo '  EXCEÇÃO: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n";
    }
}

@unlink(T::$cookies);
echo "\n" . T::$ok . ' verificações ok, ' . count(T::$falhas) . " falha(s).\n";
if (T::$falhas !== []) {
    echo "Log do servidor: $log\n";
    exit(1);
}
