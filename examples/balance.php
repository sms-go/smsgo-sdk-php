<?php

declare(strict_types=1);

/**
 * Saldo + catálogo de tipos de SMS.
 *
 * Uso:
 *   SMSGO_KEY=suachave php examples/balance.php
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
    $balance = $client->getBalance();
    printf("Saldo: %.2f %s (%s)\n", $balance->balance, $balance->currency, $balance->company['name']);

    echo "Tipos de SMS:\n";
    foreach ($client->getSmsTypes() as $type) {
        $price = $type->sale ?? $type->price;
        printf("  #%d %s — R$ %.2f\n", $type->id, $type->name, $price);
    }
} catch (SMSGoError $e) {
    fwrite(STDERR, sprintf("Erro %d/%s: %s\n", $e->status, $e->code, $e->getMessage()));
    exit(1);
}
