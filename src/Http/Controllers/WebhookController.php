<?php
namespace Paytr\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Paytr\Events\PaymentSuccessEvent;
use Paytr\Events\PaymentFailedEvent;
use Paytr\Events\RefundSuccessEvent;

/**
 * WebhookController
 * PayTR webhook bildirimlerini işler.
 */
class WebhookController
{
    /**
     * Webhook bildirimini işler
     *
     * @param Request $request
     * @return Response
     */
    public function handle(Request $request): Response
    {
        $config = Config::get('paytr');

        if (empty($config['webhook_secret'])) {
            Log::error('PayTR Webhook: webhook_secret not configured');
            return response('Webhook secret not configured', 500);
        }

        // IP kontrolü
        if (!empty($config['allowed_ips'])) {
            $allowedIps = explode(',', $config['allowed_ips']);
            $allowedIps = array_map('trim', $allowedIps);
            $clientIp = $request->ip();

            if (!in_array($clientIp, $allowedIps)) {
                Log::warning('PayTR Webhook: Yetkisiz IP', ['ip' => $clientIp]);
                return response('Unauthorized', 403);
            }
        }

        // Signature doğrulama
        $signature = $request->header('X-PayTR-Signature');
        $payload = $request->getContent();

        if (!$this->verifySignature($payload, $signature, $config)) {
            Log::warning('PayTR Webhook: Geçersiz signature');
            return response('Invalid signature', 400);
        }

        $data = json_decode($payload, true);

        if (!$data) {
            Log::error('PayTR Webhook: Geçersiz JSON payload');
            return response('Invalid payload', 400);
        }

        // Webhook event'ini işle
        $this->processWebhook($data);

        return response('OK', 200);
    }

    /**
     * Webhook signature'ını doğrular
     *
     * @param string $payload
     * @param string $signature
     * @param array $config
     * @return bool
     */
    protected function verifySignature(string $payload, string $signature, array $config): bool
    {
        if (empty($signature) || empty($config['webhook_secret'])) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $config['webhook_secret']);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Webhook event'ini işler
     *
     * @param array $data
     * @return void
     * @throws \Exception
     */
    protected function processWebhook(array $data): void
    {
        try {
            $event = $data['event'] ?? '';

            Log::info('PayTR Webhook received', [
                'event' => $event,
                'merchant_oid' => $data['merchant_oid'] ?? '',
                'status' => $data['status'] ?? '',
            ]);

            match ($event) {
                'payment_success' => event(new PaymentSuccessEvent($data)),
                'payment_failed' => event(new PaymentFailedEvent($data)),
                'refund_success' => event(new RefundSuccessEvent($data)),
                default => Log::warning('PayTR Webhook: Bilinmeyen event', ['event' => $event])
            };
        } catch (\Exception $e) {
            Log::error('PayTR Webhook processing failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }
}
