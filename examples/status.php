<?php

declare(strict_types=1);

/**
 * Envio em massa + consulta de status.
 *
 * Uso:
 *   SMSGO_KEY=suachave php examples/status.php
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
    $sent = $client->sendBulk(
        messages: [
            ['phone' => '+5511999990000', 'message' => 'Oi, Ana!'],
            ['phone' => '+5521988887777', 'message' => 'Oi, Bruno!'],
        ],
        urlCallback: 'https://seuapp.com/webhooks/smsgo',
    );
    printf("Lote criado: id=%s quantity=%d status=%s\n", $sent->id, $sent->quantity, $sent->status);

    // Detalhe + summary de acompanhamento.
    $detail = $client->get($sent->id);
    printf(
        "Summary: total=%d entregues=%d falhas=%d em_andamento=%d done=%s\n",
        $detail->summary->total,
        $detail->summary->delivered,
        $detail->summary->failed,
        $detail->summary->inProgress,
        $detail->summary->done ? 'sim' : 'não',
    );

    // Números que falharam, paginado por bucket.
    $failed = $client->getNumbers($sent->id, status: 'failed', page: 1);
    printf("Falhas na página 1: %d\n", count($failed->data));
} catch (SMSGoError $e) {
    fwrite(STDERR, sprintf("Erro %d/%s: %s\n", $e->status, $e->code, $e->getMessage()));
    exit(1);
}
