<?php
namespace Paytr\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Paytr\Tests\TestCase;

class VerifyPaytrSignatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app['config']->set('paytr.webhook_secret', 'secret');
    }

    public function test_valid_signature_allows_request()
    {
        Route::post('/hook', fn() => 'ok')->middleware('paytr.signature');

        $payload = ['foo' => 'bar'];
        $signature = hash_hmac('sha256', json_encode($payload), 'secret');

        $response = $this->postJson('/hook', $payload, ['X-PayTR-Signature' => $signature]);

        $response->assertOk()->assertSee('ok');
    }

    public function test_invalid_signature_rejected()
    {
        Route::post('/hook', fn() => 'ok')->middleware('paytr.signature');

        $payload = ['foo' => 'bar'];
        $response = $this->postJson('/hook', $payload, ['X-PayTR-Signature' => 'wrong']);

        $response->assertStatus(403);
    }


    public function test_missing_secret_rejected()
    {
        $this->app['config']->set('paytr.webhook_secret', null);
        Route::post('/hook-missing', fn() => 'ok')->middleware('paytr.signature');

        $payload = ['foo' => 'bar'];
        $signature = hash_hmac('sha256', json_encode($payload), 'secret');

        $response = $this->postJson('/hook-missing', $payload, ['X-PayTR-Signature' => $signature]);

        $response->assertStatus(500);
    }
}
