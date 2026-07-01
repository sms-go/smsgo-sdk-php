<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Model;

/**
 * Resultado de uma compra de créditos (`billing->purchase()`).
 */
final class PurchaseResult
{
    /**
     * @param string $status `succeeded` já creditou o saldo; `processing` confirma via webhook.
     * @param float $total Valor cobrado (R$).
     */
    public function __construct(
        public readonly string $status,
        public readonly string $invoiceUuid,
        public readonly float $total,
        public readonly int $quantity,
        public readonly string $paymentIntentId,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: (string) ($data['status'] ?? ''),
            invoiceUuid: (string) ($data['invoiceUuid'] ?? ''),
            total: (float) ($data['total'] ?? 0),
            quantity: (int) ($data['quantity'] ?? 0),
            paymentIntentId: (string) ($data['paymentIntentId'] ?? ''),
        );
    }
}
