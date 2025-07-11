<?php
namespace Paytr\Tests\Feature;

use Paytr\Tests\TestCase;
use Paytr\Services\PaymentService;
use Paytr\Services\RefundService;
use Paytr\Helpers\HashHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;
use ReflectionClass;

class PayloadStructureTest extends TestCase
{
    protected array $historyContainer = [];
    protected function setUp(): void
    {
        parent::setUp();
        $this->app['config']->set('paytr', [
            'merchant_id' => 'test_merchant_id',
            'merchant_key' => 'test_merchant_key',
            'merchant_salt' => 'test_merchant_salt',
            'api_url' => 'https://example.com/api/',
        ]);
    }

    protected function mockService($service)
    {
        $this->historyContainer = [];
        $history = Middleware::history($this->historyContainer);
        $mock = new MockHandler([new Response(200, [], json_encode(['status' => 'success']))]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $client = new Client(['handler' => $handler]);
        $ref = new ReflectionClass($service);
        $prop = $ref->getProperty('http');
        $prop->setAccessible(true);
        $prop->setValue($service, $client);
        return $service;
    }

    public static function payloadProvider(): array
    {
        return [
            [PaymentService::class, 'getPaymentStatus', ['OID1'], 'test_merchant_idOID1'],
            [PaymentService::class, 'getInstallmentRates', ['454360'], 'test_merchant_id454360'],
            [PaymentService::class, 'lookupBin', ['454360'], 'test_merchant_id454360'],
            [PaymentService::class, 'getTransactionDetail', ['OID2'], 'test_merchant_idOID2'],
            [PaymentService::class, 'getPaymentStatement', [['date_start' => '2020-01-01', 'date_end' => '2020-01-31']], 'test_merchant_id2020-01-012020-01-31'],
            [PaymentService::class, 'getPaymentDetail', ['12345'], 'test_merchant_id12345'],
            [RefundService::class, 'refund', ['OID3'], 'test_merchant_idOID3'],
        ];
    }

    /**
     * @dataProvider payloadProvider
     */
    public function test_payload_structure($class, $method, $args, $hashStr)
    {
        $service = new $class();
        $service = $this->mockService($service);
        call_user_func_array([$service, $method], (array) $args);
        $request = $this->historyContainer[0]['request'];
        parse_str($request->getBody()->getContents(), $params);
        $this->assertArrayHasKey('paytr_token', $params);
        $expected = HashHelper::makeSignature($hashStr, 'test_merchant_key', 'test_merchant_salt');
        $this->assertEquals($expected, $params['paytr_token']);
        $this->assertArrayNotHasKey('hash', $params);
        $this->assertArrayNotHasKey('merchant_key', $params);
        $this->assertArrayNotHasKey('merchant_salt', $params);
    }
}
