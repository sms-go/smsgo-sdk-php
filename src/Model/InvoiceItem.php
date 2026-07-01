<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Model;

/**
 * Item do histórico de faturas (`billing->invoices()`).
 */
final class InvoiceItem
{
    /**
     * @param array{code: string, name: string, icon: string|null, color: string|null}|null $status
     * @param array{code: string, name: string}|null $card
     */
    public function __construct(
        public readonly string $uuid,
        public readonly float $total,
        public readonly string $date,
        public readonly string $expiry,
        public readonly int $displayId,
        public readonly ?array $status,
        public readonly ?array $card,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $status = null;
        if (is_array($data['status'] ?? null)) {
            $rawStatus = $data['status'];
            $status = [
                'code' => (string) ($rawStatus['code'] ?? ''),
                'name' => (string) ($rawStatus['name'] ?? ''),
                'icon' => isset($rawStatus['icon']) ? (string) $rawStatus['icon'] : null,
                'color' => isset($rawStatus['color']) ? (string) $rawStatus['color'] : null,
            ];
        }

        $card = null;
        if (is_array($data['card'] ?? null)) {
            $rawCard = $data['card'];
            $card = [
                'code' => (string) ($rawCard['code'] ?? ''),
                'name' => (string) ($rawCard['name'] ?? ''),
            ];
        }

        return new self(
            uuid: (string) ($data['uuid'] ?? ''),
            total: (float) ($data['total'] ?? 0),
            date: (string) ($data['date'] ?? ''),
            expiry: (string) ($data['expiry'] ?? ''),
            displayId: (int) ($data['displayId'] ?? 0),
            status: $status,
            card: $card,
        );
    }
}
