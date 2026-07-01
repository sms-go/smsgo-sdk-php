<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Model;

/**
 * Detalhe de um contato (`contacts->get()`).
 */
final class ContactDetail
{
    public function __construct(
        public readonly string $fullName,
        public readonly ?string $email,
        public readonly string $phone,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            fullName: (string) ($data['fullName'] ?? ''),
            email: isset($data['email']) ? (string) $data['email'] : null,
            phone: (string) ($data['phone'] ?? ''),
        );
    }
}
