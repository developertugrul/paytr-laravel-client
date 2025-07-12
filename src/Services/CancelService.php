<?php
namespace Paytr\Services;

use Paytr\Helpers\HashHelper;
use Paytr\Exceptions\PaytrException;
use Illuminate\Support\Facades\Config;
use GuzzleHttp\Client;

/**
 * CancelService
 * PayTR iptal/void işlemlerini yönetir.
 */
class CancelService
{
    /** @var Client */
    protected $http;

    public function __construct()
    {
        $timeout = Config::get('paytr.timeout', 30);
        $verify  = Config::get('paytr.verify_ssl', true);
        $this->http = new Client([
            'timeout' => $timeout,
            'verify'  => $verify,
        ]);
    }

    /**
     * Tam iptal
     *
     * @param string $merchantOid
     * @return array
     * @throws PaytrException
     */
    public function cancel(string $merchantOid): array
    {
        return $this->processCancel($merchantOid, null);
    }

    /**
     * Kısmi iptal
     *
     * @param string $merchantOid
     * @param int    $amount
     * @return array
     * @throws PaytrException
     */
    public function partialCancel(string $merchantOid, int $amount): array
    {
        return $this->processCancel($merchantOid, $amount);
    }

    /**
     * İptal işlemini gerçekleştirir
     *
     * @param string   $merchantOid
     * @param int|null $amount
     * @return array
     * @throws PaytrException
     */
    protected function processCancel(string $merchantOid, ?int $amount): array
    {
        $config = Config::get('paytr');
        $data = [
            'merchant_id' => $config['merchant_id'],
            'merchant_oid' => $merchantOid,
        ];

        if ($amount !== null) {
            $data['amount'] = $amount;
        }

        $hashStr = $data['merchant_id'] . $data['merchant_oid'] . ($amount ?? '');
        $data['paytr_token'] = HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);

        try {
            $response = $this->http->post($config['api_url'] . 'cancel', [
                'form_params' => $data,
                'headers' => [
                    'Accept'     => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'PayTR iptal işlemi başarısız.');
            }
            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR İptal API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }
}
