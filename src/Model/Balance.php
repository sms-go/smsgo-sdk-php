<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Model;

/**
 * Saldo monetário (R$) + dados básicos da conta.
 */
final class Balance
{
    /**
     * @param float $balance Saldo disponível em R$.
     * @param array{name: string, document: string|null} $company
     */
    public function __construct(
        public readonly float $balance,
        public readonly string $currency,
        public readonly array $company,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rawCompany = is_array($data['company'] ?? null) ? $data['company'] : [];

        return new self(
            balance: (float) ($data['balance'] ?? 0),
            currency: (string) ($data['currency'] ?? ''),
            company: [
                'name' => (string) ($rawCompany['name'] ?? ''),
                'document' => isset($rawCompany['document']) ? (string) $rawCompany['document'] : null,
            ],
        );
    }
}
