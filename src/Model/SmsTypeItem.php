<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Model;

/**
 * Tipo de SMS do catálogo (`getSmsTypes`). O `id` é o valor de `smsTypeId`.
 */
final class SmsTypeItem
{
    /**
     * @param int $id Valor a enviar em `smsTypeId`/`sms_type_id`.
     * @param float $price Preço unitário (R$).
     * @param float|null $sale Preço promocional (R$), se houver.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly float $price,
        public readonly ?float $sale,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            price: (float) ($data['price'] ?? 0),
            sale: isset($data['sale']) ? (float) $data['sale'] : null,
        );
    }
}
