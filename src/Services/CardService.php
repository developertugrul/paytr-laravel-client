<?php
namespace Paytr\Services;

use Paytr\Helpers\HashHelper;
use Paytr\Exceptions\PaytrException;
use Illuminate\Support\Facades\Config;
use GuzzleHttp\Client;

/**
 * CardService
 * PayTR kart saklama ve yönetimi işlemlerini yönetir.
 */
class CardService
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
     * Yeni kart kaydetme
     *
     * @param array $cardData
     * @return array
     * @throws PaytrException
     */
    public function storeCard(array $cardData): array
    {
        $config = Config::get('paytr');

        $data = [
            'merchant_id' => $config['merchant_id'],
            'cc_owner' => $cardData['cc_owner'],
            'card_number' => $cardData['card_number'],
            'expiry_month' => $cardData['expiry_month'],
            'expiry_year' => $cardData['expiry_year'],
            'cvv' => $cardData['cvv'],
            'customer_id' => $cardData['customer_id'],
        ];

        $hashStr = $data['merchant_id'] . $data['customer_id'] . $data['card_number'];
        $data['paytr_token'] = HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);
        try {
            $response = $this->http->post($config['api_url'] . 'card/store', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'Kart kaydetme işlemi başarısız.');
            }

            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR Kart API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Kayıtlı kartla ödeme
     *
     * @param string $token
     * @param array $paymentData
     * @return array
     * @throws PaytrException
     */
    public function payWithCard(string $token, array $paymentData): array
    {
        $config = Config::get('paytr');

        $data = [
            'merchant_id' => $config['merchant_id'],
            'token' => $token,
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency'] ?? $config['default_currency'],
            'merchant_oid' => $paymentData['merchant_oid'],
            'installment_count' => $paymentData['installment_count'] ?? 0,
        ];

        $hashStr = $data['merchant_id'] . $data['token'] . $data['amount'] . $data['merchant_oid'];
        $data['paytr_token'] = HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);

        try {
            $response = $this->http->post($config['api_url'] . 'card/pay', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'Kart ile ödeme işlemi başarısız.');
            }

            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR Kart Ödeme API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Kayıtlı kart ile tekrarlayan ödeme başlatır
     *
     * @param string $token
     * @param array $paymentData
     * @return array
     * @throws PaytrException
     */
    public function recurringPayment(string $token, array $paymentData): array
    {
        $config = Config::get('paytr');
        $data = [
            'merchant_id' => $config['merchant_id'],
            'token' => $token,
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency'] ?? $config['default_currency'],
            'merchant_oid' => $paymentData['merchant_oid'],
            'installment_count' => $paymentData['installment_count'] ?? 0,
            'recurring' => 1,
        ];
        $hashStr = $data['merchant_id'] . $data['token'] . $data['amount'] . $data['merchant_oid'];
        $data['paytr_token'] = HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);
        try {
            $response = $this->http->post($config['api_url'] . 'card/pay', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'Tekrarlayan ödeme işlemi başarısız.');
            }
            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR Tekrarlayan Ödeme API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Kayıtlı kartları listele
     *
     * @param string $customerId
     * @return array
     * @throws PaytrException
     */
    public function listCards(string $customerId): array
    {
        $config = Config::get('paytr');

        $data = [
            'merchant_id' => $config['merchant_id'],
            'customer_id' => $customerId,
        ];

        $hashStr = $data['merchant_id'] . $data['customer_id'];
        $data['paytr_token'] = HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);

        try {
            $response = $this->http->post($config['api_url'] . 'card/list', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'Kart listesi alınamadı.');
            }

            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR Kart Listesi API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Kart silme
     *
     * @param string $token
     * @return array
     * @throws PaytrException
     */
    public function deleteCard(string $token): array
    {
        $config = Config::get('paytr');

        $data = [
            'merchant_id' => $config['merchant_id'],
            'token' => $token,
        ];

        $hashStr = $data['merchant_id'] . $data['token'];
        $data['paytr_token'] = HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);
        try {
            $response = $this->http->post($config['api_url'] . 'card/delete', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'Kart silme işlemi başarısız.');
            }

            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR Kart Silme API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }
}
