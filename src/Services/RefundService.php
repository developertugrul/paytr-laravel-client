<?php
namespace Paytr\Services;

use Paytr\Helpers\HashHelper;
use Paytr\Exceptions\PaytrException;
use Illuminate\Support\Facades\Config;
use GuzzleHttp\Client;

/**
 * RefundService
 * PayTR iade işlemlerini yönetir.
 */
class RefundService
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
     * Tam iade işlemi
     *
     * @param string $merchantOid
     * @return array
     * @throws PaytrException
     */
    public function refund(string $merchantOid): array
    {
        return $this->processRefund($merchantOid, null);
    }

    /**
     * Kısmi iade işlemi
     *
     * @param string $merchantOid
     * @param int $amount
     * @return array
     * @throws PaytrException
     */
    public function partialRefund(string $merchantOid, int $amount): array
    {
        return $this->processRefund($merchantOid, $amount);
    }

    /**
     * İade işlemini gerçekleştirir
     *
     * @param string $merchantOid
     * @param int|null $amount
     * @return array
     * @throws PaytrException
     */
    protected function processRefund(string $merchantOid, ?int $amount): array
    {
        $config = Config::get('paytr');

        $data = [
            'merchant_id' => $config['merchant_id'],
            'merchant_key' => $config['merchant_key'],
            'merchant_salt' => $config['merchant_salt'],
            'merchant_oid' => $merchantOid,
        ];

        if ($amount !== null) {
            $data['amount'] = $amount;
        }

        $hashStr = $data['merchant_id'] . $data['merchant_oid'] . ($amount ?? '');
        $data['hash'] = HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);

        try {
            $response = $this->http->post($config['api_url'] . 'refund', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'PayTR iade işlemi başarısız.');
            }

            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR İade API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * İade durumu sorgular
     *
     * @param string $merchantOid
     * @return array
     * @throws PaytrException
     */
    public function getRefundStatus(string $merchantOid): array
    {
        $config = Config::get('paytr');
        $data = [
            'merchant_id' => $config['merchant_id'],
            'merchant_oid' => $merchantOid,
        ];
        $hashStr = $data['merchant_id'] . $data['merchant_oid'];
        $data['hash'] = \Paytr\Helpers\HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);

        try {
            $response = $this->http->post($config['api_url'] . 'refund/status', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'İade durumu alınamadı.');
            }
            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR İade Durumu API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }
}
