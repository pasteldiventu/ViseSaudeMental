<?php

declare(strict_types=1);

namespace Vise\Http;

final class HttpError extends \RuntimeException
{
    /** @param array<string, string> $headers */
    public function __construct(public int $status, string $detail, public array $headers = [])
    {
        parent::__construct($detail, $status);
    }

    public static function unauthorized(string $detail): self
    {
        return new self(401, $detail, ['WWW-Authenticate' => 'Bearer']);
    }
}
