<?php
namespace Paytr\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Paytr\Http\Controllers\WebhookController;
use Paytr\Tests\TestCase;

class WebhookSecretTest extends TestCase
{
    public function test_missing_secret_rejected_by_controller()
    {
        $this->app['config']->set('paytr', [
            'webhook_secret' => '',
            'allowed_ips' => '',
        ]);
        Route::post('/webhook', [WebhookController::class, 'handle']);
        $payload = ['foo' => 'bar'];
        $response = $this->postJson('/webhook', $payload, ['X-PayTR-Signature' => '']);
        $response->assertStatus(500);
    }
}
