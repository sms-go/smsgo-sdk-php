<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Tests;

use Orynlabs\SMSGo\Http\Transport;
use Orynlabs\SMSGo\Http\TransportException;
use Orynlabs\SMSGo\Http\TransportResponse;

/**
 * Transporte falso para testes: devolve respostas enfileiradas e registra as
 * requisições recebidas. Uma resposta pode ser um {@see TransportResponse} pronto
 * ou uma {@see \Throwable} a ser lançada (para simular falha de rede).
 */
final class FakeTransport implements Transport
{
    /** @var list<TransportResponse|\Throwable> */
    private array $queue = [];

    /**
     * @var list<array{method: string, url: string, headers: array<string, string>, body: string|null, timeout: int}>
     */
    public array $requests = [];

    /**
     * Enfileira uma resposta JSON.
     *
     * @param array<string, mixed>|list<mixed> $json
     * @param array<string, string> $headers
     */
    public function pushJson(int $status, array $json, array $headers = []): void
    {
        $body = json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->queue[] = new TransportResponse($status, $headers, $body === false ? '' : $body);
    }

    /**
     * Enfileira uma resposta com corpo bruto (texto ou vazio).
     *
     * @param array<string, string> $headers
     */
    public function pushRaw(int $status, string $body, array $headers = []): void
    {
        $this->queue[] = new TransportResponse($status, $headers, $body);
    }

    /** Enfileira uma falha de rede/transporte. */
    public function pushNetworkError(string $message = 'connection refused'): void
    {
        $this->queue[] = new TransportException($message);
    }

    public function send(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        int $timeout,
    ): TransportResponse {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
            'timeout' => $timeout,
        ];

        if ($this->queue === []) {
            throw new \LogicException('FakeTransport: fila de respostas vazia para ' . $method . ' ' . $url);
        }

        $next = array_shift($this->queue);
        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }

    /**
     * @return array{method: string, url: string, headers: array<string, string>, body: string|null, timeout: int}
     */
    public function requestAt(int $index): array
    {
        if (!isset($this->requests[$index])) {
            throw new \OutOfRangeException('FakeTransport: nenhuma requisição no índice ' . $index);
        }

        return $this->requests[$index];
    }

    /**
     * Corpo JSON decodificado da requisição no índice dado.
     *
     * @return array<string, mixed>
     */
    public function jsonBodyAt(int $index): array
    {
        $body = $this->requestAt($index)['body'];
        if ($body === null || $body === '') {
            return [];
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function requestCount(): int
    {
        return count($this->requests);
    }
}
