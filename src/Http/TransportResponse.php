<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Http;

/**
 * Resposta bruta de uma requisição HTTP, independente da implementação de transporte.
 */
final class TransportResponse
{
    /**
     * @param int $status Status HTTP.
     * @param array<string, string> $headers Cabeçalhos da resposta (nomes em minúsculas).
     * @param string $body Corpo bruto da resposta (pode ser string vazia).
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }
}
