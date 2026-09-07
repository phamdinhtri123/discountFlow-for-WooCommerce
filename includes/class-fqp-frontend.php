<?php
/**
 * Frontend table rendering and assets.
 *
 * @package FRPSYCH_Quantity_Pricing
 */

defined( 'ABSPATH' ) || exit;

final class FQP_Frontend {
	private static array $rendered = array();

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp', array( __CLASS__, 'register_positioned_hooks' ) );
		add_filter( 'woocommerce_available_variation', array( __CLASS__, 'add_variation_payload' ), 10, 3 );
		add_shortcode( 'fqp_pricing_table', array( __CLASS__, 'shortcode' ) );
	}

	public static function register_positioned_hooks(): void {
		if ( ! is_product() ) {
			return;
		}

		$product = wc_get_product( get_the_ID() );
		if ( ! $product ) {
			return;
		}

		$settings = FQP_Helper::get_display_settings( $product->get_id() );
		$position = apply_filters( 'fqp_table_position', $settings['position'], $product );

		if ( 'after_price' === $position ) {
			add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_pricing_table' ), 11 );
		} elseif ( 'after_add_to_cart' === $position ) {
			add_action( 'woocommerce_after_add_to_cart_form', array( __CLASS__, 'render_pricing_table' ), 10 );
		} else {
			add_action( 'woocommerce_before_add_to_cart_form', array( __CLASS__, 'render_pricing_table' ), 10 );
		}
	}

	public static function enqueue_assets(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		wp_enqueue_style( 'fqp-frontend', FQP_PLUGIN_URL . 'assets/css/frontend.css', array(), FQP_VERSION );
		wp_enqueue_script( 'fqp-frontend', FQP_PLUGIN_URL . 'assets/js/frontend.js', array(), FQP_VERSION, true );

		$product = is_product() ? wc_get_product( get_the_ID() ) : false;
		$payload = $product ? FQP_Helper::get_product_payload( $product ) : array();

		wp_localize_script(
			'fqp-frontend',
			'fqpData',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'fqp_frontend' ),
				'product'   => $payload,
				'currency'  => array(
					'symbol'       => get_woocommerce_currency_symbol(),
					'decimals'     => wc_get_price_decimals(),
					'decimalSep'   => wc_get_price_decimal_separator(),
					'thousandSep'  => wc_get_price_thousand_separator(),
					'priceFormat'  => get_woocommerce_price_format(),
				),
				'i18n'      => array(
					'no_discount' => __( 'No quantity discount applied', 'frpsych-quantity-pricing' ),
					'applied'     => __( '%s quantity discount applied', 'frpsych-quantity-pricing' ),
					'you_save'    => __( 'You Save: %s', 'frpsych-quantity-pricing' ),
				),
			)
		);
	}

	public static function render_pricing_table( ?WC_Product $target_product = null ): void {
		global $product;
		$product = $target_product instanceof WC_Product ? $target_product : $product;

		if ( ! $product instanceof WC_Product || $product->is_sold_individually() ) {
			return;
		}

		$product_id = $product->get_id();
		if ( isset( self::$rendered[ $product_id ] ) ) {
			return;
		}
		if ( ! self::product_has_rules( $product ) ) {
			return;
		}

		$settings = FQP_Helper::get_display_settings( $product_id );
		if ( ! apply_filters( 'fqp_show_pricing_table', $settings['show_table'], $product ) ) {
			return;
		}

		$payload = FQP_Helper::get_product_payload( $product );
		self::$rendered[ $product_id ] = true;
		$parent_has_rules = FQP_Helper::is_enabled( $product_id ) && ! empty( FQP_Helper::get_pricing_tiers( $product_id ) );

		$style = sprintf(
			'--fqp-primary:%s;--fqp-radius:%dpx;',
			esc_attr( $settings['color'] ),
			absint( $settings['radius'] )
		);

		do_action( 'fqp_before_pricing_table', $product );
		echo '<div class="fqp-pricing-box" data-fqp-pricing data-fqp-payload="' . esc_attr( wp_json_encode( $payload ) ) . '" style="' . esc_attr( $style ) . '">';
		echo '<h3 class="fqp-heading">' . esc_html( $settings['heading'] ) . '</h3>';
		if ( '' !== $settings['description'] ) {
			echo '<p class="fqp-description">' . esc_html( $settings['description'] ) . '</p>';
		}
		echo '<table class="fqp-pricing-table"><thead><tr><th>' . esc_html__( 'Quantity', 'frpsych-quantity-pricing' ) . '</th><th>' . esc_html__( 'Discount (%)', 'frpsych-quantity-pricing' ) . '</th><th>' . esc_html__( 'Price Each', 'frpsych-quantity-pricing' ) . '</th></tr></thead><tbody>';
		if ( $parent_has_rules ) {
			echo '<tr data-min="1" data-max="1" data-price="' . esc_attr( $payload['base_price_display'] ) . '" data-discount="0"><td>1</td><td>-</td><td>' . wp_kses_post( wc_price( $payload['base_price_display'] ) ) . '</td></tr>';

			foreach ( $payload['tiers'] as $tier ) {
				echo '<tr data-min="' . esc_attr( $tier['min_qty'] ) . '" data-max="' . esc_attr( null === $tier['max_qty'] ? '' : $tier['max_qty'] ) . '" data-price="' . esc_attr( $tier['price_display'] ) . '" data-discount="' . esc_attr( $tier['discount'] ) . '">';
				echo '<td>' . esc_html( $tier['label'] ) . '</td><td>' . esc_html( self::format_percent( $tier['discount'] ) ) . '</td><td>' . wp_kses_post( wc_price( $tier['price_display'] ) ) . '</td>';
				echo '</tr>';
			}
		} else {
			echo '<tr class="fqp-placeholder"><td colspan="3">' . esc_html__( 'Select product options to view quantity pricing.', 'frpsych-quantity-pricing' ) . '</td></tr>';
		}

		echo '</tbody></table>';
		echo '<div class="fqp-live-summary" data-show-active="' . esc_attr( $settings['show_active'] ? 'yes' : 'no' ) . '" data-show-unit="' . esc_attr( $settings['show_unit'] ? 'yes' : 'no' ) . '" data-show-total="' . esc_attr( $settings['show_total'] ? 'yes' : 'no' ) . '" data-show-savings="' . esc_attr( $settings['show_savings'] ? 'yes' : 'no' ) . '"' . ( $parent_has_rules ? '' : ' style="display:none"' ) . '>';
		echo '<div class="fqp-unit-row">' . esc_html__( 'Unit Price:', 'frpsych-quantity-pricing' ) . ' <strong data-fqp-unit>' . wp_kses_post( wc_price( $payload['base_price_display'] ) ) . '</strong></div>';
		echo '<div class="fqp-total-row">' . esc_html__( 'Total:', 'frpsych-quantity-pricing' ) . ' <strong data-fqp-total>' . wp_kses_post( wc_price( $payload['base_price_display'] ) ) . '</strong></div>';
		echo '<div class="fqp-savings-row" data-fqp-savings></div>';
		echo '</div></div>';
		do_action( 'fqp_after_pricing_table', $product );
	}

	public static function shortcode( array $atts = array() ): string {
		$atts = shortcode_atts( array( 'product_id' => 0 ), $atts, 'fqp_pricing_table' );
		$product = absint( $atts['product_id'] ) ? wc_get_product( absint( $atts['product_id'] ) ) : wc_get_product( get_the_ID() );

		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		ob_start();
		self::render_pricing_table( $product );
		return (string) ob_get_clean();
	}

	private static function product_has_rules( WC_Product $product ): bool {
		if ( FQP_Helper::is_enabled( $product->get_id() ) && ! empty( FQP_Helper::get_pricing_tiers( $product->get_id() ) ) ) {
			return true;
		}

		if ( ! $product->is_type( 'variable' ) ) {
			return false;
		}

		foreach ( $product->get_children() as $variation_id ) {
			if ( 'yes' === get_post_meta( $variation_id, FQP_Helper::META_VAR_OVERRIDE, true ) && FQP_Helper::is_enabled( $product->get_id(), absint( $variation_id ) ) && ! empty( FQP_Helper::get_pricing_tiers( $product->get_id(), absint( $variation_id ) ) ) ) {
				return true;
			}
		}

		return false;
	}

	public static function add_variation_payload( array $data, WC_Product $product, WC_Product_Variation $variation ): array {
		$data['fqp_pricing'] = FQP_Helper::get_product_payload( $variation, $product->get_id(), $variation->get_id() );
		return $data;
	}

	private static function format_percent( float $percent ): string {
		$formatted = rtrim( rtrim( number_format_i18n( $percent, 4 ), '0' ), wc_get_price_decimal_separator() );
		return $formatted . '%';
	}
}
