<?php

declare(strict_types=1);

namespace Vise\Http;

final class Request
{
    /** @var array<string, mixed>|null */
    private ?array $jsonCache = null;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, string> $headers chaves em minúsculas
     * @param array<string, mixed> $files
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public array $post = [],
        public array $headers = [],
        public string $body = '',
        public array $files = [],
        public ?string $ip = null,
        public bool $https = false,
    ) {
    }

    public static function capture(): self
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
        $uri = rawurldecode((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/'));
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        // Hospedagens sem mod_rewrite: /index.php/api/v1/...
        if (str_starts_with($uri, '/index.php')) {
            $uri = substr($uri, strlen('/index.php'));
        }
        Url::setBase($base);

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with((string) $key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr((string) $key, 5)))] = (string) $value;
            }
        }
        foreach (['CONTENT_TYPE' => 'content-type', 'CONTENT_LENGTH' => 'content-length'] as $key => $name) {
            if (isset($_SERVER[$key])) {
                $headers[$name] = (string) $_SERVER[$key];
            }
        }
        // Apache/CGI costuma esconder o Authorization; tenta as alternativas conhecidas.
        if (($headers['authorization'] ?? '') === '') {
            $fallback = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
            if ($fallback === null && function_exists('apache_request_headers')) {
                foreach ((array) apache_request_headers() as $name => $value) {
                    if (strtolower((string) $name) === 'authorization') {
                        $fallback = $value;
                    }
                }
            }
            if ($fallback !== null && $fallback !== '') {
                $headers['authorization'] = (string) $fallback;
            }
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            '/' . trim($uri, '/'),
            $_GET,
            $_POST,
            $headers,
            (string) file_get_contents('php://input'),
            $_FILES,
            isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null,
            $https,
        );
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        if ($this->jsonCache !== null) {
            return $this->jsonCache;
        }
        if (trim($this->body) === '') {
            throw new HttpError(422, 'Corpo da requisição ausente.');
        }
        $data = json_decode($this->body, true);
        if (!is_array($data)) {
            throw new HttpError(422, 'JSON inválido.');
        }
        return $this->jsonCache = $data;
    }

    public function bearerToken(): ?string
    {
        $authorization = trim((string) $this->header('authorization'));
        if (preg_match('/^Bearer\s+(\S+)$/i', $authorization, $match)) {
            return $match[1];
        }
        // Alternativa enviada pelo app para hospedagens que removem o Authorization.
        $token = trim((string) $this->header('x-auth-token'));
        return $token !== '' ? $token : null;
    }

    public function queryInt(string $key): ?int
    {
        $value = $this->query[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || !preg_match('/^-?\d+$/', $value)) {
            throw new HttpError(422, "Parâmetro $key deve ser um número inteiro.");
        }
        return (int) $value;
    }
}
