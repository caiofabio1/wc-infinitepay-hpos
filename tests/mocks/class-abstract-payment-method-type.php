<?php

namespace Automattic\WooCommerce\Blocks\Payments\Integrations;

abstract class AbstractPaymentMethodType {
    /**
     * Settings array.
     *
     * @var array
     */
    protected $settings = array();

    /**
     * Initialize the payment method type.
     */
    abstract public function initialize();

    /**
     * Returns if this payment method should be active.
     *
     * @return boolean
     */
    abstract public function is_active();

    /**
     * Returns an array of script handles to enqueue for this payment method.
     *
     * @return string[]
     */
    abstract public function get_payment_method_script_handles();

    /**
     * Returns an array of key-value pairs of data to expose to the payment method script.
     *
     * @return array
     */
    abstract public function get_payment_method_data();

    /**
     * Get a setting value.
     *
     * @param string $key Setting key.
     * @return mixed
     */
    public function get_setting( $key ) {
        return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : null;
    }
}
