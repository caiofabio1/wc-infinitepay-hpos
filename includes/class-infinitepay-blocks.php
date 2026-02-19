<?php
/**
 * Integração com WooCommerce Blocks Checkout.
 *
 * Registra o gateway InfinitePay como método de pagamento
 * compatível com o WC Blocks Checkout (Cart & Checkout Blocks).
 *
 * @package InfinitePay\WooCommerce
 * @since   5.0.0
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Classe de integração com WC Blocks.
 */
final class InfinitePay_Blocks_Integration extends AbstractPaymentMethodType {

    /**
     * Nome do método (deve coincidir com o JS e o gateway ID).
     *
     * @var string
     */
    protected $name = WC_Gateway_InfinitePay_HPOS::ID;

    /**
     * Inicializa as configurações.
     */
    public function initialize(): void {
        $this->settings = get_option( 'woocommerce_infinitepay_hpos_settings', array() );
    }

    /**
     * Retorna se o gateway está ativo.
     *
     * @return bool
     */
    public function is_active(): bool {
        return 'yes' === ( $this->get_setting( 'enabled' ) ?? 'no' )
            && ! empty( $this->get_setting( 'handle' ) );
    }

    /**
     * Registra e retorna os handles de scripts para o Blocks checkout.
     *
     * @return string[]
     */
    public function get_payment_method_script_handles(): array {
        $asset_path = INFINITEPAY_WC_PATH . 'assets/js/blocks.asset.php';
        $asset      = file_exists( $asset_path )
            ? require $asset_path
            : array( 'dependencies' => array(), 'version' => INFINITEPAY_WC_VERSION );

        wp_register_script(
            'wc-infinitepay-blocks',
            plugins_url( 'assets/js/blocks.js', INFINITEPAY_WC_FILE ),
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_localize_script( 'wc-infinitepay-blocks', 'infinitepayBlocksConfig', array(
            'gatewayId' => WC_Gateway_InfinitePay_HPOS::ID,
        ) );

        return array( 'wc-infinitepay-blocks' );
    }

    /**
     * Retorna os handles de scripts para o admin (editor de blocos).
     *
     * @return string[]
     */
    public function get_payment_method_script_handles_for_admin(): array {
        return $this->get_payment_method_script_handles();
    }

    /**
     * Dados disponibilizados para o script JS via wc.wcSettings.
     *
     * @return array
     */
    public function get_payment_method_data(): array {
        return array(
            'title'       => $this->get_setting( 'title' ) ?? __( 'Cartão/Pix (InfinitePay)', 'wc-infinitepay-hpos' ),
            'description' => $this->get_setting( 'description' ) ?? __( 'Pague com segurança via InfinitePay.', 'wc-infinitepay-hpos' ),
            'supports'    => array( 'products' ),
        );
    }
}
