<?php

namespace Tests\Feature\Foundation;

use Tests\TestCase;

class FoundationTest extends TestCase
{
    public function test_health_endpoint_returns_a_correlation_id(): void
    {
        $response = $this->get('/up');

        $response
            ->assertOk()
            ->assertHeader('X-Correlation-ID');

        $this->assertMatchesRegularExpression(
            '/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i',
            (string) $response->headers->get('X-Correlation-ID')
        );
    }

    public function test_valid_incoming_correlation_id_is_preserved(): void
    {
        $response = $this->withHeader('X-Correlation-ID', 'checkout-request-123')
            ->get('/up');

        $response
            ->assertOk()
            ->assertHeader('X-Correlation-ID', 'checkout-request-123');
    }

    public function test_invalid_incoming_correlation_id_is_replaced(): void
    {
        $response = $this->withHeader('X-Correlation-ID', '<invalid>')
            ->get('/up');

        $response->assertOk();

        $this->assertNotSame(
            '<invalid>',
            $response->headers->get('X-Correlation-ID')
        );
    }

    public function test_foundation_defaults_use_utc_and_post_commit_queues(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertTrue(config('queue.connections.database.after_commit'));
        $this->assertTrue(config('queue.connections.redis.after_commit'));
        $this->assertTrue(config('queue.connections.sqs.after_commit'));
        $this->assertTrue(config('queue.connections.beanstalkd.after_commit'));
    }
}
