<?php

declare(strict_types=1);

/**
 * Compra de créditos off-session (cartão salvo) + recarga automática.
 *
 * Uso:
 *   SMSGO_KEY=suachave php examples/buy-credits.php
 *
 * ⚠️ Idempotência: cada purchase gera uma cobrança nova. Em timeout, consulte
 * billing->invoices() antes de repetir — não faça retry cego.
 */

require __DIR__ . '/../vendor/autoload.php';

use Orynlabs\SMSGo\Client;
use Orynlabs\SMSGo\SMSGoError;

$apiKey = getenv('SMSGO_KEY');
if ($apiKey === false || $apiKey === '') {
    fwrite(STDERR, "Defina SMSGO_KEY no ambiente.\n");
    exit(1);
}

$client = new Client($apiKey);

try {
    // Pacotes por faixa + cartões salvos (4 últimos dígitos).
    foreach ($client->billing->plans() as $plan) {
        printf("Pacote %s: %d créditos por R$ %.2f%s\n", $plan->id, $plan->quantity, $plan->total, $plan->popular ? ' (popular)' : '');
    }

    $cards = $client->billing->cards();
    if ($cards === []) {
        echo "Nenhum cartão salvo — cadastre um no painel antes de comprar.\n";
    }

    // Compra 5000 créditos no cartão padrão.
    $receipt = $client->billing->purchase(quantity: 5000);
    printf(
        "Compra: status=%s fatura=%s total=R$ %.2f (%d créditos)\n",
        $receipt->status,
        $receipt->invoiceUuid,
        $receipt->total,
        $receipt->quantity,
    );

    // Liga a recarga automática + alerta de saldo (exige cardId + planQuantity).
    if ($cards !== []) {
        $cfg = $client->setAutoRecharge(
            enabled: true,
            threshold: 5,
            planQuantity: 5000,
            cardId: $cards[0]->id,
            alertEnabled: true,
            alertThreshold: 15,
        );
        printf("Recarga automática: %s (limiar R$ %.2f)\n", $cfg->enabled ? 'ligada' : 'desligada', $cfg->threshold);
    }
} catch (SMSGoError $e) {
    fwrite(STDERR, sprintf("Erro %d/%s: %s\n", $e->status, $e->code, $e->getMessage()));
    exit(1);
}
