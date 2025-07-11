<?php
namespace Paytr\Services;

use Paytr\Helpers\HashHelper;
use Paytr\Exceptions\PaytrException;
use Illuminate\Support\Facades\Config;
use GuzzleHttp\Client;

/**
 * LinkService
 * PayTR Link API işlemlerini yönetir.
 */
class LinkService
{
    /** @var Client */
    protected $http;

    public function __construct()
    {
        $timeout = Config::get('paytr.timeout', 30);
        $verify = Config::get('paytr.verify_ssl', true);
        $this->http = new Client([
            'timeout' => $timeout,
            'verify' => $verify,
        ]);
    }

    /**
     * Link ile ödeme oluşturur
     *
     * @param array $payload
     * @return array
     * @throws PaytrException
     */
    public function createLink(array $payload): array
    {
        $config = Config::get('paytr');
        $data = [
            'merchant_id' => $config['merchant_id'],
            'email' => $payload['email'],
            'payment_amount' => $payload['amount'],
            'user_name' => $payload['user_name'],
            'user_address' => $payload['user_address'],
            'user_phone' => $payload['user_phone'],
            'user_basket' => base64_encode(json_encode($payload['basket'])),
            'currency' => $payload['currency'] ?? 'TL',
            'lang' => $payload['lang'] ?? 'tr',
        ];
        $hashStr = $data['merchant_id'] . $data['email'] . $data['payment_amount'] . $data['user_basket'] . $data['currency'] . $data['lang'];
        $data['paytr_token'] = HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);
        try {
            $response = $this->http->post($config['api_url'] . 'link/create', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'Link ile ödeme oluşturulamadı.');
            }
            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR Link API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Link ile ödemeyi siler
     *
     * @param string $linkId
     * @return array
     * @throws PaytrException
     */
    public function deleteLink(string $linkId): array
    {
        $config = Config::get('paytr');
        $data = [
            'merchant_id' => $config['merchant_id'],
            'link_id' => $linkId,
        ];
        $hashStr = $data['merchant_id'] . $data['link_id'];
        $data['paytr_token'] = HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);
        try {
            $response = $this->http->post($config['api_url'] . 'link/delete', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'Link silinemedi.');
            }
            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR Link Silme API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Link ile ödeme callback doğrulama (opsiyonel, örnek)
     *
     * @param array $payload
     * @param string $signature
     * @return bool
     */
    public function verifyCallback(array $payload, string $signature): bool
    {
        $config = Config::get('paytr');
        $expectedSignature = hash_hmac('sha256', json_encode($payload), $config['webhook_secret']);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Link ile ödeme SMS/Email gönderimi (opsiyonel, örnek)
     *
     * @param string $linkId
     * @param string $type (sms|email)
     * @return array
     * @throws PaytrException
     */
    public function sendLinkNotification(string $linkId, string $type = 'sms'): array
    {
        $config = Config::get('paytr');
        $data = [
            'merchant_id' => $config['merchant_id'],
            'link_id' => $linkId,
            'type' => $type,
        ];
        $hashStr = $data['merchant_id'] . $data['link_id'] . $data['type'];
        $data['paytr_token'] = HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);
        try {
            $response = $this->http->post($config['api_url'] . 'link/notify', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'Link bildirimi gönderilemedi.');
            }
            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR Link Bildirim API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }
}
