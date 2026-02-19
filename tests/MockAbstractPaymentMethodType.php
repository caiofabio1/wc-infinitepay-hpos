<?php
namespace Automattic\WooCommerce\Blocks\Payments\Integrations;

abstract class AbstractPaymentMethodType {
    protected $settings;

    public function get_setting( $key ) {
        return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : null;
    }
}
