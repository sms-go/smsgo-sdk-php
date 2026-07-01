<?php

declare(strict_types=1);

/**
 * Recebe callbacks de entrega (DLR) e respostas (MO) da SMSGo, validando a
 * assinatura HMAC antes de confiar no payload.
 *
 * Sirva com o servidor embutido do PHP:
 *   SMSGO_WEBHOOK_SECRET=whsec_... php -S 0.0.0.0:8080 examples/receive-dlr-webhook.php
 *
 * A SMSGo envia POST com o header `X-SMSGo-Signature: sha256=<hmac>`, onde o HMAC
 * é calculado sobre o CORPO BRUTO. Nunca re-serialize o JSON antes de validar.
 */

require __DIR__ . '/../vendor/autoload.php';

use Orynlabs\SMSGo\Webhook;

$secret = getenv('SMSGO_WEBHOOK_SECRET');
if ($secret === false || $secret === '') {
    http_response_code(500);
    echo "Defina SMSGO_WEBHOOK_SECRET no ambiente.\n";
    return;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo "Method Not Allowed\n";
    return;
}

// Corpo BRUTO (bytes exatos recebidos).
$rawBody = file_get_contents('php://input');
if ($rawBody === false) {
    $rawBody = '';
}

$signature = $_SERVER['HTTP_X_SMSGO_SIGNATURE'] ?? null;

if (!Webhook::verifySignature($rawBody, is_string($signature) ? $signature : null, $secret)) {
    http_response_code(401);
    echo "Assinatura inválida\n";
    return;
}

/** @var array<string, mixed> $payload */
$payload = json_decode($rawBody, true) ?: [];
$event = is_string($payload['event'] ?? null) ? $payload['event'] : '';
$data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

// Em produção, ignore eventos de teste (sandbox).
if (($payload['test'] ?? false) === true) {
    // Evento de teste — apenas confirme o recebimento.
    http_response_code(200);
    echo "ok (teste)\n";
    return;
}

switch ($event) {
    case 'sms.status':
        // DLR: $data = { sendId, phone, status: delivered|failed|in_progress }
        error_log(sprintf('DLR %s -> %s', (string) ($data['phone'] ?? ''), (string) ($data['status'] ?? '')));
        break;
    case 'sms.reply':
        // MO: $data = { fromPhone, message, receivedAt }
        error_log(sprintf('Resposta de %s: %s', (string) ($data['fromPhone'] ?? ''), (string) ($data['message'] ?? '')));
        break;
}

// Responda 2xx rápido; processe de forma assíncrona se necessário.
http_response_code(200);
echo "ok\n";
