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
        $data['paytr_token'] = HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);
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
        $data['paytr_token'] = HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);
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
     * İade edilen ödemelerin listesini getirir
     *
     * @param array $payload [date_start, date_end]
     * @return array
     * @throws PaytrException
     */
    public function getReturningPayments(array $payload): array
    {
        $config = Config::get('paytr');
        $data = [
            'merchant_id' => $config['merchant_id'],
            'date_start'  => $payload['date_start'],
            'date_end'    => $payload['date_end'],
        ];

        $hashStr = $data['merchant_id'] . $data['date_start'] . $data['date_end'];
        $data['paytr_token'] = HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);

        try {
            $response = $this->http->post($config['api_url'] . 'platform-transfer-talebi/returning-payments', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'İade listesi alınamadı.');
            }
            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR İade Listesi API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Hesaptan iade ödemesi gönderir
     *
     * @param array $payload
     * @return array
     * @throws PaytrException
     */
    public function sendReturningPayment(array $payload): array
    {
        $config = Config::get('paytr');
        $data = [
            'merchant_id' => $config['merchant_id'],
            'trans_id'    => $payload['trans_id'],
            'amount'      => $payload['amount'],
            'iban'        => $payload['iban'],
            'name'        => $payload['name'],
        ];

        $hashStr = $data['merchant_id'] . $data['trans_id'] . $data['amount'] . $data['iban'] . $data['name'];
        $data['paytr_token'] = HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);

        try {
            $response = $this->http->post($config['api_url'] . 'platform-transfer-talebi/returning-send', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'İade gönderimi başarısız.');
            }
            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR İade Gönderim API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Gelen iade bildirimini işler
     *
     * @param array $payload
     * @return array
     * @throws PaytrException
     */
    public function handleReturningCallback(array $payload): array
    {
        $config = Config::get('paytr');
        $transIds = str_replace('\\', '', $payload['trans_ids'] ?? '');
        $hash = $payload['hash'] ?? '';

        $expected = HashHelper::makeSignature($transIds, $config['merchant_key'], $config['merchant_salt']);
        if (!hash_equals($expected, $hash)) {
            throw new PaytrException('Geçersiz callback imzası');
        }

        return json_decode($transIds, true) ?: [];
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
