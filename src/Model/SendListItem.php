<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Model;

/**
 * Item da listagem de envios (`list`).
 */
final class SendListItem
{
    public function __construct(
        public readonly string $id,
        public readonly ?int $number,
        public readonly ?string $date,
        public readonly int $quantity,
        public readonly string $fullName,
        public readonly string $createdAt,
        public readonly string $status,
        public readonly string $type,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            number: isset($data['number']) ? (int) $data['number'] : null,
            date: isset($data['date']) ? (string) $data['date'] : null,
            quantity: (int) ($data['quantity'] ?? 0),
            fullName: (string) ($data['full_name'] ?? ''),
            createdAt: (string) ($data['created_at'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            type: (string) ($data['type'] ?? ''),
        );
    }
}
