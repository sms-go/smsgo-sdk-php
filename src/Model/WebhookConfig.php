<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Model;

/**
 * Configuração do webhook de saída (`getWebhook` / `setWebhook`).
 */
final class WebhookConfig
{
    /**
     * @param string|null $url URL configurada (`null` = desativado).
     * @param string|null $secret Segredo HMAC. Assine o corpo bruto p/ validar `X-SMSGo-Signature`.
     */
    public function __construct(
        public readonly ?string $url,
        public readonly ?string $secret,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            url: isset($data['url']) ? (string) $data['url'] : null,
            secret: isset($data['secret']) ? (string) $data['secret'] : null,
        );
    }
}
