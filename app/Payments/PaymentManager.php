<?php

namespace App\Payments;

use App\Models\PaymentGatewaySetting;
use App\Payments\Gateways\ManualGateway;
use App\Payments\Gateways\MipsGateway;
use Illuminate\Support\Manager;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PaymentManager extends Manager
{
    /**
     * Get the default payment driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->config->get('payments.default', 'manual');
    }

    /**
     * Resolve the gateway for a school: the active row in payment_gateways
     * wins, otherwise the configured default driver. Credentials come from
     * the database (encrypted), falling back to config/env.
     *
     * @param  int  $schoolId
     * @return \App\Payments\PaymentGateway
     */
    public function forSchool($schoolId)
    {
        $setting = PaymentGatewaySetting::where('school_id', $schoolId)
            ->where('is_active', true)
            ->first();

        $driver      = $setting ? $setting->driver : $this->getDefaultDriver();
        $credentials = ($setting && $setting->credentials) ? $setting->credentials : [];

        return $this->resolveGateway($driver, $credentials);
    }

    /**
     * Build a driver instance with per-school credentials merged in.
     *
     * @param  string  $driver
     * @param  array  $credentials
     * @return \App\Payments\PaymentGateway
     */
    protected function resolveGateway($driver, array $credentials = [])
    {
        $method = 'create'.Str::studly($driver).'Driver';

        if (method_exists($this, $method)) {
            return $this->{$method}($credentials);
        }

        throw new InvalidArgumentException("Payment driver [{$driver}] is not supported.");
    }

    /**
     * Create the manual (record-only) driver.
     *
     * @param  array  $credentials
     * @return \App\Payments\Gateways\ManualGateway
     */
    public function createManualDriver(array $credentials = [])
    {
        return new ManualGateway($this->config->get('payments.gateways.manual', []));
    }

    /**
     * Create the MIPS driver (beta skeleton).
     *
     * @param  array  $credentials
     * @return \App\Payments\Gateways\MipsGateway
     */
    public function createMipsDriver(array $credentials = [])
    {
        $config = array_merge(
            $this->config->get('payments.gateways.mips', []),
            array_filter($credentials)
        );

        return new MipsGateway($config);
    }
}
