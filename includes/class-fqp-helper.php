<?php
/**
 * Shared quantity pricing helpers.
 *
 * @package FRPSYCH_Quantity_Pricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Centralized helper methods.
 */
final class FQP_Helper {
	public const META_ENABLED       = '_fqp_enabled';
	public const META_MODE          = '_fqp_pricing_mode';
	public const META_BASE_SOURCE   = '_fqp_base_price_source';
	public const META_TIERS         = '_fqp_pricing_tiers';
	public const META_SHOW_TABLE    = '_fqp_show_table';
	public const META_SHOW_ACTIVE   = '_fqp_show_active';
	public const META_SHOW_UNIT     = '_fqp_show_unit_price';
	public const META_SHOW_TOTAL    = '_fqp_show_total';
	public const META_SHOW_SAVINGS  = '_fqp_show_savings';
	public const META_HEADING       = '_fqp_heading';
	public const META_DESCRIPTION   = '_fqp_description';
	public const META_TABLE_POS     = '_fqp_table_position';
	public const META_VAR_OVERRIDE  = '_fqp_variation_override';

	public const MODE_PERCENT = 'percentage';
	public const MODE_FIXED   = 'fixed';

	/**
	 * Default plugin settings.
	 */
	public static function defaults(): array {
		return array(
			'primary_color' => get_option( 'fqp_primary_color', '#0D405A' ),
			'border_radius' => absint( get_option( 'fqp_border_radius', 18 ) ),
			'heading'       => get_option( 'fqp_default_heading', __( 'Buy More, Save More', 'frpsych-quantity-pricing' ) ),
			'description'   => get_option( 'fqp_default_description', __( 'Discounts are automatically applied based on quantity.', 'frpsych-quantity-pricing' ) ),
			'show_table'    => 'yes' === get_option( 'fqp_default_show_table', 'yes' ),
			'show_active'   => 'yes' === get_option( 'fqp_default_show_active', 'yes' ),
			'show_unit'     => 'yes' === get_option( 'fqp_default_show_unit', 'yes' ),
			'show_total'    => 'yes' === get_option( 'fqp_default_show_total', 'yes' ),
			'show_savings'  => 'yes' === get_option( 'fqp_default_show_savings', 'yes' ),
		);
	}

	/**
	 * Is pricing enabled for product/variation context.
	 */
	public static function is_enabled( int $product_id, int $variation_id = 0 ): bool {
		$rule_id = self::get_rule_product_id( $product_id, $variation_id );
		$enabled = $rule_id > 0 && 'yes' === get_post_meta( $rule_id, self::META_ENABLED, true );

		return (bool) apply_filters( 'fqp_is_enabled', $enabled, $product_id, $variation_id, $rule_id );
	}

	/**
	 * Return product ID that owns active rules.
	 */
	public static function get_rule_product_id( int $product_id, int $variation_id = 0 ): int {
		if ( $variation_id > 0 && 'yes' === get_post_meta( $variation_id, self::META_VAR_OVERRIDE, true ) ) {
			return $variation_id;
		}

		return $product_id;
	}

	/**
	 * Get pricing mode.
	 */
	public static function get_pricing_mode( int $product_id, int $variation_id = 0 ): string {
		$rule_id = self::get_rule_product_id( $product_id, $variation_id );
		$mode    = get_post_meta( $rule_id, self::META_MODE, true );

		return in_array( $mode, array( self::MODE_PERCENT, self::MODE_FIXED ), true ) ? $mode : self::MODE_PERCENT;
	}

	/**
	 * Get base price source.
	 */
	public static function get_base_source( int $product_id, int $variation_id = 0 ): string {
		$rule_id = self::get_rule_product_id( $product_id, $variation_id );
		$source  = get_post_meta( $rule_id, self::META_BASE_SOURCE, true );

		return 'regular' === $source ? 'regular' : 'active';
	}

	/**
	 * Sanitize tier rows.
	 */
	public static function sanitize_tiers( mixed $tiers, string $mode ): array {
		if ( ! is_array( $tiers ) ) {
			return array();
		}

		$clean = array();

		foreach ( $tiers as $tier ) {
			if ( ! is_array( $tier ) ) {
				continue;
			}

			$min = isset( $tier['min_qty'] ) ? absint( $tier['min_qty'] ) : 0;
			$max = isset( $tier['max_qty'] ) && '' !== (string) $tier['max_qty'] ? absint( $tier['max_qty'] ) : null;

			if ( $min < 2 ) {
				continue;
			}

			if ( null !== $max && $max < $min ) {
				continue;
			}

			$row = array(
				'min_qty' => $min,
				'max_qty' => $max,
			);

			if ( self::MODE_FIXED === $mode ) {
				$row['price'] = isset( $tier['price'] ) ? max( 0, wc_format_decimal( wp_unslash( $tier['price'] ) ) ) : 0;
			} else {
				$row['discount'] = isset( $tier['discount'] ) ? min( 100, max( 0, (float) wc_format_decimal( wp_unslash( $tier['discount'] ) ) ) ) : 0;
			}

			$clean[] = $row;
		}

		usort(
			$clean,
			static fn( array $a, array $b ): int => $a['min_qty'] <=> $b['min_qty']
		);

		$valid = array();
		$last_max = 1;
		$has_unlimited = false;

		foreach ( $clean as $tier ) {
			if ( $has_unlimited || $tier['min_qty'] <= $last_max ) {
				continue;
			}

			$valid[] = $tier;

			if ( null === $tier['max_qty'] ) {
				$has_unlimited = true;
			} else {
				$last_max = $tier['max_qty'];
			}
		}

		return $valid;
	}

	/**
	 * Validate raw tier rows and return notices.
	 */
	public static function validate_tiers( mixed $tiers, string $mode ): array {
		if ( ! is_array( $tiers ) ) {
			return array();
		}

		$messages = array();
		$ranges   = array();

		foreach ( $tiers as $index => $tier ) {
			$row = absint( $index ) + 1;
			$min = isset( $tier['min_qty'] ) ? absint( $tier['min_qty'] ) : 0;
			$max = isset( $tier['max_qty'] ) && '' !== (string) $tier['max_qty'] ? absint( $tier['max_qty'] ) : null;

			if ( $min < 2 ) {
				$messages[] = sprintf( __( 'Quantity Pricing row %d: minimum quantity must be 2 or greater.', 'frpsych-quantity-pricing' ), $row );
			}
			if ( null !== $max && $max < $min ) {
				$messages[] = sprintf( __( 'Quantity Pricing row %d: maximum quantity must be greater than or equal to minimum quantity.', 'frpsych-quantity-pricing' ), $row );
			}
			if ( self::MODE_FIXED === $mode ) {
				$price = isset( $tier['price'] ) ? (float) wc_format_decimal( wp_unslash( $tier['price'] ) ) : 0;
				if ( $price < 0 ) {
					$messages[] = sprintf( __( 'Quantity Pricing row %d: fixed unit price cannot be negative.', 'frpsych-quantity-pricing' ), $row );
				}
			} else {
				$discount = isset( $tier['discount'] ) ? (float) wc_format_decimal( wp_unslash( $tier['discount'] ) ) : 0;
				if ( $discount < 0 || $discount > 100 ) {
					$messages[] = sprintf( __( 'Quantity Pricing row %d: discount must be between 0 and 100.', 'frpsych-quantity-pricing' ), $row );
				}
			}

			if ( $min >= 2 && ( null === $max || $max >= $min ) ) {
				$ranges[] = array( 'row' => $row, 'min' => $min, 'max' => $max );
			}
		}

		usort( $ranges, static fn( array $a, array $b ): int => $a['min'] <=> $b['min'] );
		$previous = null;

		foreach ( $ranges as $range ) {
			if ( null !== $previous && ( null === $previous['max'] || $range['min'] <= $previous['max'] ) ) {
				$messages[] = sprintf(
					__( 'Quantity Pricing rows %1$d and %2$d overlap. Use adjacent ranges such as 2-9 and 10-20; leave Max Qty blank only on the final tier.', 'frpsych-quantity-pricing' ),
					$previous['row'],
					$range['row']
				);
				$previous = $range;
				continue;
			}

			$previous = $range;
		}

		return array_unique( $messages );
	}

	/**
	 * Get sanitized tiers.
	 */
	public static function get_pricing_tiers( int $product_id, int $variation_id = 0 ): array {
		$rule_id = self::get_rule_product_id( $product_id, $variation_id );
		$tiers   = get_post_meta( $rule_id, self::META_TIERS, true );
		$mode    = self::get_pricing_mode( $product_id, $variation_id );
		$tiers   = self::sanitize_tiers( is_array( $tiers ) ? $tiers : array(), $mode );

		return (array) apply_filters( 'fqp_pricing_tiers', $tiers, $product_id, $variation_id, $rule_id );
	}

	/**
	 * Base price for calculations.
	 */
	public static function get_base_price( WC_Product $product, int $product_id = 0, int $variation_id = 0 ): float {
		$product_id = $product_id ?: ( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() );
		$source     = self::get_base_source( $product_id, $variation_id );

		if ( 'regular' === $source ) {
			$price = $product->get_regular_price( 'edit' );
		} else {
			$sale_price = $product->get_sale_price( 'edit' );
			$price      = $product->is_on_sale( 'edit' ) && '' !== $sale_price ? $sale_price : $product->get_regular_price( 'edit' );
			$price      = '' !== $price ? $price : $product->get_price( 'edit' );
		}

		$price = '' === $price ? 0.0 : (float) wc_format_decimal( $price, wc_get_price_decimals() );

		return (float) apply_filters( 'fqp_base_price', $price, $product, $product_id, $variation_id );
	}

	/**
	 * Find matching tier.
	 */
	public static function find_matching_tier( int $product_id, int $quantity, int $variation_id = 0 ): ?array {
		if ( $quantity < 2 || ! self::is_enabled( $product_id, $variation_id ) ) {
			return null;
		}

		foreach ( self::get_pricing_tiers( $product_id, $variation_id ) as $tier ) {
			$max = $tier['max_qty'] ?? null;
			if ( $quantity >= $tier['min_qty'] && ( null === $max || $quantity <= $max ) ) {
				return $tier;
			}
		}

		return null;
	}

	/**
	 * Calculate unit price.
	 */
	public static function calculate_unit_price( WC_Product $product, int $quantity, int $variation_id = 0 ): float {
		$product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		$base_price = self::get_base_price( $product, $product_id, $variation_id );

		return self::calculate_unit_price_from_base( $product, $quantity, $variation_id, $base_price );
	}

	/**
	 * Calculate unit price from an already resolved base price.
	 */
	public static function calculate_unit_price_from_base( WC_Product $product, int $quantity, int $variation_id, float $base_price ): float {
		$product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		$tier       = self::find_matching_tier( $product_id, $quantity, $variation_id );

		if ( ! $tier ) {
			return (float) apply_filters( 'fqp_calculated_price', $base_price, $product, $quantity, null, $base_price );
		}

		if ( self::MODE_FIXED === self::get_pricing_mode( $product_id, $variation_id ) ) {
			$price = isset( $tier['price'] ) ? (float) $tier['price'] : $base_price;
		} else {
			$discount = isset( $tier['discount'] ) ? (float) $tier['discount'] : 0.0;
			$price    = (float) wc_format_decimal( $base_price * ( 1 - ( $discount / 100 ) ), wc_get_price_decimals() );
		}

		$price = max( 0, $price );

		return (float) apply_filters( 'fqp_calculated_price', $price, $product, $quantity, $tier, $base_price );
	}

	/**
	 * Effective discount percent.
	 */
	public static function get_discount_percent( float $base_price, float $tier_price ): float {
		if ( $base_price <= 0 || $tier_price >= $base_price ) {
			return 0.0;
		}

		return round( ( ( $base_price - $tier_price ) / $base_price ) * 100, 4 );
	}

	/**
	 * Human-readable range label.
	 */
	public static function range_label( array $tier ): string {
		$min = absint( $tier['min_qty'] ?? 0 );
		$max = isset( $tier['max_qty'] ) ? $tier['max_qty'] : null;

		if ( null === $max || '' === $max ) {
			return $min . '+';
		}

		return (int) $max === $min ? (string) $min : $min . '-' . absint( $max );
	}

	/**
	 * Get display option with global fallback.
	 */
	public static function get_display_settings( int $product_id ): array {
		$defaults = self::defaults();

		return array(
			'show_table'   => self::bool_meta_or_default( $product_id, self::META_SHOW_TABLE, $defaults['show_table'] ),
			'show_active'  => self::bool_meta_or_default( $product_id, self::META_SHOW_ACTIVE, $defaults['show_active'] ),
			'show_unit'    => self::bool_meta_or_default( $product_id, self::META_SHOW_UNIT, $defaults['show_unit'] ),
			'show_total'   => self::bool_meta_or_default( $product_id, self::META_SHOW_TOTAL, $defaults['show_total'] ),
			'show_savings' => self::bool_meta_or_default( $product_id, self::META_SHOW_SAVINGS, $defaults['show_savings'] ),
			'heading'      => self::text_meta_or_default( $product_id, self::META_HEADING, $defaults['heading'] ),
			'description'  => self::text_meta_or_default( $product_id, self::META_DESCRIPTION, $defaults['description'] ),
			'position'     => self::text_meta_or_default( $product_id, self::META_TABLE_POS, 'before_add_to_cart' ),
			'color'        => sanitize_hex_color( $defaults['primary_color'] ) ?: '#0D405A',
			'radius'       => absint( $defaults['border_radius'] ),
		);
	}

	private static function bool_meta_or_default( int $product_id, string $key, bool $default ): bool {
		$value = get_post_meta( $product_id, $key, true );
		return '' === $value ? $default : 'yes' === $value;
	}

	private static function text_meta_or_default( int $product_id, string $key, string $default ): string {
		$value = get_post_meta( $product_id, $key, true );
		return '' === $value ? $default : sanitize_text_field( $value );
	}

	/**
	 * JS-friendly payload.
	 */
	public static function get_product_payload( WC_Product $product, int $product_id = 0, int $variation_id = 0 ): array {
		$product_id  = $product_id ?: ( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() );
		$base_price  = self::get_base_price( $product, $product_id, $variation_id );
		$mode        = self::get_pricing_mode( $product_id, $variation_id );
		$tiers       = array();

		foreach ( self::get_pricing_tiers( $product_id, $variation_id ) as $tier ) {
			$tier_price = self::MODE_FIXED === $mode
				? (float) ( $tier['price'] ?? $base_price )
				: (float) wc_format_decimal( $base_price * ( 1 - ( (float) ( $tier['discount'] ?? 0 ) / 100 ) ), wc_get_price_decimals() );

			$tiers[] = array(
				'min_qty'       => absint( $tier['min_qty'] ),
				'max_qty'       => isset( $tier['max_qty'] ) ? $tier['max_qty'] : null,
				'label'         => self::range_label( $tier ),
				'price'         => $tier_price,
				'price_display' => (float) wc_get_price_to_display( $product, array( 'price' => $tier_price ) ),
				'discount'      => self::get_discount_percent( $base_price, $tier_price ),
			);
		}

		return array(
			'enabled'            => self::is_enabled( $product_id, $variation_id ),
			'base_price'         => $base_price,
			'base_price_display' => (float) wc_get_price_to_display( $product, array( 'price' => $base_price ) ),
			'base_price_html'    => wc_price( wc_get_price_to_display( $product, array( 'price' => $base_price ) ) ),
			'tiers'              => $tiers,
		);
	}
}

function fqp_get_pricing_tiers( $product_id ) {
	return FQP_Helper::get_pricing_tiers( absint( $product_id ) );
}

function fqp_get_base_price( $product ) {
	return $product instanceof WC_Product ? FQP_Helper::get_base_price( $product ) : 0.0;
}

function fqp_find_matching_tier( $product_id, $quantity, $variation_id = 0 ) {
	return FQP_Helper::find_matching_tier( absint( $product_id ), absint( $quantity ), absint( $variation_id ) );
}

function fqp_calculate_unit_price( $product, $quantity, $variation_id = 0 ) {
	return $product instanceof WC_Product ? FQP_Helper::calculate_unit_price( $product, absint( $quantity ), absint( $variation_id ) ) : 0.0;
}

function fqp_get_discount_percent( $base_price, $tier_price ) {
	return FQP_Helper::get_discount_percent( (float) $base_price, (float) $tier_price );
}
