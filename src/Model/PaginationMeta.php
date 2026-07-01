<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Model;

/**
 * Metadados de paginação retornados nas listagens.
 */
final class PaginationMeta
{
    public function __construct(
        public readonly int $total,
        public readonly int $perPage,
        public readonly int $currentPage,
        public readonly int $lastPage,
        public readonly int $firstPage,
        public readonly string $firstPageUrl,
        public readonly string $lastPageUrl,
        public readonly ?string $nextPageUrl,
        public readonly ?string $previousPageUrl,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            total: (int) ($data['total'] ?? 0),
            perPage: (int) ($data['perPage'] ?? 0),
            currentPage: (int) ($data['currentPage'] ?? 0),
            lastPage: (int) ($data['lastPage'] ?? 0),
            firstPage: (int) ($data['firstPage'] ?? 0),
            firstPageUrl: (string) ($data['firstPageUrl'] ?? ''),
            lastPageUrl: (string) ($data['lastPageUrl'] ?? ''),
            nextPageUrl: isset($data['nextPageUrl']) ? (string) $data['nextPageUrl'] : null,
            previousPageUrl: isset($data['previousPageUrl']) ? (string) $data['previousPageUrl'] : null,
        );
    }
}
