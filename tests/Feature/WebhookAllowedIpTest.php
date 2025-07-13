<?php
namespace Paytr\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Paytr\Http\Controllers\WebhookController;
use Paytr\Tests\TestCase;

class WebhookAllowedIpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app['config']->set('paytr.webhook_secret', 'secret');
    }

    public function test_allowed_ips_with_spaces_are_trimmed()
    {
        Route::post('/hook', [WebhookController::class, 'handle']);

        $this->app['config']->set('paytr.allowed_ips', ' 127.0.0.1 , 10.0.0.1 ');

        $payload = ['event' => 'payment_success'];
        $signature = hash_hmac('sha256', json_encode($payload), 'secret');

        $response = $this->postJson('/hook', $payload, [
            'X-PayTR-Signature' => $signature,
        ]);

        $response->assertOk()->assertSee('OK');
    }
}
