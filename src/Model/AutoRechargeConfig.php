<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Model;

/**
 * Configuração de recarga automática + alerta de saldo.
 */
final class AutoRechargeConfig
{
    /**
     * @param float $threshold Limiar de recarga (R$).
     * @param int $planQuantity Créditos comprados a cada recarga.
     * @param float $alertThreshold Limiar de alerta de saldo (R$).
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly float $threshold,
        public readonly int $planQuantity,
        public readonly ?string $cardId,
        public readonly bool $alertEnabled,
        public readonly float $alertThreshold,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            enabled: (bool) ($data['enabled'] ?? false),
            threshold: (float) ($data['threshold'] ?? 0),
            planQuantity: (int) ($data['planQuantity'] ?? 0),
            cardId: isset($data['cardId']) ? (string) $data['cardId'] : null,
            alertEnabled: (bool) ($data['alertEnabled'] ?? false),
            alertThreshold: (float) ($data['alertThreshold'] ?? 0),
        );
    }
}
