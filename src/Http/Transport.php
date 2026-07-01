<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Http;

/**
 * Contrato de transporte HTTP usado pelo cliente.
 *
 * A implementação padrão ({@see CurlTransport}) usa a extensão cURL. Os testes
 * injetam um transporte falso que devolve respostas prontas, sem tocar a rede.
 */
interface Transport
{
    /**
     * Executa uma requisição HTTP e devolve a resposta bruta.
     *
     * A implementação NÃO deve lançar exceção para status HTTP não-2xx — apenas
     * para falhas reais de rede/transporte ({@see TransportException}).
     *
     * @param string $method Verbo HTTP em maiúsculas (GET, POST, PUT, DELETE).
     * @param string $url URL absoluta.
     * @param array<string, string> $headers Cabeçalhos como pares nome => valor.
     * @param string|null $body Corpo bruto (JSON) ou null quando não há corpo.
     * @param int $timeout Timeout total, em segundos.
     *
     * @throws TransportException Em falha de rede/transporte.
     */
    public function send(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        int $timeout,
    ): TransportResponse;
}
