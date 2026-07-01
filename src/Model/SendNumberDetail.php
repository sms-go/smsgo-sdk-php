<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Model;

/**
 * Detalhe de um número dentro de um envio (embutido em {@see SendDetail}).
 */
final class SendNumberDetail
{
    public function __construct(
        public readonly string $id,
        public readonly int $characters,
        public readonly ?string $code,
        public readonly float $cost,
        public readonly string $message,
        public readonly string $phone,
        public readonly string $status,
        public readonly ?string $template,
        public readonly string $createdAt,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            characters: (int) ($data['characters'] ?? 0),
            code: isset($data['code']) ? (string) $data['code'] : null,
            cost: (float) ($data['cost'] ?? 0),
            message: (string) ($data['message'] ?? ''),
            phone: (string) ($data['phone'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            template: isset($data['template']) ? (string) $data['template'] : null,
            createdAt: (string) ($data['created_at'] ?? ''),
        );
    }
}
