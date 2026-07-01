<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo;

use RuntimeException;

/**
 * Erro padronizado lançado pelo SDK em respostas não-2xx.
 *
 * Espelha o `SMSGoError` do SDK Node: expõe o `status` HTTP, um `code` estável
 * (ex.: `validation_error`, `insufficient_balance`, `rate_limited`), o corpo bruto
 * da resposta em `details` e, em falhas de validação (422), a lista `errors` com
 * o detalhe por campo.
 *
 * Falhas de rede/transporte produzem `status = 0` e `code = 'network_error'`.
 */
final class SMSGoError extends RuntimeException
{
    /**
     * Código estável do erro (ex.: `validation_error`, `insufficient_balance`).
     *
     * Sobrescreve `Exception::$code` (herdada sem tipo nativo e readwrite), por
     * isso é declarada aqui **sem tipo nativo e sem `readonly`** — do contrário o
     * PHP/PHPStan acusam "readonly overrides readwrite" e "should not have a
     * native type". O tipo real é `string`, definido via PHPDoc.
     *
     * @var string
     */
    public $code; // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint

    /**
     * @param int $status Status HTTP (0 em falha de rede/transporte).
     * @param string $code Código estável do erro.
     * @param mixed $details Corpo bruto da resposta de erro (array, string ou null).
     * @param list<array{field: string, message: string}>|null $errors Detalhe por campo (422).
     */
    public function __construct(
        string $message,
        public readonly int $status,
        string $code,
        public readonly mixed $details = null,
        public readonly ?array $errors = null,
    ) {
        parent::__construct($message);
        $this->code = $code;
    }
}
