<?php

declare(strict_types=1);

namespace Vise\Http;

final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(public int $status = 200, public string $body = '', public array $headers = [])
    {
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $body = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE
        );
        return new self($status, (string) $body, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($status, $html, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function text(string $text, int $status = 200): self
    {
        return new self($status, $text, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public static function download(string $conteudo, string $arquivo, string $tipo = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'): self
    {
        $ascii = (string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $arquivo);
        return new self(200, $conteudo, [
            'Content-Type' => $tipo,
            'Content-Disposition' => 'attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($arquivo),
            'Content-Length' => (string) strlen($conteudo),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Access-Control-Expose-Headers' => 'Content-Disposition',
        ]);
    }

    public static function redirect(string $url): self
    {
        return new self(302, '', ['Location' => $url]);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        echo $this->body;
    }
}
