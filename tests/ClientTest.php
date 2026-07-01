<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Tests;

use Orynlabs\SMSGo\Client;
use Orynlabs\SMSGo\SMSGoError;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private function client(FakeTransport $transport, string $apiKey = 'live_key'): Client
    {
        return new Client($apiKey, [
            'baseUrl' => 'https://api.example.test',
            'transport' => $transport,
        ]);
    }

    public function testConstructorRejectsEmptyApiKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Client('');
    }

    public function testConstructorStripsTrailingSlashesFromBaseUrl(): void
    {
        $transport = new FakeTransport();
        $transport->pushJson(200, ['token' => 't', 'mode' => 'live']);
        $transport->pushJson(200, ['id' => 'a', 'quantity' => 1, 'status' => 'queued']);

        $client = new Client('k', ['baseUrl' => 'https://api.example.test///', 'transport' => $transport]);
        $client->send(phone: '+5511999990000', message: 'oi');

        $this->assertSame('https://api.example.test/v1/auth/token', $transport->requestAt(0)['url']);
        $this->assertSame('https://api.example.test/v1/sms/send/single', $transport->requestAt(1)['url']);
    }

    public function testTokenExchangeUsesSmsGoKeyHeaderAndCaches(): void
    {
        $transport = new FakeTransport();
        $transport->pushJson(200, ['token' => 'oat_abc', 'mode' => 'live']);
        $transport->pushJson(200, ['id' => 'a', 'quantity' => 1, 'status' => 'queued']);
        $transport->pushJson(200, ['id' => 'b', 'quantity' => 1, 'status' => 'queued']);

        $client = $this->client($transport);
        $client->send(phone: '+5511999990000', message: 'um');
        $client->send(phone: '+5511999990000', message: 'dois');

        // Auth once, then two sends — token cached.
        $this->assertSame(3, $transport->requestCount());
        $auth = $transport->requestAt(0);
        $this->assertSame('GET', $auth['method']);
        $this->assertSame('https://api.example.test/v1/auth/token', $auth['url']);
        $this->assertSame('live_key', $auth['headers']['SMSGo-key']);
        $this->assertSame('application/json', $auth['headers']['Accept']);
        $this->assertArrayNotHasKey('Authorization', $auth['headers']);

        $send = $transport->requestAt(1);
        $this->assertSame('Bearer oat_abc', $send['headers']['Authorization']);
        $this->assertSame('application/json', $send['headers']['Content-Type']);
    }

    public function testSendBodyMappingAndNullStripping(): void
    {
        $transport = new FakeTransport();
        $transport->pushJson(200, ['token' => 't', 'mode' => 'live']);
        $transport->pushJson(200, ['id' => 'uuid-1', 'quantity' => 1, 'status' => 'queued', 'test' => false]);

        $client = $this->client($transport);
        $result = $client->send(
            phone: '+5511999990000',
            message: 'Olá',
            smsTypeId: 3,
        );

        $body = $transport->jsonBodyAt(1);
        $this->assertSame([
            'phone' => '+5511999990000',
            'message' => 'Olá',
            'sms_type_id' => 3,
        ], $body);
        // null optionals stripped
        $this->assertArrayNotHasKey('schedule', $body);
        $this->assertArrayNotHasKey('reference', $body);
        $this->assertArrayNotHasKey('from', $body);

        $this->assertSame('uuid-1', $result->id);
        $this->assertSame(1, $result->quantity);
        $this->assertSame('queued', $result->status);
        $this->assertFalse($result->test);
    }

    public function testSendMapsAllOptionalFields(): void
    {
        $transport = new FakeTransport();
        $transport->pushJson(200, ['token' => 't', 'mode' => 'live']);
        $transport->pushJson(200, ['id' => 'x', 'quantity' => 1, 'status' => 'scheduled']);

        $client = $this->client($transport);
        $client->send(
            phone: '+5511999990000',
            message: 'agendado',
            schedule: '2026-07-01T12:00:00Z',
            reference: 'ref-1',
            from: 'MinhaMarca',
            smsTypeId: 2,
        );

        $this->assertSame([
            'phone' => '+5511999990000',
            'message' => 'agendado',
            'schedule' => '2026-07-01T12:00:00Z',
            'reference' => 'ref-1',
            'from' => 'MinhaMarca',
            'sms_type_id' => 2,
        ], $transport->jsonBodyAt(1));
    }

    public function testTest401RefreshRetrySucceedsOnSecondToken(): void
    {
        $transport = new FakeTransport();
        // 1) initial token
        $transport->pushJson(200, ['token' => 'oat_old', 'mode' => 'live']);
        // 2) send returns 401
        $transport->pushJson(401, ['error' => true, 'code' => 'unauthorized', 'message' => 'token expirado']);
        // 3) refresh token
        $transport->pushJson(200, ['token' => 'oat_new', 'mode' => 'live']);
        // 4) retried send succeeds
        $transport->pushJson(200, ['id' => 'ok', 'quantity' => 1, 'status' => 'queued']);

        $client = $this->client($transport);
        $result = $client->send(phone: '+5511999990000', message: 'oi');

        $this->assertSame('ok', $result->id);
        $this->assertSame(4, $transport->requestCount());
        // First send used old token, retried send used the refreshed token.
        $this->assertSame('Bearer oat_old', $transport->requestAt(1)['headers']['Authorization']);
        $this->assertSame('Bearer oat_new', $transport->requestAt(3)['headers']['Authorization']);
    }

    public function testTest401RetriesOnlyOnce(): void
    {
        $transport = new FakeTransport();
        $transport->pushJson(200, ['token' => 'oat_old', 'mode' => 'live']);
        $transport->pushJson(401, ['code' => 'unauthorized', 'message' => 'no']);
        $transport->pushJson(200, ['token' => 'oat_new', 'mode' => 'live']);
        $transport->pushJson(401, ['code' => 'unauthorized', 'message' => 'still no']);

        $client = $this->client($transport);

        try {
            $client->send(phone: '+5511999990000', message: 'oi');
            $this->fail('esperava SMSGoError');
        } catch (SMSGoError $e) {
            $this->assertSame(401, $e->status);
            $this->assertSame('unauthorized', $e->code);
        }

        // token, send(401), refresh, send(401) — no third send.
        $this->assertSame(4, $transport->requestCount());
    }

    public function testValidationErrorMappingWithFieldErrors(): void
    {
        $transport = new FakeTransport();
        $transport->pushJson(200, ['token' => 't', 'mode' => 'live']);
        $transport->pushJson(422, [
            'error' => true,
            'code' => 'validation_error',
            'message' => 'Dados inválidos',
            'errors' => [
                ['field' => 'phone', 'message' => 'obrigatório'],
                ['field' => 'message', 'message' => 'muito curto'],
            ],
        ]);

        $client = $this->client($transport);

        try {
            $client->send(phone: '', message: '');
            $this->fail('esperava SMSGoError');
        } catch (SMSGoError $e) {
            $this->assertSame(422, $e->status);
            $this->assertSame('validation_error', $e->code);
            $this->assertSame('Dados inválidos', $e->getMessage());
            $this->assertNotNull($e->errors);
            $this->assertCount(2, $e->errors);
            $this->assertSame('phone', $e->errors[0]['field']);
            $this->assertSame('obrigatório', $e->errors[0]['message']);
            $this->assertIsArray($e->details);
        }
    }

    public function testErrorCodeFallsBackToHttpCodeNameWhenBodyHasNoCode(): void
    {
        $transport = new FakeTransport();
        $transport->pushJson(200, ['token' => 't', 'mode' => 'live']);
        $transport->pushRaw(402, '');

        $client = $this->client($transport);

        try {
            $client->send(phone: '+5511999990000', message: 'oi');
            $this->fail('esperava SMSGoError');
        } catch (SMSGoError $e) {
            $this->assertSame(402, $e->status);
            $this->assertSame('insufficient_balance', $e->code);
        }
    }

    public function testUnknownStatusGetsHttpPrefixedCode(): void
    {
        $transport = new FakeTransport();
        $transport->pushJson(200, ['token' => 't', 'mode' => 'live']);
        $transport->pushRaw(500, 'boom');

        $client = $this->client($transport);

        try {
            $client->get('abc');
            $this->fail('esperava SMSGoError');
        } catch (SMSGoError $e) {
            $this->assertSame(500, $e->status);
            $this->assertSame('http_500', $e->code);
            // non-JSON string body becomes the message
            $this->assertSame('boom', $e->getMessage());
        }
    }

    public function testNetworkFailureBecomesNetworkError(): void
    {
        $transport = new FakeTransport();
        $transport->pushNetworkError('connection refused');

        $client = $this->client($transport);

        try {
            $client->getBalance();
            $this->fail('esperava SMSGoError');
        } catch (SMSGoError $e) {
            $this->assertSame(0, $e->status);
            $this->assertSame('network_error', $e->code);
            $this->assertStringContainsString('connection refused', $e->getMessage());
        }
    }

    public function testTestKeyResolvesTestMode(): void
    {
        $transport = new FakeTransport();
        $transport->pushJson(200, ['token' => 'oat_t', 'mode' => 'test']);

        $client = $this->client($transport, 'test_key');
        $this->assertNull($client->mode());

        $mode = $client->resolveMode();
        $this->assertSame('test', $mode);
        $this->assertSame('test', $client->mode());
        $this->assertSame('test_key', $transport->requestAt(0)['headers']['SMSGo-key']);
    }

    public function testListBuildsPageQueryAndMapsItems(): void
    {
        $transport = new FakeTransport();
        $transport->pushJson(200, ['token' => 't', 'mode' => 'live']);
        $transport->pushJson(200, [
            'meta' => [
                'total' => 1,
                'perPage' => 20,
                'currentPage' => 2,
                'lastPage' => 3,
                'firstPage' => 1,
                'firstPageUrl' => '/?page=1',
                'lastPageUrl' => '/?page=3',
                'nextPageUrl' => '/?page=3',
                'previousPageUrl' => null,
            ],
            'data' => [
                [
                    'id' => 's1',
                    'number' => 42,
                    'date' => null,
                    'quantity' => 3,
                    'full_name' => 'Ana',
                    'created_at' => '2026-06-30',
                    'status' => 'entregue',
                    'type' => 'SHORTCODE',
                ],
            ],
        ]);

        $client = $this->client($transport);
        $page = $client->list(page: 2);

        $this->assertStringContainsString('/v1/sms/list?page=2', $transport->requestAt(1)['url']);
        $this->assertSame(2, $page->meta->currentPage);
        $this->assertNull($page->meta->previousPageUrl);
        $this->assertCount(1, $page->data);
        $this->assertSame('Ana', $page->data[0]->fullName);
        $this->assertSame(42, $page->data[0]->number);
    }

    public function testGetSmsTypesUnwrapsData(): void
    {
        $transport = new FakeTransport();
        $transport->pushJson(200, ['token' => 't', 'mode' => 'live']);
        $transport->pushJson(200, [
            'data' => [
                ['id' => 1, 'name' => 'SHORTCODE', 'price' => 0.09, 'sale' => null],
                ['id' => 3, 'name' => 'LONGCODE', 'price' => 0.12, 'sale' => 0.10],
            ],
        ]);

        $client = $this->client($transport);
        $types = $client->getSmsTypes();

        $this->assertCount(2, $types);
        $this->assertSame(1, $types[0]->id);
        $this->assertNull($types[0]->sale);
        $this->assertSame(0.10, $types[1]->sale);
    }

    public function testGetNumbersBuildsStatusAndPageQuery(): void
    {
        $transport = new FakeTransport();
        $transport->pushJson(200, ['token' => 't', 'mode' => 'live']);
        $transport->pushJson(200, ['meta' => [], 'data' => []]);

        $client = $this->client($transport);
        $client->getNumbers('id 1', status: 'failed', page: 2);

        $url = $transport->requestAt(1)['url'];
        $this->assertStringContainsString('/v1/sms/id%201/numbers?', $url);
        $this->assertStringContainsString('status=failed', $url);
        $this->assertStringContainsString('page=2', $url);
    }

    public function testSetAutoRechargeMapsCamelToSnake(): void
    {
        $transport = new FakeTransport();
        $transport->pushJson(200, ['token' => 't', 'mode' => 'live']);
        $transport->pushJson(200, [
            'enabled' => true,
            'threshold' => 5,
            'planQuantity' => 5000,
            'cardId' => 'card-1',
            'alertEnabled' => true,
            'alertThreshold' => 15,
        ]);

        $client = $this->client($transport);
        $cfg = $client->setAutoRecharge(
            enabled: true,
            threshold: 5,
            planQuantity: 5000,
            cardId: 'card-1',
            alertEnabled: true,
            alertThreshold: 15,
        );

        $this->assertSame([
            'enabled' => true,
            'threshold' => 5,
            'plan_quantity' => 5000,
            'card_id' => 'card-1',
            'alert_enabled' => true,
            'alert_threshold' => 15,
        ], $transport->jsonBodyAt(1));

        $this->assertTrue($cfg->enabled);
        $this->assertSame(5000, $cfg->planQuantity);
        $this->assertSame('card-1', $cfg->cardId);
    }

    public function testSetWebhookMapsRotateSecret(): void
    {
        $transport = new FakeTransport();
        $transport->pushJson(200, ['token' => 't', 'mode' => 'live']);
        $transport->pushJson(200, ['url' => null, 'secret' => 'whsec_new']);

        $client = $this->client($transport);
        $cfg = $client->setWebhook(url: '', rotateSecret: true);

        $this->assertSame([
            'url' => '',
            'rotate_secret' => true,
        ], $transport->jsonBodyAt(1));
        $this->assertNull($cfg->url);
        $this->assertSame('whsec_new', $cfg->secret);
    }

    public function testContactsCreateMapsBodyAndReturnsUuidString(): void
    {
        $transport = new FakeTransport();
        $transport->pushJson(200, ['token' => 't', 'mode' => 'live']);
        $transport->pushRaw(200, '"contact-uuid"');

        $client = $this->client($transport);
        $id = $client->contacts->create(
            fullName: 'Ana Souza',
            phone: '+5511999990000',
            email: 'ana@exemplo.com',
            lists: ['list-1'],
        );

        $this->assertSame('contact-uuid', $id);
        $this->assertSame([
            'full_name' => 'Ana Souza',
            'phone' => '+5511999990000',
            'email' => 'ana@exemplo.com',
            'lists' => ['list-1'],
        ], $transport->jsonBodyAt(1));
    }

    public function testBillingPurchaseMapsBodyAndParsesResult(): void
    {
        $transport = new FakeTransport();
        $transport->pushJson(200, ['token' => 't', 'mode' => 'live']);
        $transport->pushJson(200, [
            'status' => 'succeeded',
            'invoiceUuid' => 'inv-1',
            'total' => 500.0,
            'quantity' => 5000,
            'paymentIntentId' => 'pi_123',
        ]);

        $client = $this->client($transport);
        $receipt = $client->billing->purchase(quantity: 5000, coupon: 'PROMO');

        $this->assertSame([
            'quantity' => 5000,
            'coupon' => 'PROMO',
        ], $transport->jsonBodyAt(1));
        $this->assertSame('succeeded', $receipt->status);
        $this->assertSame('inv-1', $receipt->invoiceUuid);
        $this->assertSame(500.0, $receipt->total);
    }

    public function testBillingPlansUnwrapsData(): void
    {
        $transport = new FakeTransport();
        $transport->pushJson(200, ['token' => 't', 'mode' => 'live']);
        $transport->pushJson(200, [
            'data' => [
                ['id' => 'p1', 'quantity' => 5000, 'price' => 500, 'sale' => 450, 'unit' => 0.09, 'total' => 450, 'popular' => true],
            ],
        ]);

        $client = $this->client($transport);
        $plans = $client->billing->plans();

        $this->assertCount(1, $plans);
        $this->assertSame('p1', $plans[0]->id);
        $this->assertTrue($plans[0]->popular);
    }

    public function testGetBalanceParsesCompany(): void
    {
        $transport = new FakeTransport();
        $transport->pushJson(200, ['token' => 't', 'mode' => 'live']);
        $transport->pushJson(200, [
            'balance' => 9.3,
            'currency' => 'BRL',
            'company' => ['name' => 'Acme', 'document' => null],
        ]);

        $client = $this->client($transport);
        $balance = $client->getBalance();

        $this->assertSame(9.3, $balance->balance);
        $this->assertSame('BRL', $balance->currency);
        $this->assertSame('Acme', $balance->company['name']);
        $this->assertNull($balance->company['document']);
    }

    public function testGetSendsNoContentTypeHeader(): void
    {
        $transport = new FakeTransport();
        $transport->pushJson(200, ['token' => 't', 'mode' => 'live']);
        $transport->pushJson(200, ['balance' => 1.0, 'currency' => 'BRL', 'company' => ['name' => 'x', 'document' => null]]);

        $client = $this->client($transport);
        $client->getBalance();

        $this->assertArrayNotHasKey('Content-Type', $transport->requestAt(1)['headers']);
        $this->assertNull($transport->requestAt(1)['body']);
    }
}
