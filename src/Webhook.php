<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo;

/**
 * Utilitário de verificação de assinatura dos webhooks de saída da SMSGo.
 *
 * Cada requisição de webhook traz o header `X-SMSGo-Signature: sha256=<hmac>`,
 * onde `<hmac>` é o HMAC-SHA256 do **corpo bruto** (bytes exatos recebidos) usando
 * o seu `secret`. Valide SEMPRE antes de confiar no payload.
 */
final class Webhook
{
    /**
     * Verifica a assinatura de um webhook em tempo constante.
     *
     * Calcula `expected = "sha256=" + hex(HMAC_SHA256(rawBody, secret))` e compara
     * com o header recebido usando {@see hash_equals} (à prova de timing attack).
     *
     * Retorna `false` — sem lançar exceção — para header nulo ou vazio, e para
     * qualquer adulteração (byte alterado no corpo, segredo errado, assinatura
     * truncada).
     *
     * @param string $rawBody Corpo bruto EXATO da requisição (não re-serialize o JSON).
     * @param string|null $signatureHeader Valor do header `X-SMSGo-Signature`.
     * @param string $secret Segredo do webhook (retornado por `setWebhook`/`getWebhook`).
     * @param int|null $toleranceSeconds Se informado, além da assinatura exige que o
     *   `sentAt` do corpo esteja dentro desta janela (anti-replay). Sem ele, o
     *   comportamento é idêntico ao anterior (só assinatura). Deduplicar pelo `id`
     *   do corpo continua a cargo do receptor.
     */
    public static function verifySignature(
        string $rawBody,
        ?string $signatureHeader,
        string $secret,
        ?int $toleranceSeconds = null
    ): bool {
        if ($signatureHeader === null || $signatureHeader === '') {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);

        if (!hash_equals($expected, $signatureHeader)) {
            return false;
        }

        if ($toleranceSeconds !== null) {
            $payload = json_decode($rawBody, true);
            $sentAt = is_array($payload) && isset($payload['sentAt']) ? $payload['sentAt'] : null;
            if (!is_string($sentAt)) {
                return false;
            }
            $ts = strtotime($sentAt);
            if ($ts === false) {
                return false;
            }
            if (abs(time() - $ts) > $toleranceSeconds) {
                return false;
            }
        }

        return true;
    }
}
