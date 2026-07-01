<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Model;

/**
 * Cartão salvo (`billing->cards()`) — apenas os 4 últimos dígitos.
 */
final class Card
{
    /**
     * @param string $number Últimos 4 dígitos.
     * @param string $validate Validade MM/AA.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $number,
        public readonly string $name,
        public readonly ?string $alias,
        public readonly string $validate,
        public readonly string $flag,
        public readonly bool $default,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            number: (string) ($data['number'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            alias: isset($data['alias']) ? (string) $data['alias'] : null,
            validate: (string) ($data['validate'] ?? ''),
            flag: (string) ($data['flag'] ?? ''),
            default: (bool) ($data['default'] ?? false),
        );
    }
}
