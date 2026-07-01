<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Model;

/**
 * Pacote de recarga (tier) disponível (`billing->plans()`).
 */
final class Plan
{
    /**
     * @param float $unit Preço unitário efetivo (R$).
     * @param float $total Total do pacote (R$).
     */
    public function __construct(
        public readonly string $id,
        public readonly int $quantity,
        public readonly float $price,
        public readonly float $sale,
        public readonly float $unit,
        public readonly float $total,
        public readonly bool $popular,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            quantity: (int) ($data['quantity'] ?? 0),
            price: (float) ($data['price'] ?? 0),
            sale: (float) ($data['sale'] ?? 0),
            unit: (float) ($data['unit'] ?? 0),
            total: (float) ($data['total'] ?? 0),
            popular: (bool) ($data['popular'] ?? false),
        );
    }
}
