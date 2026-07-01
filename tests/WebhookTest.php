<?php

declare(strict_types=1);

namespace Orynlabs\SMSGo\Tests;

use Orynlabs\SMSGo\Webhook;
use PHPUnit\Framework\TestCase;

final class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_3f8a9c2e1b6d4a70f5e2c9b8a1d7e0f4';

    private const RAW_BODY = '{"event":"sms.status","data":{"sendId":"7c3e1a90-2b4d-4f6a-8c1e-9d0f2a3b4c5d","phone":"5511999990000","status":"delivered"}}';

    private const EXPECTED = 'sha256=986eb0c41355b1c94165c4cb275ce2cc9b175e5f93efe7e2ed4294ba58d330c3';

    public function testGoldenVectorVerifies(): void
    {
        $this->assertTrue(
            Webhook::verifySignature(self::RAW_BODY, self::EXPECTED, self::SECRET)
        );
    }

    public function testTamperedBodyFails(): void
    {
        // Flip one byte: "delivered" -> "delivereD"
        $tampered = str_replace('delivered', 'delivereD', self::RAW_BODY);

        $this->assertFalse(
            Webhook::verifySignature($tampered, self::EXPECTED, self::SECRET)
        );
    }

    public function testWrongSecretFails(): void
    {
        $this->assertFalse(
            Webhook::verifySignature(self::RAW_BODY, self::EXPECTED, 'whsec_wrong')
        );
    }

    public function testTruncatedSignatureFails(): void
    {
        $truncated = substr(self::EXPECTED, 0, -4);

        $this->assertFalse(
            Webhook::verifySignature(self::RAW_BODY, $truncated, self::SECRET)
        );
    }

    public function testNullSignatureFails(): void
    {
        $this->assertFalse(
            Webhook::verifySignature(self::RAW_BODY, null, self::SECRET)
        );
    }

    public function testEmptySignatureFails(): void
    {
        $this->assertFalse(
            Webhook::verifySignature(self::RAW_BODY, '', self::SECRET)
        );
    }

    public function testSignatureWithoutPrefixFails(): void
    {
        $noPrefix = substr(self::EXPECTED, strlen('sha256='));

        $this->assertFalse(
            Webhook::verifySignature(self::RAW_BODY, $noPrefix, self::SECRET)
        );
    }

    public function testFreshlyComputedSignatureRoundTrips(): void
    {
        $body = '{"event":"sms.reply","data":{"fromPhone":"5511988887777","message":"OK","receivedAt":"2026-07-01T00:00:00.000Z"}}';
        $secret = 'whsec_roundtrip';
        $signature = 'sha256=' . hash_hmac('sha256', $body, $secret);

        $this->assertTrue(Webhook::verifySignature($body, $signature, $secret));
    }
}
