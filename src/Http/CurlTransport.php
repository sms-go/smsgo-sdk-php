<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Http;

/**
 * Transporte HTTP padrão, baseado na extensão cURL.
 *
 * Não segue redirects (por segurança) e devolve a resposta bruta sem interpretar
 * status HTTP: a tradução para {@see \Orynlabs\SMSGo\SMSGoError} é feita pelo cliente.
 */
final class CurlTransport implements Transport
{
    public function send(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        int $timeout,
    ): TransportResponse {
        $ch = curl_init();
        if ($ch === false) {
            throw new TransportException('SMSGo: falha ao inicializar o cURL.');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $responseHeaders = [];

        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_HEADERFUNCTION => function ($_ch, string $line) use (&$responseHeaders): int {
                $len = strlen($line);
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $key = strtolower(trim($parts[0]));
                    $responseHeaders[$key] = trim($parts[1]);
                }

                return $len;
            },
        ];

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($ch, $options);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new TransportException($error !== '' ? $error : 'SMSGo: falha de rede.');
        }

        /** @var int $status */
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return new TransportResponse($status, $responseHeaders, (string) $raw);
    }
}
