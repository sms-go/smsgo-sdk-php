<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Resource;

use Orynlabs\SMSGo\Model\Card;
use Orynlabs\SMSGo\Model\InvoiceItem;
use Orynlabs\SMSGo\Model\Paginated;
use Orynlabs\SMSGo\Model\Plan;
use Orynlabs\SMSGo\Model\PurchaseResult;

/**
 * Namespace de faturamento (pacotes, cartões, faturas, compra off-session).
 * Compartilha o transporte (auth/refresh/erros) do cliente.
 */
final class Billing
{
    /**
     * @param \Closure(string, string, array<string, mixed>|null): mixed $request
     */
    public function __construct(private readonly \Closure $request)
    {
    }

    /**
     * Pacotes de recarga (tiers) disponíveis.
     *
     * @return list<Plan>
     */
    public function plans(): array
    {
        $res = ($this->request)('GET', '/v1/billing/plans', null);
        $data = is_array($res) && is_array($res['data'] ?? null) ? $res['data'] : [];

        return array_values(array_map(
            static fn (mixed $item): Plan => Plan::fromArray(is_array($item) ? $item : []),
            $data,
        ));
    }

    /**
     * Cartões salvos (apenas os 4 últimos dígitos).
     *
     * @return list<Card>
     */
    public function cards(): array
    {
        $res = ($this->request)('GET', '/v1/billing/cards', null);
        $data = is_array($res) && is_array($res['data'] ?? null) ? $res['data'] : [];

        return array_values(array_map(
            static fn (mixed $item): Card => Card::fromArray(is_array($item) ? $item : []),
            $data,
        ));
    }

    /**
     * Histórico de faturas/recibos (paginado).
     *
     * @return Paginated<InvoiceItem>
     */
    public function invoices(?int $page = null, ?int $perPage = null): Paginated
    {
        $query = self::buildQuery([
            'page' => $page,
            'perPage' => $perPage,
        ]);

        $res = ($this->request)('GET', '/v1/billing/invoices' . $query, null);

        return Paginated::fromArray(
            is_array($res) ? $res : [],
            static fn (array $item): InvoiceItem => InvoiceItem::fromArray($item),
        );
    }

    /**
     * Compra créditos cobrando um cartão salvo (off-session). Informe `quantity`
     * ou `planId`. Sem `cardId`, usa o cartão padrão.
     *
     * ⚠️ Idempotência: cada chamada gera uma cobrança nova. Em timeout, consulte
     * `invoices()` antes de repetir — não faça retry cego.
     *
     * @param int|null $quantity Quantidade de créditos (250–1.000.000). Ignorado se `planId` for enviado.
     * @param string|null $planId UUID de um pacote (tier). Tem prioridade sobre `quantity`.
     * @param string|null $cardId UUID do cartão salvo (usa o padrão se omitido).
     * @param string|null $coupon Código de cupom.
     */
    public function purchase(
        ?int $quantity = null,
        ?string $planId = null,
        ?string $cardId = null,
        ?string $coupon = null,
    ): PurchaseResult {
        $res = ($this->request)('POST', '/v1/billing/purchase', [
            'quantity' => $quantity,
            'plan_id' => $planId,
            'card_id' => $cardId,
            'coupon' => $coupon,
        ]);

        return PurchaseResult::fromArray(is_array($res) ? $res : []);
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
