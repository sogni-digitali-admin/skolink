<?php

namespace App\Payments;

class PaymentSession
{
    const TYPE_REDIRECT = 'redirect';
    const TYPE_QR       = 'qr';
    const TYPE_MANUAL   = 'manual';

    /** @var string */
    public $type;

    /** @var string|null */
    public $redirectUrl;

    /** @var array */
    public $data;

    /**
     * @param  string  $type
     * @param  string|null  $redirectUrl
     * @param  array  $data
     */
    public function __construct($type, $redirectUrl = null, array $data = [])
    {
        $this->type        = $type;
        $this->redirectUrl = $redirectUrl;
        $this->data        = $data;
    }

    /**
     * Session that sends the payer to a hosted gateway page.
     *
     * @param  string  $url
     * @param  array  $data
     * @return static
     */
    public static function redirect($url, array $data = [])
    {
        return new static(self::TYPE_REDIRECT, $url, $data);
    }

    /**
     * Session carrying QR payload data.
     *
     * @param  array  $data
     * @return static
     */
    public static function qr(array $data = [])
    {
        return new static(self::TYPE_QR, null, $data);
    }

    /**
     * Record-only session for manual rails (cash, cheque, transfer, Juice ref).
     *
     * @param  string  $reference
     * @param  array  $data
     * @return static
     */
    public static function manual($reference, array $data = [])
    {
        return new static(self::TYPE_MANUAL, null, array_merge(['reference' => $reference], $data));
    }
}
