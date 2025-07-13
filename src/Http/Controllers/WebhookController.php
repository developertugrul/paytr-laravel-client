<?php
namespace Paytr\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Paytr\Helpers\HashHelper;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

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
        if (empty($signature)) {
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
     */
    protected function processWebhook(array $data): void
    {
        $event = $data['event'] ?? '';
        $merchantOid = $data['merchant_oid'] ?? '';
        $status = $data['status'] ?? '';

        Log::info('PayTR Webhook received', [
            'event' => $event,
            'merchant_oid' => $merchantOid,
            'status' => $status,
        ]);

        // Event'e göre işlem yap
        switch ($event) {
            case 'payment_success':
                $this->handlePaymentSuccess($data);
                break;
            case 'payment_failed':
                $this->handlePaymentFailed($data);
                break;
            case 'refund_success':
                $this->handleRefundSuccess($data);
                break;
            default:
                Log::warning('PayTR Webhook: Bilinmeyen event', ['event' => $event]);
        }
    }

    /**
     * Başarılı ödeme event'i
     *
     * @param array $data
     * @return void
     */
    protected function handlePaymentSuccess(array $data): void
    {
        // Ödeme başarılı işlemleri
        Log::info('PayTR Payment Success', $data);
    }

    /**
     * Başarısız ödeme event'i
     *
     * @param array $data
     * @return void
     */
    protected function handlePaymentFailed(array $data): void
    {
        // Ödeme başarısız işlemleri
        Log::info('PayTR Payment Failed', $data);
    }

    /**
     * Başarılı iade event'i
     *
     * @param array $data
     * @return void
     */
    protected function handleRefundSuccess(array $data): void
    {
        // İade başarılı işlemleri
        Log::info('PayTR Refund Success', $data);
    }
}
