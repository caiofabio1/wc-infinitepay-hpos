/**
 * WooCommerce Blocks Checkout integration for InfinitePay.
 *
 * Registers the InfinitePay payment method in the WC Blocks Checkout.
 * This is a redirect-based gateway — the customer is sent to InfinitePay's
 * hosted checkout page after placing the order.
 *
 * @package InfinitePay\WooCommerce
 * @since   5.0.0
 * @updated 5.3.0 — Rich payment presentation with card brand badges,
 *                   PIX icon, installment info, and redirect notice.
 */

( function () {
    'use strict';

    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
    const { decodeEntities }        = window.wp.htmlEntities;
    const { getSetting }            = window.wc.wcSettings;
    const el                        = window.wp.element.createElement;

    const settings = getSetting( 'infinitepay_hpos_data', {} );
    const title    = decodeEntities( settings.title || 'Cartão/Pix (InfinitePay)' );

    /**
     * Inline SVG card brand icons (small, monochrome for clean look).
     */
    var cardBrands = [
        { name: 'Visa',       color: '#1a1f71' },
        { name: 'Mastercard', color: '#eb001b' },
        { name: 'Elo',        color: '#00a4e0' },
        { name: 'PIX',        color: '#32bcad' },
    ];

    /**
     * Label component shown in the payment method selector.
     */
    var Label = function ( props ) {
        var PaymentMethodLabel = props.components.PaymentMethodLabel;
        return el( PaymentMethodLabel, { text: title } );
    };

    /**
     * Content component shown when the payment method is selected.
     * Rich presentation with card brands, PIX, installments, and redirect notice.
     */
    var Content = function () {
        var containerStyle = {
            fontSize: '14px',
            lineHeight: '1.5',
            color: '#333',
        };

        var badgesStyle = {
            display: 'flex',
            alignItems: 'center',
            gap: '8px',
            flexWrap: 'wrap',
            marginBottom: '12px',
        };

        var badgeStyle = function ( color ) {
            return {
                display: 'inline-flex',
                alignItems: 'center',
                padding: '3px 10px',
                borderRadius: '4px',
                fontSize: '11px',
                fontWeight: '600',
                letterSpacing: '0.5px',
                color: color,
                border: '1px solid ' + color,
                background: 'transparent',
            };
        };

        var installmentStyle = {
            display: 'flex',
            alignItems: 'center',
            gap: '6px',
            fontSize: '13px',
            color: '#555',
            marginBottom: '10px',
        };

        var redirectStyle = {
            display: 'flex',
            alignItems: 'center',
            gap: '6px',
            fontSize: '12px',
            color: '#888',
            padding: '8px 12px',
            background: '#f8f6f3',
            borderRadius: '6px',
            marginTop: '4px',
        };

        // Lock SVG icon
        var lockIcon = el( 'svg', {
            width: 14, height: 14, viewBox: '0 0 24 24',
            fill: 'currentColor', style: { flexShrink: 0 }
        }, el( 'path', { d: 'M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z' } ) );

        return el( 'div', { style: containerStyle },
            // Card brand badges
            el( 'div', { style: badgesStyle },
                cardBrands.map( function ( brand ) {
                    return el( 'span', { key: brand.name, style: badgeStyle( brand.color ) }, brand.name );
                })
            ),
            // Installment info
            el( 'div', { style: installmentStyle },
                el( 'span', null, '\uD83D\uDCB3' ), // 💳
                el( 'span', null, 'Cartão em até 12x · PIX à vista' )
            ),
            // Redirect notice
            el( 'div', { style: redirectStyle },
                lockIcon,
                el( 'span', null, 'Você será redirecionado para pagamento seguro' )
            )
        );
    };

    registerPaymentMethod( {
        name: 'infinitepay_hpos',
        label: el( Label, null ),
        ariaLabel: title,
        content: el( Content, null ),
        edit: el( Content, null ),
        canMakePayment: function () {
            return true;
        },
        paymentMethodId: 'infinitepay_hpos',
        supports: {
            features: settings.supports || [ 'products' ],
        },
    } );
} )();
