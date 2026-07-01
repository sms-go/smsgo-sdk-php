<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Model;

/**
 * Contagens por bucket de status de um envio.
 */
final class SendSummary
{
    /**
     * @param bool $done `true` quando nenhum número está mais em andamento.
     */
    public function __construct(
        public readonly int $total,
        public readonly int $delivered,
        public readonly int $failed,
        public readonly int $inProgress,
        public readonly bool $done,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            total: (int) ($data['total'] ?? 0),
            delivered: (int) ($data['delivered'] ?? 0),
            failed: (int) ($data['failed'] ?? 0),
            inProgress: (int) ($data['inProgress'] ?? 0),
            done: (bool) ($data['done'] ?? false),
        );
    }
}
