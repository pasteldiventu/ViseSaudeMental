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
use Vise\Services\ImportAlunosService;
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
