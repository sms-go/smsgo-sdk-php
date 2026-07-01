# Changelog

Todas as mudanças relevantes deste pacote são documentadas aqui.
O formato segue [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/)
e o versionamento segue [SemVer](https://semver.org/lang/pt-BR/).

## [0.3.0] - 2026-07-01

### Adicionado

- **Primeira versão do SDK oficial para PHP** (`orynlabs/smsgo`), em paridade com o
  SDK Node `@orynlabs/smsgo`. Requer **PHP 8.1+**, `ext-json` e `ext-curl`. **Zero
  dependências de runtime.**
- **Autenticação de 2 passos transparente:** troca a `SMSGo-key` por um token Bearer
  (validade 48h), cacheia em memória (renova aos 47h) e faz refresh automático no `401`
  (retry único).
- **Modo de teste (sandbox):** chaves `test_…` selecionam o sandbox de forma
  transparente; o modo detectado fica em `Client::mode()` / `Client::resolveMode()`.
- **Envio de SMS:** `send()`, `sendBulk()`, `list()`, `get()`, `getNumbers()`,
  `getSmsTypes()`.
- **Conta:** `getBalance()`, `getAutoRecharge()`, `setAutoRecharge()`, `getWebhook()`,
  `setWebhook()`.
- **Namespaces** `billing` (`plans()`, `cards()`, `invoices()`, `purchase()`),
  `contacts` (`list`/`create`/`get`/`update`/`delete`) e `lists` (idem), como
  propriedades públicas `readonly` do cliente.
- **DTOs tipados** (classes `readonly` com `::fromArray()`): `SendResult`, `Balance`,
  `SmsTypeItem`, `AutoRechargeConfig`, `WebhookConfig`, `Plan`, `Card`, `InvoiceItem`,
  `ContactDetail`, `ListResult`, `Paginated`, `PaginationMeta`, `SendDetail`,
  `SendListItem`, `SendNumberItem`, `SendNumberDetail`, `SendSummary`, `PurchaseResult`.
- **Erro único** `SMSGoError` (estende `RuntimeException`) com `status`, `code`,
  `details` (corpo bruto) e `errors` (por campo, em `422`). Falha de rede/transporte →
  `status = 0`, `code = 'network_error'`.
- **Verificação de webhook:** `Orynlabs\SMSGo\Webhook::verifySignature()`
  (HMAC-SHA256 + comparação em tempo constante via `hash_equals`).
- **Transporte injetável** (`Orynlabs\SMSGo\Http\Transport`) para testes;
  implementação padrão `CurlTransport` (não segue redirects).
