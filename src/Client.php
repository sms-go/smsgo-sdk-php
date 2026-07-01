<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo;

use Orynlabs\SMSGo\Http\CurlTransport;
use Orynlabs\SMSGo\Http\Transport;
use Orynlabs\SMSGo\Http\TransportException;
use Orynlabs\SMSGo\Http\TransportResponse;
use Orynlabs\SMSGo\Model\AutoRechargeConfig;
use Orynlabs\SMSGo\Model\Balance;
use Orynlabs\SMSGo\Model\Paginated;
use Orynlabs\SMSGo\Model\SendDetail;
use Orynlabs\SMSGo\Model\SendListItem;
use Orynlabs\SMSGo\Model\SendNumberItem;
use Orynlabs\SMSGo\Model\SendResult;
use Orynlabs\SMSGo\Model\SmsTypeItem;
use Orynlabs\SMSGo\Model\WebhookConfig;
use Orynlabs\SMSGo\Resource\Billing;
use Orynlabs\SMSGo\Resource\Contacts;
use Orynlabs\SMSGo\Resource\Lists;

/**
 * SDK oficial da SMSGo para PHP.
 *
 * Cuida da autenticação de 2 passos (SMSGo-key → token Bearer de 48h) de forma
 * transparente: você só passa a `apiKey`. O token é buscado sob demanda, cacheado
 * em memória e renovado automaticamente quando expira ou retorna 401.
 *
 * Cobre toda a API pública `v1`: envio de SMS, consulta de envios, catálogo de
 * tipos, saldo, faturamento (compra off-session), recarga automática, webhooks
 * de saída, contatos e listas.
 *
 * @example
 * $client = new \Orynlabs\SMSGo\Client(getenv('SMSGO_KEY'));
 * $result = $client->send(phone: '+5511999990000', message: 'Olá do SMSGo');
 *
 * @example
 * // Modo de teste (sandbox): basta usar a chave `test_…` — nada muda no código.
 * $sandbox = new \Orynlabs\SMSGo\Client(getenv('SMSGO_TEST_KEY'));
 */
final class Client
{
    /** Base padrão da API. */
    private const DEFAULT_BASE_URL = 'https://api.smsgo.com.br';

    /** Token vale 48h; renova com folga aos 47h. */
    private const TOKEN_TTL_SECONDS = 47 * 60 * 60;

    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly int $timeout;
    private readonly Transport $transport;

    private ?string $token = null;
    private int $tokenExpiresAt = 0;

    /** Modo de autenticação da chave atual (`live`/`test`), `null` antes da 1ª chamada. */
    private ?string $authMode = null;

    /** Namespace de contatos (CRUD). */
    public readonly Contacts $contacts;

    /** Namespace de listas (CRUD). */
    public readonly Lists $lists;

    /** Namespace de faturamento (pacotes, cartões, faturas, compra). */
    public readonly Billing $billing;

    /**
     * @param string $apiKey Chave permanente da conta (painel → Minha conta → API). Aceita `test_…` (sandbox).
     * @param array{baseUrl?: string, timeout?: int, transport?: Transport} $options
     */
    public function __construct(string $apiKey, array $options = [])
    {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('SMSGo: apiKey é obrigatório.');
        }

        $this->apiKey = $apiKey;

        $baseUrl = $options['baseUrl'] ?? self::DEFAULT_BASE_URL;
        $this->baseUrl = rtrim($baseUrl, '/');

        $this->timeout = $options['timeout'] ?? 30;
        $this->transport = $options['transport'] ?? new CurlTransport();

        // Os namespaces compartilham o transporte (auth/refresh/erros) do cliente.
        $req = fn (string $method, string $path, ?array $payload = null): mixed
            => $this->request($method, $path, $payload);

        $this->contacts = new Contacts($req);
        $this->lists = new Lists($req);
        $this->billing = new Billing($req);
    }

    /**
     * Modo da chave atual (`live` ou `test`), conhecido após a 1ª chamada
     * autenticada. Retorna `null` antes disso — use `resolveMode()` para forçar.
     */
    public function mode(): ?string
    {
        return $this->authMode;
    }

    /** Garante um token e devolve o modo (`live`/`test`) da chave. */
    public function resolveMode(): string
    {
        $this->ensureToken();

        return $this->authMode ?? 'live';
    }

    /* --- SMS ---------------------------------------------------------------- */

    /**
     * Envia um SMS para um número.
     *
     * @param string $phone Número em formato internacional E.164, ex.: +5511999990000.
     * @param string $message Texto da mensagem (1–1600 caracteres; limite real depende do provedor).
     * @param string|null $schedule Agendamento ISO-8601 (opcional).
     * @param string|null $reference Identificador próprio do cliente, ecoado nos webhooks (opcional).
     * @param string|null $from Remetente, conforme provedor (opcional).
     * @param int|null $smsTypeId Tipo de SMS para precificação (opcional). Veja `getSmsTypes()`.
     */
    public function send(
        string $phone,
        string $message,
        ?string $schedule = null,
        ?string $reference = null,
        ?string $from = null,
        ?int $smsTypeId = null,
    ): SendResult {
        $res = $this->request('POST', '/v1/sms/send/single', [
            'phone' => $phone,
            'message' => $message,
            'schedule' => $schedule,
            'reference' => $reference,
            'from' => $from,
            'sms_type_id' => $smsTypeId,
        ]);

        return SendResult::fromArray(is_array($res) ? $res : []);
    }

    /**
     * Envia várias mensagens numa única transação (até 5000).
     *
     * @param list<array{phone: string, message: string, schedule?: string, reference?: string, from?: string}> $messages
     * @param string|null $urlCallback URL para callback de status de entrega (opcional).
     * @param bool|null $flashSms Flash SMS, se o provedor suportar (opcional).
     * @param int|null $smsTypeId Tipo de SMS para precificação (opcional).
     */
    public function sendBulk(
        array $messages,
        ?string $urlCallback = null,
        ?bool $flashSms = null,
        ?int $smsTypeId = null,
    ): SendResult {
        $res = $this->request('POST', '/v1/sms/send/multiple', [
            'messages' => $messages,
            'urlCallback' => $urlCallback,
            'flashSms' => $flashSms,
            'sms_type_id' => $smsTypeId,
        ]);

        return SendResult::fromArray(is_array($res) ? $res : []);
    }

    /**
     * Lista os envios da conta (paginado).
     *
     * @return Paginated<SendListItem>
     */
    public function list(int $page = 1): Paginated
    {
        $res = $this->request('GET', '/v1/sms/list' . $this->buildQuery(['page' => $page]));

        return Paginated::fromArray(
            is_array($res) ? $res : [],
            static fn (array $item): SendListItem => SendListItem::fromArray($item),
        );
    }

    /** Detalha um envio pelo seu UUID (inclui `summary` de acompanhamento). */
    public function get(string $id): SendDetail
    {
        $res = $this->request('GET', '/v1/sms/' . rawurlencode($id) . '/show');

        return SendDetail::fromArray(is_array($res) ? $res : []);
    }

    /**
     * Números de um envio, paginado e filtrável por bucket de status.
     *
     * @param string|null $status Filtra por bucket: `delivered` · `failed` · `in_progress`.
     *
     * @return Paginated<SendNumberItem>
     */
    public function getNumbers(string $id, ?string $status = null, ?int $page = null): Paginated
    {
        $res = $this->request(
            'GET',
            '/v1/sms/' . rawurlencode($id) . '/numbers' . $this->buildQuery([
                'status' => $status,
                'page' => $page,
            ]),
        );

        return Paginated::fromArray(
            is_array($res) ? $res : [],
            static fn (array $item): SendNumberItem => SendNumberItem::fromArray($item),
        );
    }

    /**
     * Catálogo de tipos de SMS ativos (o `id` é o valor de `smsTypeId`).
     *
     * @return list<SmsTypeItem>
     */
    public function getSmsTypes(): array
    {
        $res = $this->request('GET', '/v1/sms-types');
        $data = is_array($res) && is_array($res['data'] ?? null) ? $res['data'] : [];

        return array_values(array_map(
            static fn (mixed $item): SmsTypeItem => SmsTypeItem::fromArray(is_array($item) ? $item : []),
            $data,
        ));
    }

    /* --- Conta -------------------------------------------------------------- */

    /** Saldo monetário (R$) + dados básicos da conta. */
    public function getBalance(): Balance
    {
        $res = $this->request('GET', '/v1/account/balance');

        return Balance::fromArray(is_array($res) ? $res : []);
    }

    /** Lê a configuração de recarga automática + alerta de saldo. */
    public function getAutoRecharge(): AutoRechargeConfig
    {
        $res = $this->request('GET', '/v1/account/auto-recharge');

        return AutoRechargeConfig::fromArray(is_array($res) ? $res : []);
    }

    /**
     * Atualiza recarga automática + alerta de saldo. Para LIGAR a recarga é
     * obrigatório `cardId` + `planQuantity`.
     *
     * @param bool|null $enabled Liga/desliga a recarga automática.
     * @param float|int|null $threshold Recarrega quando o saldo for ≤ este valor (R$).
     * @param int|null $planQuantity Créditos comprados a cada recarga.
     * @param string|null $cardId UUID do cartão salvo.
     * @param bool|null $alertEnabled Liga/desliga o alerta de saldo.
     * @param float|int|null $alertThreshold Envia e-mail quando o saldo for ≤ este valor (R$).
     */
    public function setAutoRecharge(
        ?bool $enabled = null,
        float|int|null $threshold = null,
        ?int $planQuantity = null,
        ?string $cardId = null,
        ?bool $alertEnabled = null,
        float|int|null $alertThreshold = null,
    ): AutoRechargeConfig {
        $res = $this->request('PUT', '/v1/account/auto-recharge', [
            'enabled' => $enabled,
            'threshold' => $threshold,
            'plan_quantity' => $planQuantity,
            'card_id' => $cardId,
            'alert_enabled' => $alertEnabled,
            'alert_threshold' => $alertThreshold,
        ]);

        return AutoRechargeConfig::fromArray(is_array($res) ? $res : []);
    }

    /** Lê a URL e o segredo do webhook de saída. */
    public function getWebhook(): WebhookConfig
    {
        $res = $this->request('GET', '/v1/account/webhook');

        return WebhookConfig::fromArray(is_array($res) ? $res : []);
    }

    /**
     * Define o webhook de saída (DLR + respostas). String vazia em `url` desativa;
     * use `rotateSecret` para girar o segredo de assinatura.
     *
     * @param string|null $url URL HTTPS do seu endpoint. String vazia desativa o webhook.
     * @param bool|null $rotateSecret Gera um novo segredo de assinatura.
     */
    public function setWebhook(?string $url = null, ?bool $rotateSecret = null): WebhookConfig
    {
        $res = $this->request('PUT', '/v1/account/webhook', [
            'url' => $url,
            'rotate_secret' => $rotateSecret,
        ]);

        return WebhookConfig::fromArray(is_array($res) ? $res : []);
    }

    /* --- Auth interna ------------------------------------------------------- */

    /** Troca a SMSGo-key por um token Bearer; cacheia até expirar. */
    private function ensureToken(bool $forceRefresh = false): string
    {
        $now = time();
        if (!$forceRefresh && $this->token !== null && $now < $this->tokenExpiresAt) {
            return $this->token;
        }

        try {
            $res = $this->transport->send(
                'GET',
                $this->baseUrl . '/v1/auth/token',
                [
                    'SMSGo-key' => $this->apiKey,
                    'Accept' => 'application/json',
                ],
                null,
                $this->timeout,
            );
        } catch (TransportException $e) {
            throw new SMSGoError($e->getMessage(), 0, 'network_error');
        }

        $body = $this->parseBody($res);
        $token = is_array($body) ? ($body['token'] ?? null) : null;

        if ($res->status < 200 || $res->status >= 300 || !is_string($token) || $token === '') {
            throw $this->toError($res->status, $body, 'Falha ao autenticar a SMSGo-key.');
        }

        $this->token = $token;
        $mode = is_array($body) ? ($body['mode'] ?? null) : null;
        $this->authMode = $mode === 'test' ? 'test' : 'live';
        $this->tokenExpiresAt = $now + self::TOKEN_TTL_SECONDS;

        return $this->token;
    }

    /**
     * Executa uma chamada autenticada, com refresh-and-retry único no 401.
     *
     * @param array<string, mixed>|null $payload
     */
    private function request(string $method, string $path, ?array $payload = null, bool $isRetry = false): mixed
    {
        $token = $this->ensureToken();

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ];

        $body = null;
        if ($payload !== null) {
            $headers['Content-Type'] = 'application/json';
            $body = self::encodeJson(self::stripNull($payload));
        }

        try {
            $res = $this->transport->send($method, $this->baseUrl . $path, $headers, $body, $this->timeout);
        } catch (TransportException $e) {
            throw new SMSGoError($e->getMessage(), 0, 'network_error');
        }

        // Token expirado/revogado: renova uma vez e tenta de novo.
        if ($res->status === 401 && !$isRetry) {
            $this->ensureToken(true);

            return $this->request($method, $path, $payload, true);
        }

        $parsed = $this->parseBody($res);
        if ($res->status < 200 || $res->status >= 300) {
            throw $this->toError($res->status, $parsed);
        }

        return $parsed;
    }

    /**
     * Interpreta o corpo: JSON → array/valor; vazio → null; não-JSON → texto cru.
     */
    private function parseBody(TransportResponse $res): mixed
    {
        $text = $res->body;
        if ($text === '') {
            return null;
        }

        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $text;
    }

    /**
     * Constrói um {@see SMSGoError} a partir de uma resposta não-2xx.
     */
    private function toError(int $status, mixed $body, string $fallbackMessage = 'Erro na requisição.'): SMSGoError
    {
        $rawCode = is_array($body) ? ($body['code'] ?? null) : null;
        if (is_string($rawCode) && $rawCode !== '') {
            $code = $rawCode;
        } elseif (is_int($rawCode) || is_float($rawCode)) {
            // Controllers legados podem devolver código numérico (ex.: 1006);
            // espelha o String(body.code) do SDK Node.
            $code = (string) $rawCode;
        } else {
            $code = self::httpCodeName($status);
        }

        if (is_array($body) && isset($body['message']) && is_string($body['message']) && $body['message'] !== '') {
            $message = $body['message'];
        } elseif (is_string($body) && $body !== '') {
            $message = $body;
        } else {
            $message = $fallbackMessage;
        }

        $errors = null;
        if (is_array($body) && isset($body['errors']) && is_array($body['errors'])) {
            $errors = [];
            foreach ($body['errors'] as $err) {
                if (is_array($err)) {
                    $errors[] = [
                        'field' => (string) ($err['field'] ?? ''),
                        'message' => (string) ($err['message'] ?? ''),
                    ];
                }
            }
        }

        return new SMSGoError($message, $status, $code, $body, $errors);
    }

    /* --- Helpers ------------------------------------------------------------ */

    /**
     * Remove chaves com valor `null` (equivalente a `strip undefined` do Node).
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private static function stripNull(array $payload): array
    {
        $out = [];
        foreach ($payload as $key => $value) {
            if ($value !== null) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * Monta uma query string (`?a=1&b=2`), ignorando valores `null`.
     *
     * @param array<string, string|int|float|bool|null> $params
     */
    private function buildQuery(array $params): string
    {
        $pairs = [];
        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }
            $pairs[$key] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        return $pairs === [] ? '' : '?' . http_build_query($pairs);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function encodeJson(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new SMSGoError('SMSGo: falha ao serializar o corpo da requisição.', 0, 'network_error');
        }

        return $json;
    }

    private static function httpCodeName(int $status): string
    {
        return match ($status) {
            400 => 'bad_request',
            401 => 'unauthorized',
            402 => 'insufficient_balance',
            409 => 'provider_out_of_stock',
            422 => 'validation_error',
            429 => 'rate_limited',
            503 => 'payment_unavailable',
            default => 'http_' . $status,
        };
    }
}
