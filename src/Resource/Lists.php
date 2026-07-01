<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Resource;

use Orynlabs\SMSGo\Model\ListResult;
use Orynlabs\SMSGo\Model\Paginated;

/**
 * Namespace de listas (CRUD). Compartilha o transporte (auth/refresh/erros) do cliente.
 */
final class Lists
{
    /**
     * @param \Closure(string, string, array<string, mixed>|null): mixed $request
     */
    public function __construct(private readonly \Closure $request)
    {
    }

    /**
     * Lista as listas da conta (paginado; `page` obrigatório).
     *
     * @return Paginated<array<string, mixed>>
     */
    public function list(int $page, ?int $perPage = null, ?string $title = null): Paginated
    {
        $query = self::buildQuery([
            'page' => $page,
            'perPage' => $perPage,
            'title' => $title,
        ]);

        $res = ($this->request)('GET', '/v1/lists/list' . $query, null);

        return Paginated::fromArray(is_array($res) ? $res : []);
    }

    /** Cria uma lista. */
    public function create(string $name): ListResult
    {
        $res = ($this->request)('POST', '/v1/lists/store', ['name' => $name]);

        return ListResult::fromArray(is_array($res) ? $res : []);
    }

    /** Detalha uma lista pelo UUID. */
    public function get(string $id): ListResult
    {
        $res = ($this->request)('GET', '/v1/lists/' . rawurlencode($id) . '/show', null);

        return ListResult::fromArray(is_array($res) ? $res : []);
    }

    /** Atualiza uma lista. */
    public function update(string $id, string $name): ListResult
    {
        $res = ($this->request)('PUT', '/v1/lists/' . rawurlencode($id) . '/update', ['name' => $name]);

        return ListResult::fromArray(is_array($res) ? $res : []);
    }

    /**
     * Exclui uma lista.
     *
     * @return array{message: string}
     */
    public function delete(string $id): array
    {
        $res = ($this->request)('DELETE', '/v1/lists/' . rawurlencode($id) . '/delete', null);
        $message = is_array($res) ? (string) ($res['message'] ?? '') : '';

        return ['message' => $message];
    }

    /**
     * @param array<string, string|int|float|bool|null> $params
     */
    private static function buildQuery(array $params): string
    {
        $pairs = [];
        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }
            $pairs[$key] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        return $pairs === [] ? '' : '?' . http_build_query($pairs);
    }
}
