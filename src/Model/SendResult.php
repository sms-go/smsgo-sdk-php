<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Model;

/**
 * Resultado de um envio (`send` / `sendBulk`).
 */
final class SendResult
{
    /**
     * @param string $id UUID do envio.
     * @param int $quantity Quantidade de mensagens do envio.
     * @param string $status `scheduled` quando há agendamento; senão `queued`.
     * @param bool|null $test Presente e `true` apenas em modo de teste (sandbox).
     */
    public function __construct(
        public readonly string $id,
        public readonly int $quantity,
        public readonly string $status,
        public readonly ?bool $test = null,
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
            status: (string) ($data['status'] ?? ''),
            test: array_key_exists('test', $data) ? (bool) $data['test'] : null,
        );
    }
}
