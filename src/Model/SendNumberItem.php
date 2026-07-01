<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Model;

/**
 * Item da listagem de números de um envio (`getNumbers`).
 */
final class SendNumberItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $phone,
        public readonly ?string $code,
        public readonly string $status,
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
            phone: (string) ($data['phone'] ?? ''),
            code: isset($data['code']) ? (string) $data['code'] : null,
            status: (string) ($data['status'] ?? ''),
            createdAt: (string) ($data['created_at'] ?? ''),
        );
    }
}
