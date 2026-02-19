/**
 * WooCommerce Blocks Checkout integration for InfinitePay.
 *
 * Registers the InfinitePay payment method in the WC Blocks Checkout.
 * This is a redirect-based gateway — the customer is sent to InfinitePay's
 * hosted checkout page after placing the order.
 *
 * @package InfinitePay\WooCommerce
 * @since   5.0.0
 */

( function () {
    'use strict';

    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
    const { decodeEntities }        = window.wp.htmlEntities;
    const { getSetting }            = window.wc.wcSettings;
    const el                        = window.wp.element.createElement;

    // Gateway ID injected from PHP via wp_localize_script (fallback for safety).
    const gatewayId = ( window.infinitepayBlocksConfig && window.infinitepayBlocksConfig.gatewayId )
        ? window.infinitepayBlocksConfig.gatewayId
        : 'infinitepay_hpos';

    const settings = getSetting( gatewayId + '_data', {} );
    const title    = decodeEntities( settings.title || 'Cartão/Pix (InfinitePay)' );
    const description = decodeEntities(
        settings.description || 'Pague com segurança via InfinitePay.'
    );

    /**
     * Label component shown in the payment method selector.
     */
    const Label = function ( props ) {
        var PaymentMethodLabel = props.components.PaymentMethodLabel;
        return el( PaymentMethodLabel, { text: title } );
    };

    /**
     * Content component shown when the payment method is selected.
     */
    const Content = function () {
        return el( 'p', null, description );
    };

    registerPaymentMethod( {
        name: gatewayId,
        label: el( Label, null ),
        ariaLabel: title,
        content: el( Content, null ),
        edit: el( Content, null ),
        canMakePayment: function () {
            return true;
        },
        paymentMethodId: gatewayId,
        supports: {
            features: settings.supports || [ 'products' ],
        },
    } );
} )();
