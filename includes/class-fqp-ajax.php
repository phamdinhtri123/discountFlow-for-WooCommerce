<?php
/**
 * AJAX placeholder for future server-side interactions.
 *
 * @package FRPSYCH_Quantity_Pricing
 */

defined( 'ABSPATH' ) || exit;

final class FQP_Ajax {
	public static function init(): void {
		add_action( 'wp_ajax_fqp_get_variation_pricing', array( __CLASS__, 'get_variation_pricing' ) );
		add_action( 'wp_ajax_nopriv_fqp_get_variation_pricing', array( __CLASS__, 'get_variation_pricing' ) );
	}

	public static function get_variation_pricing(): void {
		check_ajax_referer( 'fqp_frontend', 'nonce' );

		$variation_id = isset( $_POST['variation_id'] ) ? absint( wp_unslash( $_POST['variation_id'] ) ) : 0;
		$variation    = $variation_id ? wc_get_product( $variation_id ) : false;

		if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid variation.', 'frpsych-quantity-pricing' ) ), 400 );
		}

		wp_send_json_success( FQP_Helper::get_product_payload( $variation, $variation->get_parent_id(), $variation_id ) );
	}
}
