<?php
namespace Paytr;

use Paytr\Services\PaymentService;
use Paytr\Services\RefundService;
use Paytr\Services\CardService;
use Paytr\Services\LinkService;
use Paytr\Services\PlatformService;
use Illuminate\Contracts\Foundation\Application;

/**
 * PaytrManager
 * Tüm PayTR servislerine erişim sağlar.
 */
class PaytrManager
{
    /** @var Application */
    protected $app;

    /**
     * PaytrManager constructor.
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Ödeme işlemleri servisi
     * @return PaymentService
     */
    public function paymentService()
    {
        return $this->app->make(PaymentService::class);
    }

    /**
     * İade işlemleri servisi
     * @return RefundService
     */
    public function refundService()
    {
        return $this->app->make(RefundService::class);
    }

    /**
     * Kart işlemleri servisi
     * @return CardService
     */
    public function cardService()
    {
        return $this->app->make(CardService::class);
    }

    /**
     * Link işlemleri servisi
     * @return LinkService
     */
    public function linkService()
    {
        return $this->app->make(LinkService::class);
    }

    /**
     * Platform işlemleri servisi
     * @return PlatformService
     */
    public function platformService()
    {
        return $this->app->make(PlatformService::class);
    }
}
