<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Model;

/**
 * Container genérico de listagem paginada: `{ meta, data }`.
 *
 * Os itens em `$data` são preservados como arrays associativos crus, exceto quando
 * um mapeador é informado a {@see Paginated::fromArray()} (usado pelos métodos que
 * devolvem DTOs tipados, como `list()` e `getNumbers()`).
 *
 * @template T
 */
final class Paginated
{
    /**
     * @param PaginationMeta $meta
     * @param list<T> $data
     */
    public function __construct(
        public readonly PaginationMeta $meta,
        public readonly array $data,
    ) {
    }

    /**
     * @template U
     *
     * @param array<array-key, mixed> $payload
     * @param (callable(array<string, mixed>): U)|null $mapItem Converte cada item em DTO.
     *
     * @return ($mapItem is null ? self<array<string, mixed>> : self<U>)
     */
    public static function fromArray(array $payload, ?callable $mapItem = null): self
    {
        /** @var array<string, mixed> $rawMeta */
        $rawMeta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
        /** @var array<int, mixed> $rawData */
        $rawData = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $meta = PaginationMeta::fromArray($rawMeta);

        // Ramos separados para o PHPStan inferir o parâmetro genérico de cada
        // caminho: self<array<string, mixed>> sem mapper, self<U> com mapper —
        // batendo com o tipo de retorno condicional declarado acima.
        if ($mapItem === null) {
            $rows = [];
            foreach ($rawData as $item) {
                /** @var array<string, mixed> $row */
                $row = is_array($item) ? $item : [];
                $rows[] = $row;
            }

            return new self($meta, $rows);
        }

        $mapped = [];
        foreach ($rawData as $item) {
            /** @var array<string, mixed> $row */
            $row = is_array($item) ? $item : [];
            $mapped[] = $mapItem($row);
        }

        return new self($meta, $mapped);
    }
}
