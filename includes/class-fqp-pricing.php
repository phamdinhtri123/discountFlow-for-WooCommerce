<?php
/**
 * Runtime WooCommerce cart pricing.
 *
 * @package FRPSYCH_Quantity_Pricing
 */

defined( 'ABSPATH' ) || exit;

final class FQP_Pricing {
	public static function init(): void {
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_cart_prices' ), 20 );
	}

	public static function apply_cart_prices( WC_Cart $cart ): void {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof WC_Product ) {
				continue;
			}

			$product      = $cart_item['data'];
			$product_id   = absint( $cart_item['product_id'] ?? $product->get_id() );
			$variation_id = absint( $cart_item['variation_id'] ?? 0 );
			$quantity     = max( 1, absint( $cart_item['quantity'] ?? 1 ) );
			$source       = FQP_Helper::get_base_source( $product_id, $variation_id );
			$base_price   = FQP_Helper::get_base_price( $product, $product_id, $variation_id );

			if ( 'active' === $source ) {
				if ( ! isset( $cart->cart_contents[ $cart_item_key ]['fqp_original_active_price'] ) ) {
					$cart->cart_contents[ $cart_item_key ]['fqp_original_active_price'] = (float) wc_format_decimal( $product->get_price( 'edit' ), wc_get_price_decimals() );
				}
				$base_price = (float) $cart->cart_contents[ $cart_item_key ]['fqp_original_active_price'];
			}

			if ( ! FQP_Helper::is_enabled( $product_id, $variation_id ) ) {
				$product->set_price( $base_price );
				continue;
			}

			$product->set_price( FQP_Helper::calculate_unit_price_from_base( $product, $quantity, $variation_id, $base_price ) );
		}
	}
}
