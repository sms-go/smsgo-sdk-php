<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Http;

use RuntimeException;

/**
 * Falha de rede/transporte (DNS, conexão, timeout).
 *
 * O cliente converte isto num {@see \Orynlabs\SMSGo\SMSGoError} com
 * `status = 0` e `code = 'network_error'`.
 */
final class TransportException extends RuntimeException
{
}
