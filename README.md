# orynlabs/smsgo

[![Packagist](https://img.shields.io/packagist/v/orynlabs/smsgo.svg)](https://packagist.org/packages/orynlabs/smsgo)
[![PHP](https://img.shields.io/packagist/php-v/orynlabs/smsgo.svg)](https://packagist.org/packages/orynlabs/smsgo)
[![license](https://img.shields.io/packagist/l/orynlabs/smsgo.svg)](./LICENSE)

SDK oficial **PHP** para a [SMSGo](https://smsgo.com.br) — a API de SMS simples para o Brasil. Envie **OTP/2FA, alertas transacionais e campanhas** com poucas linhas de código.

- ⚡ **Integra em minutos** — autenticação cuidada pra você (sem ritual de token manual).
- 💸 **Sem mensalidade** — créditos pré-pagos que não expiram, preço em real.
- 🇧🇷 **Brasil-first** — entrega para todas as operadoras, LGPD nativo.
- 🟢 **Zero dependências** — só `ext-json` + `ext-curl`. Tipado com DTOs `readonly`.
- 🎁 **R$ 10 grátis** ao criar a conta — dá pra testar sem cartão.

> Nova conta e chave em **[smsgo.com.br](https://smsgo.com.br)** → painel → **Minha conta → API**.

## Requisitos

- PHP **8.1+**
- Extensões `ext-json` e `ext-curl`

## Instalação

```bash
composer require orynlabs/smsgo
```

## Começo rápido

```php
use Orynlabs\SMSGo\Client;

$client = new Client(getenv('SMSGO_KEY'));

$result = $client->send(
    phone: '+5511999990000',
    message: 'Olá do SMSGo',
);

echo $result->id . ' ' . $result->status; // -> "a1b2c3...", "queued"
```

Você passa só a `apiKey`. O SDK troca a chave por um token Bearer (válido 48h), guarda em cache e renova sozinho quando expira (ou no primeiro `401`).

O construtor aceita opções:

```php
$client = new Client(getenv('SMSGO_KEY'), [
    'baseUrl'   => 'https://api.smsgo.com.br', // default; só mexa se a SMSGo orientar
    'timeout'   => 30,                          // segundos
    // 'transport' => $meuTransport,            // injeta um Http\Transport (testes)
]);
```

## Enviar um OTP (2FA)

```php
$code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

$client->send(
    phone: $user->phone,
    message: "Seu código SMSGo é {$code}. Válido por 5 minutos.",
);
// guarde $code (com TTL) e compare na verificação
```

## Envio em massa

```php
$client->sendBulk(
    messages: [
        ['phone' => '+5511999990000', 'message' => 'Oi, Ana!'],
        ['phone' => '+5521988887777', 'message' => 'Oi, Bruno!'],
    ],
    urlCallback: 'https://seuapp.com/webhooks/smsgo', // status de entrega (opcional)
);
```

## Consultar envios

```php
$page = $client->list(page: 1);         // Paginated<SendListItem> — { meta, data }
$one  = $client->get('a1b2c3-...');     // SendDetail + summary { total, delivered, failed, inProgress, done }

// Acompanhar um envio grande sem baixar tudo — números por bucket, paginado:
$failed = $client->getNumbers('a1b2c3-...', status: 'failed', page: 1);
```

## Modo de teste (sandbox)

Use a **chave de teste** (prefixo `test_`, no painel → Minha conta → API) como `apiKey`. Nada muda no código: os envios **não debitam saldo nem são despachados de verdade**, as respostas são idênticas às de produção (com `test: true`) e os webhooks disparam com o mesmo flag.

```php
$sandbox = new Client(getenv('SMSGO_TEST_KEY'));
$r = $sandbox->send(phone: '+5511999990000', message: 'Teste');
$r->test; // true

$sandbox->resolveMode(); // "test"  (ou $sandbox->mode() após a 1ª chamada)
```

## Saldo e catálogo

```php
$balance = $client->getBalance();  // Balance { balance, currency, company }
echo $balance->balance;            // 9.3

$types = $client->getSmsTypes();   // list<SmsTypeItem> { id, name, price, sale } — id vai em smsTypeId
```

## Comprar créditos (off-session)

Cobra um **cartão salvo** sem abrir o painel (o cartão é cadastrado no painel via Stripe; a API só cobra um já salvo).

```php
$plans = $client->billing->plans(); // list<Plan> — pacotes por faixa
$cards = $client->billing->cards(); // list<Card> — 4 últimos dígitos

$receipt = $client->billing->purchase(quantity: 5000 /*, planId:, cardId:, coupon: */);
$receipt->status; // 'succeeded' já creditou o saldo | 'processing' confirma via webhook

$invoices = $client->billing->invoices(page: 1);
```

> **Idempotência:** cada `purchase` gera uma cobrança nova. Em timeout, consulte `billing->invoices()` antes de repetir — **não faça retry cego**. O endpoint tem rate-limit estrito (6/min).

## Recarga automática + alerta de saldo

```php
$client->setAutoRecharge(
    enabled: true,
    threshold: 5,        // recarrega quando o saldo ≤ R$ 5
    planQuantity: 5000,  // créditos por recarga
    cardId: '<uuid>',    // obrigatório p/ ligar
    alertEnabled: true,
    alertThreshold: 15,  // e-mail quando o saldo ≤ R$ 15
);

$cfg = $client->getAutoRecharge();
```

## Webhooks de saída (DLR + respostas)

```php
// Define a URL que recebe `sms.status` (DLR) e `sms.reply` (resposta). Guarde o secret.
$cfg = $client->setWebhook(url: 'https://seuapp.com/webhooks/smsgo');
$cfg->url; $cfg->secret;

$client->setWebhook(rotateSecret: true); // gira o segredo
$client->setWebhook(url: '');            // desativa
```

Cada requisição traz `X-SMSGo-Signature: sha256=<hmac>` — o HMAC-SHA256 do **corpo bruto** com o seu `secret`. **Valide sempre** com o helper `Webhook::verifySignature()` (comparação em tempo constante):

```php
use Orynlabs\SMSGo\Webhook;

$rawBody   = file_get_contents('php://input');           // bytes exatos — não re-serialize!
$signature = $_SERVER['HTTP_X_SMSGO_SIGNATURE'] ?? null;

if (! Webhook::verifySignature($rawBody, $signature, $secret)) {
    http_response_code(401);
    exit;
}
```

Veja [`examples/receive-dlr-webhook.php`](./examples/receive-dlr-webhook.php) para o handler completo.

## Contatos e listas

```php
$listId = $client->lists->create(name: 'Clientes VIP')->id;

$contactId = $client->contacts->create(
    fullName: 'Ana Souza',
    phone: '+5511999990000',
    email: 'ana@exemplo.com',
    lists: [$listId],
);

$client->contacts->list(page: 1, search: 'ana'); // Paginated { meta, data }
$client->contacts->update($contactId, fullName: 'Ana S.', phone: '+5511999990000');
$client->contacts->delete($contactId);
```

## Tratamento de erros

Toda resposta não-2xx vira um `SMSGoError` com `status` e um `code` estável:

```php
use Orynlabs\SMSGo\Client;
use Orynlabs\SMSGo\SMSGoError;

try {
    $client->send(phone: '+5511999990000', message: 'Olá');
} catch (SMSGoError $e) {
    switch ($e->code) {
        case 'insufficient_balance': // 402 — sem saldo
        case 'rate_limited':         // 429 — muitas requisições (veja $e->details)
        case 'validation_error':     // 422 — dados inválidos ($e->errors por campo)
        default:
            error_log("{$e->status} {$e->code} {$e->getMessage()}");
    }
}
```

Em falhas de validação (422), `$e->errors` traz o detalhe por campo (`list<array{field, message}>`). Falhas de rede/transporte viram `SMSGoError` com `status = 0` e `code = 'network_error'`.

| `code`                     | HTTP | Significado                          |
| -------------------------- | ---- | ------------------------------------ |
| `validation_error`         | 422  | Dados do request inválidos           |
| `unauthorized`             | 401  | Chave/token inválido                 |
| `insufficient_balance`     | 402  | Saldo insuficiente                   |
| `provider_out_of_stock`    | 409  | Estoque do provedor indisponível     |
| `rate_limited`             | 429  | Limite de requisições atingido       |
| `card_declined`            | 402  | Cartão recusado na compra            |
| `authentication_required`  | 402  | Cartão exige autenticação (SCA)      |
| `card_required`            | 400  | Nenhum cartão apto à cobrança        |
| `payment_unavailable`      | 503  | Gateway de pagamento indisponível    |
| `network_error`            | 0    | Falha de rede/transporte (cliente)   |

## Referência da API

### `new Client(string $apiKey, array $options = [])`

| Opção       | Tipo                        | Default                    | Descrição                          |
| ----------- | --------------------------- | -------------------------- | ---------------------------------- |
| `apiKey`    | `string`                    | —                          | **Obrigatório.** Sua SMSGo-key (aceita `test_…`). |
| `baseUrl`   | `string`                    | `https://api.smsgo.com.br` | Não precisa mexer; só se a SMSGo orientar. |
| `timeout`   | `int`                       | `30`                       | Timeout total, em segundos.        |
| `transport` | `Http\Transport`            | `Http\CurlTransport`       | Injete um transporte (ex.: testes). |

### Métodos

**SMS**

- `send(phone, message, schedule?, reference?, from?, smsTypeId?)` → `SendResult`
- `sendBulk(messages, urlCallback?, flashSms?, smsTypeId?)` → `SendResult`
- `list(page = 1)` → `Paginated<SendListItem>`
- `get(id)` → `SendDetail` (com `summary`)
- `getNumbers(id, status?, page?)` → `Paginated<SendNumberItem>` (`status`: `delivered` · `failed` · `in_progress`)
- `getSmsTypes()` → `list<SmsTypeItem>`

**Conta**

- `getBalance()` → `Balance`
- `getAutoRecharge()` / `setAutoRecharge(...)` → `AutoRechargeConfig`
- `getWebhook()` / `setWebhook(url?, rotateSecret?)` → `WebhookConfig`
- `mode()` → `'live' | 'test' | null` · `resolveMode()` → `'live' | 'test'`

**Faturamento** (`$client->billing`)

- `plans()` → `list<Plan>` · `cards()` → `list<Card>` · `invoices(page?, perPage?)` → `Paginated<InvoiceItem>`
- `purchase(quantity?, planId?, cardId?, coupon?)` → `PurchaseResult`

**Contatos** (`$client->contacts`)

- `list(page, perPage?, search?, title?)` · `create(fullName, phone, email?, lists?)` → `string` (uuid)
- `get(id)` → `ContactDetail` · `update(id, fullName, phone, email?, lists?)` → `string` · `delete(id)` → `array{message}`

**Listas** (`$client->lists`)

- `list(page, perPage?, title?)` · `create(name)` → `ListResult` · `get(id)` → `ListResult`
- `update(id, name)` → `ListResult` · `delete(id)` → `array{message}`

**Webhook** (estático)

- `Webhook::verifySignature(rawBody, signatureHeader, secret)` → `bool`

> Referência de máquina completa: **[smspulse.apidog.io](https://smspulse.apidog.io/)** — importável no Apidog/Postman.

## Exemplos

Na pasta [`examples/`](./examples):

```bash
composer install
SMSGO_KEY=suachave php examples/otp.php +5511999990000
```

- [`send.php`](./examples/send.php) — envio simples
- [`otp.php`](./examples/otp.php) — código OTP/2FA
- [`status.php`](./examples/status.php) — envio em massa + consulta de status
- [`balance.php`](./examples/balance.php) — saldo + catálogo de tipos de SMS
- [`buy-credits.php`](./examples/buy-credits.php) — compra off-session + recarga automática
- [`configure-webhook.php`](./examples/configure-webhook.php) — configura o webhook de saída
- [`receive-dlr-webhook.php`](./examples/receive-dlr-webhook.php) — recebe callbacks de entrega (DLR) e valida a assinatura

## Desenvolvimento

```bash
composer install
composer test      # PHPUnit
composer phpstan   # análise estática (nível 8)
composer cs-check  # PHP-CS-Fixer (dry-run)
```

## Migrando da TotalVoice / Twilio?

SMSGo foca em **DX simples e preço em real**. Sem cadastro de remetente pra começar, sem cobrança em dólar, créditos que não expiram. Documentação completa da API: **[smspulse.apidog.io](https://smspulse.apidog.io/)**.

## Licença

MIT © SMSGo
