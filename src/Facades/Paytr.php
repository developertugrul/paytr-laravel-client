<?php
namespace Paytr\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * PayTR Facade
 * @method static \Paytr\Services\PaymentService payment()
 * @method static \Paytr\Services\RefundService refund()
 * @method static \Paytr\Services\CardService card()
 * @see \Paytr\PaytrManager
 */
class Paytr extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'paytr';
    }

    /**
     * Ödeme işlemleri için shortcut
     */
    public static function payment()
    {
        return static::getFacadeRoot()->paymentService();
    }

    /**
     * İade işlemleri için shortcut
     */
    public static function refund()
    {
        return static::getFacadeRoot()->refundService();
    }


    /**
     * İptal işlemleri için shortcut
     */
    public static function cancel()
    {
        return static::getFacadeRoot()->cancelService();
    }

    /**
     * Kart işlemleri için shortcut
     */
    public static function card()
    {
        return static::getFacadeRoot()->cardService();
    }

    /**
     * Link işlemleri için shortcut
     */
    public static function link()
    {
        return static::getFacadeRoot()->linkService();
    }

    /**
     * Platform işlemleri için shortcut
     */
    public static function platform()
    {
        return static::getFacadeRoot()->platformService();
    }
}
