<?php

declare(strict_types=1);

/**
 * Envio simples de SMS.
 *
 * Uso:
 *   SMSGO_KEY=suachave php examples/send.php +5511999990000 "Olá do SMSGo"
 */

require __DIR__ . '/../vendor/autoload.php';

use Orynlabs\SMSGo\Client;
use Orynlabs\SMSGo\SMSGoError;

$apiKey = getenv('SMSGO_KEY');
if ($apiKey === false || $apiKey === '') {
    fwrite(STDERR, "Defina SMSGO_KEY no ambiente.\n");
    exit(1);
}

$phone = $argv[1] ?? '+5511999990000';
$message = $argv[2] ?? 'Olá do SMSGo';

$client = new Client($apiKey);

try {
    $result = $client->send(phone: $phone, message: $message);
    printf("Enviado: id=%s status=%s%s\n", $result->id, $result->status, $result->test ? ' (teste)' : '');
} catch (SMSGoError $e) {
    fwrite(STDERR, sprintf("Erro %d/%s: %s\n", $e->status, $e->code, $e->getMessage()));
    exit(1);
}
