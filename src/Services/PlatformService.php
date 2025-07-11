<?php
namespace Paytr\Services;

use Paytr\Helpers\HashHelper;
use Paytr\Exceptions\PaytrException;
use Illuminate\Support\Facades\Config;
use GuzzleHttp\Client;

/**
 * PlatformService
 * PayTR Platform Transfer işlemlerini yönetir.
 */
class PlatformService
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
     * Platform transfer talebi oluşturur
     *
     * @param array $payload
     * @return array
     * @throws PaytrException
     */
    public function createTransfer(array $payload): array
    {
        $config = Config::get('paytr');
        $data = [
            'merchant_id' => $config['merchant_id'],
            'amount' => $payload['amount'],
            'iban' => $payload['iban'],
            'description' => $payload['description'] ?? '',
        ];
        $hashStr = $data['merchant_id'] . $data['amount'] . $data['iban'];
        $data['hash'] = HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);

        try {
            $response = $this->http->post($config['api_url'] . 'platform/transfer', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'Platform transfer işlemi başarısız.');
            }
            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR Platform Transfer API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Platform transfer sonuçlarını sorgular
     *
     * @param string $transferId
     * @return array
     * @throws PaytrException
     */
    public function getTransferResult(string $transferId): array
    {
        $config = Config::get('paytr');
        $data = [
            'merchant_id' => $config['merchant_id'],
            'transfer_id' => $transferId,
        ];
        $hashStr = $data['merchant_id'] . $data['transfer_id'];
        $data['hash'] = HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);

        try {
            $response = $this->http->post($config['api_url'] . 'platform/transfer/result', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'Platform transfer sonucu alınamadı.');
            }
            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR Platform Transfer Sonuç API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Platform transfer callback doğrulama (örnek)
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
}
