<?php

namespace Paytr\Services;

use Paytr\Helpers\HashHelper;
use Paytr\Exceptions\PaytrException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

/**
 * PaymentService
 * PayTR ödeme işlemlerini yönetir.
 * Direct API, iFrame API, Token oluşturma, Filtreleme ve Pagination desteği.
 */
class PaymentService
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
     * PayTR ile ödeme başlatır (Direct API)
     *
     * @param array $payload
     * @return array
     * @throws PaytrException
     */
    public function pay(array $payload): array
    {
        $config = Config::get('paytr');
        $data = $this->preparePaymentData($payload, $config);

        // Debug için hash string'i logla
        if ($config['debug']) {
            Log::info('PayTR Hash String: ' . $data['hash_str']);
            Log::info('PayTR Merchant Key: ' . $config['merchant_key']);
            Log::info('PayTR Merchant Salt: ' . $config['merchant_salt']);
            Log::info('PayTR Merchant ID: ' . $data['merchant_id']);
            Log::info('PayTR User IP: ' . $data['user_ip']);
            Log::info('PayTR Merchant OID: ' . $data['merchant_oid']);
            Log::info('PayTR Email: ' . $data['email']);
            Log::info('PayTR Payment Amount: ' . $data['payment_amount']);
            Log::info('PayTR User Basket: ' . $data['user_basket']);
            Log::info('PayTR Payment Type: ' . $data['payment_type']);
            Log::info('PayTR Installment Count: ' . $data['installment_count']);
            Log::info('PayTR Currency: ' . $data['currency']);
            Log::info('PayTR Test Mode: ' . $data['test_mode']);
            Log::info('PayTR Non 3D: ' . $data['non_3d']);
        }

        $signature = HashHelper::makeSignature($data['hash_str'], $config['merchant_key'], $config['merchant_salt']);
         // Direct API isteklerinde imza "paytr_token" parametresi ile iletilmelidir
        // https://dev.paytr.com/direkt-api/direkt-api-1-adim dokümanında belirtildiği üzere
        // paytr_token alanının eksik ya da hatalı olması durumunda "paytr_token gonderilmedi veya gecersiz"
        // hatası alınır. Bu nedenle parametre adı "paytr_token" olarak kullanılmalıdır.
        $data['paytr_token'] = $signature;
        unset($data['hash_str']);

        try {
            $response = $this->http->post($config['direct_api_url'], [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'PayTR ödeme başarısız.');
            }

            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * iFrame API için token oluşturur
     *
     * @param array $payload
     * @return array
     * @throws PaytrException
     */
    public function createIframeToken(array $payload): array
    {
        $config = Config::get('paytr');
        $data = $this->prepareIframeData($payload, $config);
        $signature = HashHelper::makeSignature($data['hash_str'], $config['merchant_key'], $config['merchant_salt']);
        $data['paytr_token'] = $signature;
        unset($data['hash_str']);

        try {
            $response = $this->http->post($config['iframe_api_url'], [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'iFrame token oluşturma başarısız.');
            }

            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR iFrame API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Ödeme durumu sorgular
     *
     * @param string $merchantOid
     * @return array
     * @throws PaytrException
     */
    public function getPaymentStatus(string $merchantOid): array
    {
        $config = Config::get('paytr');

        $data = [
            'merchant_id' => $config['merchant_id'],
            'merchant_oid' => $merchantOid,
        ];

        $hashStr = $data['merchant_id'] . $data['merchant_oid'];
        $data['paytr_token'] = HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);
        try {
            $response = $this->http->post($config['api_url'] . 'payment/status', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'Ödeme durumu alınamadı.');
            }

            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR Ödeme Durumu API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Kart BIN'ine göre taksit oranlarını sorgular
     *
     * @param string $bin
     * @return array
     * @throws PaytrException
     */
    public function getInstallmentRates(string $bin): array
    {
        $config = Config::get('paytr');
        $data = [
            'merchant_id' => $config['merchant_id'],
            'bin_number' => $bin,
        ];
        $hashStr = $data['merchant_id'] . $data['bin_number'];
        $data['paytr_token'] = \Paytr\Helpers\HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);
        try {
            $response = $this->http->post($config['api_url'] . 'installment/rates', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'Taksit oranları alınamadı.');
            }
            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR Taksit Oranları API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Kart BIN bilgisini sorgular
     *
     * @param string $bin
     * @return array
     * @throws PaytrException
     */
    public function lookupBin(string $bin): array
    {
        $config = Config::get('paytr');
        $data = [
            'merchant_id' => $config['merchant_id'],
            'bin_number' => $bin,
        ];
        $hashStr = $data['merchant_id'] . $data['bin_number'];
        $data['paytr_token'] = \Paytr\Helpers\HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);
        try {
            $response = $this->http->post($config['api_url'] . 'bin/lookup', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'BIN bilgisi alınamadı.');
            }
            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR BIN Lookup API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * İşlem detayını sorgular
     *
     * @param string $merchantOid
     * @return array
     * @throws PaytrException
     */
    public function getTransactionDetail(string $merchantOid): array
    {
        $config = Config::get('paytr');
        $data = [
            'merchant_id' => $config['merchant_id'],
            'merchant_oid' => $merchantOid,
        ];
        $hashStr = $data['merchant_id'] . $data['merchant_oid'];
        $data['paytr_token'] = \Paytr\Helpers\HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);
        try {
            $response = $this->http->post($config['api_url'] . 'transaction/detail', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'İşlem detayı alınamadı.');
            }
            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR İşlem Detay API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Ödeme raporu (statement) sorgular
     *
     * @param array $payload
     * @return array
     * @throws PaytrException
     */
    public function getPaymentStatement(array $payload): array
    {
        $config = Config::get('paytr');
        $data = [
            'merchant_id' => $config['merchant_id'],
            'date_start' => $payload['date_start'],
            'date_end' => $payload['date_end'],
        ];
        $hashStr = $data['merchant_id'] . $data['date_start'] . $data['date_end'];
        $data['paytr_token'] = \Paytr\Helpers\HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);

        try {
            $response = $this->http->post($config['api_url'] . 'payment/statement', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'Ödeme raporu alınamadı.');
            }
            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR Ödeme Raporu API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Ödeme detayını sorgular
     *
     * @param string $paymentId
     * @return array
     * @throws PaytrException
     */
    public function getPaymentDetail(string $paymentId): array
    {
        $config = Config::get('paytr');
        $data = [
            'merchant_id' => $config['merchant_id'],
            'payment_id' => $paymentId,
        ];
        $hashStr = $data['merchant_id'] . $data['payment_id'];
        $data['paytr_token'] = \Paytr\Helpers\HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);
        try {
            $response = $this->http->post($config['api_url'] . 'payment/detail', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'Ödeme detayı alınamadı.');
            }
            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR Ödeme Detay API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

        /**
     * Ödeme için gerekli verileri hazırlar (Direct API)
     *
     * @param array $payload
     * @param array $config
     * @return array
     */
    protected function preparePaymentData(array $payload, array $config): array
    {
        // request_exp_date parametresi için varsayılan değer (1 saat sonra)
        $requestExpDate = $payload['request_exp_date'] ?? date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Otomatik alınacak parametreler
        $data = [
            // Zorunlu parametreler (config'den otomatik alınır)
            'merchant_id'    => $config['merchant_id'],
            'user_ip'        => $payload['user_ip'] ?? Request::ip(),
            'test_mode'      => $config['sandbox'] ? 1 : 0,
            'debug_on'       => $config['debug'] ? 1 : 0,
            'client_lang'    => $payload['lang'] ?? $config['default_lang'],

            // Kullanıcıdan alınan zorunlu parametreler
            'merchant_oid'   => $payload['merchant_oid'],
            'email'          => $payload['email'],
            'payment_amount' => $payload['payment_amount'],
            'payment_type'   => $payload['payment_type'] ?? 'card',
            'installment_count' => $payload['installment_count'] ?? 0,
            'currency'       => $payload['currency'] ?? $config['default_currency'],
            'non_3d'         => $payload['non_3d'] ?? 0,
            'request_exp_date' => $requestExpDate,

            // Müşteri bilgileri
            'user_name'      => $payload['user_name'],
            'user_address'   => $payload['user_address'],
            'user_phone'     => $payload['user_phone'],

            // URL'ler (doğru isimlendirme ile)
            'merchant_ok_url' => $payload['merchant_ok_url'] ?? $payload['ok_url'] ?? '',
            'merchant_fail_url' => $payload['merchant_fail_url'] ?? $payload['fail_url'] ?? '',

            // Sepet
            'user_basket'    => $this->encodeBasket($payload['basket']),

            // Kart bilgileri (Direct API için zorunlu)
            'cc_owner'       => $payload['cc_owner'] ?? '',
            'card_number'    => $payload['card_number'] ?? '',
            'expiry_month'   => $payload['expiry_month'] ?? '',
            'expiry_year'    => $payload['expiry_year'] ?? '',
            'cvv'            => $payload['cvv'] ?? '',

            // Opsiyonel parametreler
            'no_installment' => $payload['no_installment'] ?? 0,
            'max_installment' => $payload['max_installment'] ?? 0,
            'timeout_limit'  => $payload['timeout_limit'] ?? Config::get('paytr.default_timeout', 0),
            'sync_mode'      => $payload['sync_mode'] ?? 0,
            'non3d_test_failed' => $payload['non3d_test_failed'] ?? 0,
            'card_type'      => $payload['card_type'] ?? '',
        ];

        // Direct API için hash string - PayTR dokümantasyonuna göre
        // merchant_id + user_ip + merchant_oid + email + payment_amount + payment_type + installment_count + currency + test_mode + non_3d + request_exp_date
        $hash_str =
            $data['merchant_id'] .
            $data['user_ip'] .
            $data['merchant_oid'] .
            $data['email'] .
            $data['payment_amount'] .
            $data['payment_type'] .
            $data['installment_count'] .
            $data['currency'] .
            $data['test_mode'] .
            $data['non_3d'] .
            $data['request_exp_date'];
        $data['hash_str'] = $hash_str;
        return $data;
    }

    /**
     * iFrame API için verileri hazırlar
     *
     * @param array $payload
     * @param array $config
     * @return array
     */
    protected function prepareIframeData(array $payload, array $config): array
    {
        // Otomatik alınacak parametreler
        $data = [
            // Zorunlu parametreler (config'den otomatik alınır)
            'merchant_id'    => $config['merchant_id'],
            'user_ip'        => $payload['user_ip'] ?? Request::ip(),
            'test_mode'      => $config['sandbox'] ? 1 : 0,
            'debug_on'       => $config['debug'] ? 1 : 0,
            'lang'           => $payload['lang'] ?? $config['default_lang'],

            // Kullanıcıdan alınan zorunlu parametreler
            'merchant_oid'   => $payload['merchant_oid'],
            'email'          => $payload['email'],
            'payment_amount' => $payload['payment_amount'],
            'currency'       => $payload['currency'] ?? $config['default_currency'],

            // Müşteri bilgileri
            'user_name'      => $payload['user_name'],
            'user_address'   => $payload['user_address'],
            'user_phone'     => $payload['user_phone'],

            // URL'ler (doğru isimlendirme ile)
            'merchant_ok_url' => $payload['merchant_ok_url'] ?? $payload['ok_url'] ?? '',
            'merchant_fail_url' => $payload['merchant_fail_url'] ?? $payload['fail_url'] ?? '',

            // Sepet
            'user_basket'    => $this->encodeBasket($payload['basket']),

            // Opsiyonel parametreler
            'no_installment' => $payload['no_installment'] ?? 0,
            'max_installment' => $payload['max_installment'] ?? 0,
            'timeout_limit'  => $payload['timeout_limit'] ?? Config::get('paytr.default_timeout', 0),
        ];

        // iFrame API için hash string - PayTR dokümantasyonuna göre
        // merchant_id + user_ip + merchant_oid + email + payment_amount + user_basket + no_installment + max_installment + currency + lang
        $hash_str =
            $data['merchant_id'] .
            $data['user_ip'] .
            $data['merchant_oid'] .
            $data['email'] .
            $data['payment_amount'] .
            $data['user_basket'] .
            $data['no_installment'] .
            $data['max_installment'] .
            $data['currency'] .
            $data['lang'];
        $data['hash_str'] = $hash_str;
        return $data;
    }

    /**
     * Ön provizyon işlemi başlatır
     *
     * @param array $payload
     * @return array
     * @throws PaytrException
     */
    public function preProvision(array $payload): array
    {
        $config = Config::get('paytr');
        $data = [
            'merchant_id'    => $config['merchant_id'],
            'user_ip'        => $payload['user_ip'] ?? Request::ip(),
            'merchant_oid'   => $payload['merchant_oid'],
            'email'          => $payload['email'],
            'payment_amount' => $payload['amount'],
            'currency'       => $payload['currency'] ?? $config['default_currency'],
            'user_name'      => $payload['user_name'],
            'user_address'   => $payload['user_address'],
            'user_phone'     => $payload['user_phone'],
            'user_basket'    => $this->encodeBasket($payload['basket']),
            'lang'           => $payload['lang'] ?? $config['default_lang'],
        ];
        $hashStr = $data['merchant_id'] . $data['user_ip'] . $data['merchant_oid'] . $data['email'] . $data['payment_amount'] . $data['user_basket'] . $data['currency'] . $data['lang'];
        $data['paytr_token'] = \Paytr\Helpers\HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);
        try {
            $response = $this->http->post($config['api_url'] . 'preprovision', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'Ön provizyon işlemi başarısız.');
            }
            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR Ön Provizyon API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Transfer/EFT iFrame API işlemi başlatır
     *
     * @param array $payload
     * @return array
     * @throws PaytrException
     */
    public function createEftIframe(array $payload): array
    {
        $config = Config::get('paytr');
        $data = [
            'merchant_id'    => $config['merchant_id'],
            'user_ip'        => $payload['user_ip'] ?? Request::ip(),
            'merchant_oid'   => $payload['merchant_oid'],
            'email'          => $payload['email'],
            'payment_amount' => $payload['amount'],
            'currency'       => $payload['currency'] ?? $config['default_currency'],
            'user_name'      => $payload['user_name'],
            'user_address'   => $payload['user_address'],
            'user_phone'     => $payload['user_phone'],
            'user_basket'    => $this->encodeBasket($payload['basket']),
            'lang'           => $payload['lang'] ?? $config['default_lang'],
        ];
        $hashStr = $data['merchant_id'] . $data['user_ip'] . $data['merchant_oid'] . $data['email'] . $data['payment_amount'] . $data['user_basket'] . $data['currency'] . $data['lang'];
        $data['paytr_token'] = \Paytr\Helpers\HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);
        try {
            $response = $this->http->post($config['api_url'] . 'eft/iframe', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'EFT iFrame işlemi başarısız.');
            }
            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR EFT iFrame API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * BKM Express ile ödeme başlatır
     *
     * @param array $payload
     * @return array
     * @throws PaytrException
     */
    public function payWithBkmExpress(array $payload): array
    {
        $config = Config::get('paytr');
        $data = [
            'merchant_id'    => $config['merchant_id'],
            'user_ip'        => $payload['user_ip'] ?? Request::ip(),
            'merchant_oid'   => $payload['merchant_oid'],
            'email'          => $payload['email'],
            'payment_amount' => $payload['amount'],
            'currency'       => $payload['currency'] ?? $config['default_currency'],
            'user_name'      => $payload['user_name'],
            'user_address'   => $payload['user_address'],
            'user_phone'     => $payload['user_phone'],
            'user_basket'    => $this->encodeBasket($payload['basket']),
            'lang'           => $payload['lang'] ?? $config['default_lang'],
        ];
        $hashStr = $data['merchant_id'] . $data['user_ip'] . $data['merchant_oid'] . $data['email'] . $data['payment_amount'] . $data['user_basket'] . $data['currency'] . $data['lang'];
        $data['paytr_token'] = \Paytr\Helpers\HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);
        try {
            $response = $this->http->post($config['api_url'] . 'bkm/express', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'BKM Express ile ödeme başarısız.');
            }
            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR BKM Express API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Sepet verilerini PayTR formatında encode eder
     *
     * @param array $basket
     * @return string
     */
    protected function encodeBasket(array $basket): string
    {
        // PayTR sepet formatı: [["Ürün Adı", "Fiyat", "Adet"], ...]
        $formattedBasket = [];

        foreach ($basket as $item) {
            if (is_array($item)) {
                // Eğer zaten PayTR formatında ise
                if (count($item) >= 3) {
                    $formattedBasket[] = [
                        $item[0], // Ürün adı
                        number_format($item[1], 2, '.', ''), // Fiyat (ondalık nokta ile)
                        (int)$item[2] // Adet
                    ];
                }
            } else {
                // Eğer obje formatında ise
                $formattedBasket[] = [
                    $item['name'] ?? $item['title'] ?? 'Ürün',
                    number_format($item['price'] ?? $item['amount'] ?? 0, 2, '.', ''),
                    (int)($item['quantity'] ?? 1)
                ];
            }
        }

        return base64_encode(json_encode($formattedBasket));
    }
}
