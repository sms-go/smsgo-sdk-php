<?php

declare(strict_types=1);

/**
 * Envio de um código OTP/2FA de 6 dígitos.
 *
 * Uso:
 *   SMSGO_KEY=suachave php examples/otp.php +5511999990000
 *
 * Guarde `code` (com TTL curto) e compare na verificação.
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
$code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

$client = new Client($apiKey);

try {
    $result = $client->send(
        phone: $phone,
        message: "Seu código SMSGo é {$code}. Válido por 5 minutos.",
    );
    printf("OTP enviado: id=%s (guarde o código %s com TTL)\n", $result->id, $code);
} catch (SMSGoError $e) {
    fwrite(STDERR, sprintf("Erro %d/%s: %s\n", $e->status, $e->code, $e->getMessage()));
    exit(1);
}
