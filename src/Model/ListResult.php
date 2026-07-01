<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Model;

/**
 * Lista de contatos (`lists->create/get/update`).
 */
final class ListResult
{
    public function __construct(
        public readonly string $name,
        public readonly string $id,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            id: (string) ($data['id'] ?? ''),
        );
    }
}
