<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Resource;

use Orynlabs\SMSGo\Model\ContactDetail;
use Orynlabs\SMSGo\Model\Paginated;

/**
 * Namespace de contatos (CRUD). Compartilha o transporte (auth/refresh/erros) do cliente.
 */
final class Contacts
{
    /**
     * @param \Closure(string, string, array<string, mixed>|null): mixed $request
     */
    public function __construct(private readonly \Closure $request)
    {
    }

    /**
     * Lista contatos (paginado; `page` obrigatório).
     *
     * @return Paginated<array<string, mixed>>
     */
    public function list(int $page, ?int $perPage = null, ?string $search = null, ?string $title = null): Paginated
    {
        $query = self::buildQuery([
            'page' => $page,
            'perPage' => $perPage,
            'search' => $search,
            'title' => $title,
        ]);

        $res = ($this->request)('GET', '/v1/contacts/list' . $query, null);

        return Paginated::fromArray(is_array($res) ? $res : []);
    }

    /**
     * Cria (ou faz upsert pelo telefone) um contato. Retorna o UUID.
     *
     * @param list<string>|null $lists UUIDs das listas às quais associar o contato.
     */
    public function create(string $fullName, string $phone, ?string $email = null, ?array $lists = null): string
    {
        $res = ($this->request)('POST', '/v1/contacts/store', self::body($fullName, $phone, $email, $lists));

        return is_string($res) ? $res : (string) ($res['id'] ?? '');
    }

    /** Detalha um contato pelo UUID. */
    public function get(string $id): ContactDetail
    {
        $res = ($this->request)('GET', '/v1/contacts/' . rawurlencode($id) . '/show', null);

        return ContactDetail::fromArray(is_array($res) ? $res : []);
    }

    /**
     * Atualiza um contato. Retorna o UUID.
     *
     * @param list<string>|null $lists
     */
    public function update(string $id, string $fullName, string $phone, ?string $email = null, ?array $lists = null): string
    {
        $res = ($this->request)(
            'PUT',
            '/v1/contacts/' . rawurlencode($id) . '/update',
            self::body($fullName, $phone, $email, $lists),
        );

        return is_string($res) ? $res : (string) ($res['id'] ?? '');
    }

    /**
     * Exclui um contato.
     *
     * @return array{message: string}
     */
    public function delete(string $id): array
    {
        $res = ($this->request)('DELETE', '/v1/contacts/' . rawurlencode($id) . '/delete', null);
        $message = is_array($res) ? (string) ($res['message'] ?? '') : '';

        return ['message' => $message];
    }

    /**
     * @param list<string>|null $lists
     *
     * @return array<string, mixed>
     */
    private static function body(string $fullName, string $phone, ?string $email, ?array $lists): array
    {
        return [
            'full_name' => $fullName,
            'phone' => $phone,
            'email' => $email,
            'lists' => $lists,
        ];
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
