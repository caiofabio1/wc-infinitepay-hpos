<?php
/**
 * Unit tests for WC_Gateway_InfinitePay_HPOS::build_items().
 *
 * @package InfinitePay\WooCommerce\Tests
 */

use PHPUnit\Framework\TestCase;

class Test_Gateway_Items extends TestCase {

    /** @var WC_Gateway_InfinitePay_HPOS */
    private $gateway;

    /** @var ReflectionMethod */
    private $build_items;

    protected function setUp(): void {
        $ref           = new ReflectionClass( 'WC_Gateway_InfinitePay_HPOS' );
        $this->gateway = $ref->newInstanceWithoutConstructor();
        $this->build_items = $ref->getMethod( 'build_items' );
        $this->build_items->setAccessible( true );
    }

    private function build( WC_Order $order ): array {
        return $this->build_items->invoke( $this->gateway, $order );
    }

    public function test_consolidated_item_when_send_items_disabled(): void {
        $this->gateway->send_items = false;
        $order        = new WC_Order();
        $order->total = 150.00;

        $items = $this->build( $order );

        $this->assertCount( 1, $items );
        $this->assertSame( 15000, $items[0]['price'] );
        $this->assertSame( 1, $items[0]['quantity'] );
        $this->assertStringContainsString( '#123', $items[0]['description'] );
    }

    public function test_detailed_product_items(): void {
        $this->gateway->send_items = true;
        $order        = new WC_Order();
        $order->total = 100.00;

        $item           = new WC_Order_Item();
        $item->quantity = 2;
        $item->total    = 100.00; // 50.00 each = 5000 cents
        $item->name     = 'Widget';
        $order->items   = array( $item );

        $items = $this->build( $order );

        $this->assertCount( 1, $items );
        $this->assertSame( 2, $items[0]['quantity'] );
        $this->assertSame( 5000, $items[0]['price'] );
        $this->assertSame( 'Widget', $items[0]['description'] );
    }

    public function test_shipping_item_included(): void {
        $this->gateway->send_items   = true;
        $order                       = new WC_Order();
        $order->total                = 120.00;
        $order->shipping_total       = 20.00;

        $item           = new WC_Order_Item();
        $item->quantity = 1;
        $item->total    = 100.00;
        $item->name     = 'Product';
        $order->items   = array( $item );

        $items = $this->build( $order );

        $this->assertCount( 2, $items );
        $this->assertSame( 'Frete', $items[1]['description'] );
        $this->assertSame( 2000, $items[1]['price'] );
    }

    public function test_fee_items_included(): void {
        $this->gateway->send_items = true;
        $order        = new WC_Order();
        $order->total = 105.00;

        $item           = new WC_Order_Item();
        $item->quantity = 1;
        $item->total    = 100.00;
        $item->name     = 'Product';
        $order->items   = array( $item );

        $fee         = new WC_Order_Item_Fee();
        $fee->total  = 5.00;
        $fee->name   = 'Service Fee';
        $order->fees = array( $fee );

        $items = $this->build( $order );

        $this->assertCount( 2, $items );
        $this->assertSame( 'Service Fee', $items[1]['description'] );
        $this->assertSame( 500, $items[1]['price'] );
    }

    public function test_total_reconciles_to_order_total(): void {
        // Two unit-quantity items that don't sum to the order total exercise
        // the rounding-adjustment branch (applied to the last item).
        $this->gateway->send_items = true;
        $order        = new WC_Order();
        $order->total = 10.00; // 1000 cents

        $a = new WC_Order_Item(); $a->quantity = 1; $a->total = 3.33; $a->name = 'A';
        $b = new WC_Order_Item(); $b->quantity = 1; $b->total = 3.33; $b->name = 'B';
        $order->items = array( $a, $b );

        $items = $this->build( $order );

        $sum = 0;
        foreach ( $items as $i ) {
            $sum += $i['price'] * $i['quantity'];
        }
        $this->assertSame( 1000, $sum );
    }

    public function test_fallback_to_consolidated_on_empty_items(): void {
        $this->gateway->send_items = true;
        $order        = new WC_Order();
        $order->total = 50.00;
        $order->items = array();

        $items = $this->build( $order );

        $this->assertCount( 1, $items );
        $this->assertSame( 5000, $items[0]['price'] );
    }

    public function test_description_truncated_to_200(): void {
        $this->gateway->send_items = true;
        $order        = new WC_Order();
        $order->total = 10.00;

        $item           = new WC_Order_Item();
        $item->quantity = 1;
        $item->total    = 10.00;
        $item->name     = str_repeat( 'A', 300 );
        $order->items   = array( $item );

        $items = $this->build( $order );

        $this->assertSame( 200, mb_strlen( $items[0]['description'] ) );
    }
}
