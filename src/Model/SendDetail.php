<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Model;

/**
 * Detalhe de um envio (`get`), incluindo `summary` de acompanhamento e os números.
 */
final class SendDetail
{
    /**
     * @param list<SendNumberDetail> $phones
     */
    public function __construct(
        public readonly string $id,
        public readonly int $quantity,
        public readonly int $characters,
        public readonly ?string $date,
        public readonly float $total,
        public readonly float $cost,
        public readonly string $user,
        public readonly string $status,
        public readonly string $type,
        public readonly SendSummary $summary,
        public readonly array $phones,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rawSummary = is_array($data['summary'] ?? null) ? $data['summary'] : [];
        $rawPhones = is_array($data['phones'] ?? null) ? $data['phones'] : [];

        $phones = [];
        foreach ($rawPhones as $phone) {
            $phones[] = SendNumberDetail::fromArray(is_array($phone) ? $phone : []);
        }

        return new self(
            id: (string) ($data['id'] ?? ''),
            quantity: (int) ($data['quantity'] ?? 0),
            characters: (int) ($data['characters'] ?? 0),
            date: isset($data['date']) ? (string) $data['date'] : null,
            total: (float) ($data['total'] ?? 0),
            cost: (float) ($data['cost'] ?? 0),
            user: (string) ($data['user'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            type: (string) ($data['type'] ?? ''),
            summary: SendSummary::fromArray($rawSummary),
            phones: $phones,
        );
    }
}
