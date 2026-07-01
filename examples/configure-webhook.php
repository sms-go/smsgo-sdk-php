<?php

declare(strict_types=1);

/**
 * Configura o webhook de saída (DLR + respostas).
 *
 * Uso:
 *   SMSGO_KEY=suachave php examples/configure-webhook.php https://seuapp.com/webhooks/smsgo
 *   SMSGO_KEY=suachave php examples/configure-webhook.php --rotate
 *   SMSGO_KEY=suachave php examples/configure-webhook.php --disable
 */

require __DIR__ . '/../vendor/autoload.php';

use Orynlabs\SMSGo\Client;
use Orynlabs\SMSGo\SMSGoError;

$apiKey = getenv('SMSGO_KEY');
if ($apiKey === false || $apiKey === '') {
    fwrite(STDERR, "Defina SMSGO_KEY no ambiente.\n");
    exit(1);
}

$arg = $argv[1] ?? 'https://seuapp.com/webhooks/smsgo';
$client = new Client($apiKey);

try {
    if ($arg === '--rotate') {
        $cfg = $client->setWebhook(rotateSecret: true); // gira o segredo
    } elseif ($arg === '--disable') {
        $cfg = $client->setWebhook(url: ''); // string vazia desativa
    } else {
        $cfg = $client->setWebhook(url: $arg);
    }

    printf("Webhook: url=%s\n", $cfg->url ?? '(desativado)');
    if ($cfg->secret !== null) {
        printf("Guarde o secret para validar as assinaturas: %s\n", $cfg->secret);
    }
} catch (SMSGoError $e) {
    fwrite(STDERR, sprintf("Erro %d/%s: %s\n", $e->status, $e->code, $e->getMessage()));
    exit(1);
}
