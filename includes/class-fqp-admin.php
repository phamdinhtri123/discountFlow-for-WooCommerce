<?php
/**
 * Admin product and settings UI.
 *
 * @package FRPSYCH_Quantity_Pricing
 */

defined( 'ABSPATH' ) || exit;

final class FQP_Admin {
	public static function init(): void {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'render_product_panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product_data' ) );
		add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'render_variation_fields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'save_variation_data' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'woocommerce_get_sections_products', array( __CLASS__, 'add_settings_section' ) );
		add_filter( 'woocommerce_get_settings_products', array( __CLASS__, 'add_settings' ), 10, 2 );
	}

	public static function add_product_tab( array $tabs ): array {
		$tabs['fqp_quantity_pricing'] = array(
			'label'    => __( 'Quantity Pricing', 'frpsych-quantity-pricing' ),
			'target'   => 'fqp_quantity_pricing_panel',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 75,
		);

		return $tabs;
	}

	public static function render_product_panel(): void {
		global $post;

		$product_id = $post ? absint( $post->ID ) : 0;
		echo '<div id="fqp_quantity_pricing_panel" class="panel woocommerce_options_panel hidden">';
		wp_nonce_field( 'fqp_save_product', 'fqp_product_nonce' );
		self::render_settings_fields( $product_id, 'fqp', false );
		echo '</div>';
	}

	private static function render_settings_fields( int $object_id, string $prefix, bool $variation ): void {
		$defaults     = FQP_Helper::defaults();
		$mode         = FQP_Helper::get_pricing_mode( $object_id );
		$base_source  = FQP_Helper::get_base_source( $object_id );
		$settings     = FQP_Helper::get_display_settings( $object_id );
		$tiers        = FQP_Helper::get_pricing_tiers( $object_id );
		$name         = $variation ? "fqp_variation[{$object_id}]" : 'fqp';
		$field_prefix = $variation ? 'fqp_variation_' . $object_id : 'fqp';

		echo '<div class="fqp-admin-box" data-fqp-admin data-name="' . esc_attr( $name ) . '" data-mode="' . esc_attr( $mode ) . '">';

		if ( $variation ) {
			woocommerce_wp_checkbox(
				array(
					'id'            => $field_prefix . '_override',
					'name'          => $name . '[override]',
					'label'         => __( 'Enable variation-specific pricing', 'frpsych-quantity-pricing' ),
					'value'         => get_post_meta( $object_id, FQP_Helper::META_VAR_OVERRIDE, true ),
					'wrapper_class' => 'form-row form-row-full',
				)
			);
		}

		woocommerce_wp_checkbox(
			array(
				'id'            => $field_prefix . '_enabled',
				'name'          => $name . '[enabled]',
				'label'         => __( 'Enable Quantity Pricing', 'frpsych-quantity-pricing' ),
				'value'         => get_post_meta( $object_id, FQP_Helper::META_ENABLED, true ),
				'wrapper_class' => $variation ? 'form-row form-row-first' : '',
			)
		);

		woocommerce_wp_select(
			array(
				'id'            => $field_prefix . '_mode',
				'name'          => $name . '[mode]',
				'label'         => __( 'Pricing Mode', 'frpsych-quantity-pricing' ),
				'value'         => $mode,
				'options'       => array(
					FQP_Helper::MODE_PERCENT => __( 'Percentage Discount', 'frpsych-quantity-pricing' ),
					FQP_Helper::MODE_FIXED   => __( 'Fixed Unit Price', 'frpsych-quantity-pricing' ),
				),
				'wrapper_class' => $variation ? 'form-row form-row-last' : '',
			)
		);

		woocommerce_wp_select(
			array(
				'id'      => $field_prefix . '_base_source',
				'name'    => $name . '[base_source]',
				'label'   => __( 'Base Price Source', 'frpsych-quantity-pricing' ),
				'value'   => $base_source,
				'options' => array(
					'active'  => __( 'Active WooCommerce Price', 'frpsych-quantity-pricing' ),
					'regular' => __( 'Regular Price', 'frpsych-quantity-pricing' ),
				),
			)
		);

		echo '<div class="fqp-repeater-wrap">';
		echo '<h4>' . esc_html__( 'Quantity Pricing Tiers', 'frpsych-quantity-pricing' ) . '</h4>';
		echo '<table class="widefat fqp-tier-table"><thead><tr>';
		echo '<th class="fqp-handle-col"></th><th>' . esc_html__( 'Min Qty', 'frpsych-quantity-pricing' ) . '</th><th>' . esc_html__( 'Max Qty', 'frpsych-quantity-pricing' ) . '</th><th class="fqp-value-heading">' . esc_html__( 'Discount / Unit Price', 'frpsych-quantity-pricing' ) . '</th><th>' . esc_html__( 'Actions', 'frpsych-quantity-pricing' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $tiers as $index => $tier ) {
			self::render_tier_row( $name, $index, $tier, $mode );
		}

		echo '</tbody></table>';
		echo '<button type="button" class="button fqp-add-tier">+ ' . esc_html__( 'Add Tier', 'frpsych-quantity-pricing' ) . '</button>';
		echo '</div>';

		echo '<div class="fqp-display-options">';
		echo '<h4>' . esc_html__( 'Frontend Display', 'frpsych-quantity-pricing' ) . '</h4>';
		self::checkbox( $name, 'show_table', __( 'Show pricing table', 'frpsych-quantity-pricing' ), $settings['show_table'] );
		self::checkbox( $name, 'show_active', __( 'Show active tier', 'frpsych-quantity-pricing' ), $settings['show_active'] );
		self::checkbox( $name, 'show_unit', __( 'Show unit price', 'frpsych-quantity-pricing' ), $settings['show_unit'] );
		self::checkbox( $name, 'show_total', __( 'Show total', 'frpsych-quantity-pricing' ), $settings['show_total'] );
		self::checkbox( $name, 'show_savings', __( 'Show amount saved', 'frpsych-quantity-pricing' ), $settings['show_savings'] );

		woocommerce_wp_text_input(
			array(
				'id'          => $field_prefix . '_heading',
				'name'        => $name . '[heading]',
				'label'       => __( 'Heading', 'frpsych-quantity-pricing' ),
				'value'       => get_post_meta( $object_id, FQP_Helper::META_HEADING, true ) ?: $defaults['heading'],
				'placeholder' => $defaults['heading'],
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => $field_prefix . '_description',
				'name'        => $name . '[description]',
				'label'       => __( 'Description', 'frpsych-quantity-pricing' ),
				'value'       => get_post_meta( $object_id, FQP_Helper::META_DESCRIPTION, true ) ?: $defaults['description'],
				'placeholder' => $defaults['description'],
			)
		);

		woocommerce_wp_select(
			array(
				'id'      => $field_prefix . '_position',
				'name'    => $name . '[position]',
				'label'   => __( 'Table Position', 'frpsych-quantity-pricing' ),
				'value'   => $settings['position'],
				'options' => array(
					'after_price'        => __( 'After price', 'frpsych-quantity-pricing' ),
					'before_add_to_cart' => __( 'Before Add to Cart', 'frpsych-quantity-pricing' ),
					'after_add_to_cart'  => __( 'After Add to Cart', 'frpsych-quantity-pricing' ),
				),
			)
		);
		echo '</div>';
		echo '<div class="fqp-preview"><h4>' . esc_html__( 'Preview', 'frpsych-quantity-pricing' ) . '</h4><div class="fqp-preview-lines"></div></div>';
		echo '</div>';
	}

	private static function checkbox( string $name, string $key, string $label, bool $checked ): void {
		echo '<p class="form-field"><label><input type="checkbox" name="' . esc_attr( $name . '[' . $key . ']' ) . '" value="yes" ' . checked( $checked, true, false ) . '> ' . esc_html( $label ) . '</label></p>';
	}

	private static function render_tier_row( string $name, int $index, array $tier, string $mode ): void {
		$discount = isset( $tier['discount'] ) ? $tier['discount'] : '';
		$price    = isset( $tier['price'] ) ? $tier['price'] : '';

		echo '<tr class="fqp-tier-row">';
		echo '<td class="fqp-handle" aria-label="' . esc_attr__( 'Drag to reorder', 'frpsych-quantity-pricing' ) . '">::</td>';
		echo '<td><input type="number" min="2" step="1" name="' . esc_attr( "{$name}[tiers][{$index}][min_qty]" ) . '" value="' . esc_attr( $tier['min_qty'] ?? '' ) . '"></td>';
		echo '<td><input type="number" min="2" step="1" name="' . esc_attr( "{$name}[tiers][{$index}][max_qty]" ) . '" value="' . esc_attr( $tier['max_qty'] ?? '' ) . '" placeholder="' . esc_attr__( 'Unlimited', 'frpsych-quantity-pricing' ) . '"></td>';
		echo '<td>';
		echo '<input class="fqp-discount-field" type="number" min="0" max="100" step="0.0001" name="' . esc_attr( "{$name}[tiers][{$index}][discount]" ) . '" value="' . esc_attr( $discount ) . '"' . ( FQP_Helper::MODE_FIXED === $mode ? ' style="display:none"' : '' ) . '>';
		echo '<input class="fqp-price-field" type="number" min="0" step="0.0001" name="' . esc_attr( "{$name}[tiers][{$index}][price]" ) . '" value="' . esc_attr( $price ) . '"' . ( FQP_Helper::MODE_FIXED === $mode ? '' : ' style="display:none"' ) . '>';
		echo '</td>';
		echo '<td><button type="button" class="button fqp-remove-tier">' . esc_html__( 'Remove', 'frpsych-quantity-pricing' ) . '</button></td>';
		echo '</tr>';
	}

	public static function save_product_data( WC_Product $product ): void {
		if ( ! isset( $_POST['fqp_product_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fqp_product_nonce'] ) ), 'fqp_save_product' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_product', $product->get_id() ) ) {
			return;
		}

		$data = isset( $_POST['fqp'] ) && is_array( $_POST['fqp'] ) ? wp_unslash( $_POST['fqp'] ) : array();
		self::save_object_meta( $product->get_id(), $data );
	}

	public static function render_variation_fields( int $loop, array $variation_data, WP_Post $variation ): void {
		echo '<div class="form-row form-row-full fqp-variation-panel"><h4>' . esc_html__( 'Quantity Pricing Override', 'frpsych-quantity-pricing' ) . '</h4>';
		self::render_settings_fields( absint( $variation->ID ), 'fqp_variation_' . absint( $variation->ID ), true );
		echo '</div>';
	}

	public static function save_variation_data( int $variation_id, int $i ): void {
		if ( ! isset( $_POST['fqp_product_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fqp_product_nonce'] ) ), 'fqp_save_product' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_product', $variation_id ) ) {
			return;
		}

		$data = isset( $_POST['fqp_variation'][ $variation_id ] ) && is_array( $_POST['fqp_variation'][ $variation_id ] )
			? wp_unslash( $_POST['fqp_variation'][ $variation_id ] )
			: array();

		self::save_object_meta( $variation_id, $data, true );
	}

	private static function save_object_meta( int $object_id, array $data, bool $variation = false ): void {
		$mode = isset( $data['mode'] ) && FQP_Helper::MODE_FIXED === sanitize_text_field( $data['mode'] ) ? FQP_Helper::MODE_FIXED : FQP_Helper::MODE_PERCENT;
		$raw_tiers = isset( $data['tiers'] ) && is_array( $data['tiers'] ) ? $data['tiers'] : array();

		foreach ( FQP_Helper::validate_tiers( $raw_tiers, $mode ) as $message ) {
			if ( class_exists( 'WC_Admin_Meta_Boxes' ) ) {
				WC_Admin_Meta_Boxes::add_error( $message );
			}
		}

		update_post_meta( $object_id, FQP_Helper::META_ENABLED, isset( $data['enabled'] ) ? 'yes' : 'no' );
		update_post_meta( $object_id, FQP_Helper::META_MODE, $mode );
		update_post_meta( $object_id, FQP_Helper::META_BASE_SOURCE, isset( $data['base_source'] ) && 'regular' === $data['base_source'] ? 'regular' : 'active' );
		update_post_meta( $object_id, FQP_Helper::META_TIERS, FQP_Helper::sanitize_tiers( $raw_tiers, $mode ) );
		update_post_meta( $object_id, FQP_Helper::META_SHOW_TABLE, isset( $data['show_table'] ) ? 'yes' : 'no' );
		update_post_meta( $object_id, FQP_Helper::META_SHOW_ACTIVE, isset( $data['show_active'] ) ? 'yes' : 'no' );
		update_post_meta( $object_id, FQP_Helper::META_SHOW_UNIT, isset( $data['show_unit'] ) ? 'yes' : 'no' );
		update_post_meta( $object_id, FQP_Helper::META_SHOW_TOTAL, isset( $data['show_total'] ) ? 'yes' : 'no' );
		update_post_meta( $object_id, FQP_Helper::META_SHOW_SAVINGS, isset( $data['show_savings'] ) ? 'yes' : 'no' );
		update_post_meta( $object_id, FQP_Helper::META_HEADING, isset( $data['heading'] ) ? sanitize_text_field( $data['heading'] ) : '' );
		update_post_meta( $object_id, FQP_Helper::META_DESCRIPTION, isset( $data['description'] ) ? sanitize_text_field( $data['description'] ) : '' );

		$position = isset( $data['position'] ) ? sanitize_text_field( $data['position'] ) : 'before_add_to_cart';
		update_post_meta( $object_id, FQP_Helper::META_TABLE_POS, in_array( $position, array( 'after_price', 'before_add_to_cart', 'after_add_to_cart' ), true ) ? $position : 'before_add_to_cart' );

		if ( $variation ) {
			update_post_meta( $object_id, FQP_Helper::META_VAR_OVERRIDE, isset( $data['override'] ) ? 'yes' : 'no' );
		}
	}

	public static function enqueue_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->id ) {
			return;
		}

		wp_enqueue_style( 'fqp-admin', FQP_PLUGIN_URL . 'assets/css/admin.css', array(), FQP_VERSION );
		wp_enqueue_script( 'fqp-admin', FQP_PLUGIN_URL . 'assets/js/admin.js', array(), FQP_VERSION, true );
		wp_localize_script(
			'fqp-admin',
			'fqpAdmin',
			array(
				'i18n' => array(
					'unlimited' => __( 'Unlimited', 'frpsych-quantity-pricing' ),
					'qty_one'   => __( 'Quantity 1 uses the normal WooCommerce price.', 'frpsych-quantity-pricing' ),
				),
			)
		);
	}

	public static function add_settings_section( array $sections ): array {
		$sections['fqp_quantity_pricing'] = __( 'Quantity Pricing', 'frpsych-quantity-pricing' );
		return $sections;
	}

	public static function add_settings( array $settings, string $section ): array {
		if ( 'fqp_quantity_pricing' !== $section ) {
			return $settings;
		}

		return array(
			array(
				'title' => __( 'Quantity Pricing Defaults', 'frpsych-quantity-pricing' ),
				'type'  => 'title',
				'id'    => 'fqp_defaults_title',
			),
			array(
				'title'   => __( 'Default primary color', 'frpsych-quantity-pricing' ),
				'id'      => 'fqp_primary_color',
				'default' => '#0D405A',
				'type'    => 'color',
			),
			array(
				'title'   => __( 'Default border radius', 'frpsych-quantity-pricing' ),
				'id'      => 'fqp_border_radius',
				'default' => 18,
				'type'    => 'number',
			),
			array(
				'title'   => __( 'Default heading', 'frpsych-quantity-pricing' ),
				'id'      => 'fqp_default_heading',
				'default' => __( 'Buy More, Save More', 'frpsych-quantity-pricing' ),
				'type'    => 'text',
			),
			array(
				'title'   => __( 'Default description', 'frpsych-quantity-pricing' ),
				'id'      => 'fqp_default_description',
				'default' => __( 'Discounts are automatically applied based on quantity.', 'frpsych-quantity-pricing' ),
				'type'    => 'text',
			),
			array(
				'title'   => __( 'Default display settings', 'frpsych-quantity-pricing' ),
				'id'      => 'fqp_default_show_table',
				'default' => 'yes',
				'type'    => 'checkbox',
				'desc'    => __( 'Show pricing table', 'frpsych-quantity-pricing' ),
			),
			array(
				'id'      => 'fqp_default_show_unit',
				'default' => 'yes',
				'type'    => 'checkbox',
				'desc'    => __( 'Show unit price', 'frpsych-quantity-pricing' ),
			),
			array(
				'id'      => 'fqp_default_show_active',
				'default' => 'yes',
				'type'    => 'checkbox',
				'desc'    => __( 'Show active tier', 'frpsych-quantity-pricing' ),
			),
			array(
				'id'      => 'fqp_default_show_total',
				'default' => 'yes',
				'type'    => 'checkbox',
				'desc'    => __( 'Show total', 'frpsych-quantity-pricing' ),
			),
			array(
				'id'      => 'fqp_default_show_savings',
				'default' => 'yes',
				'type'    => 'checkbox',
				'desc'    => __( 'Show savings', 'frpsych-quantity-pricing' ),
			),
			array(
				'id'   => 'fqp_defaults_end',
				'type' => 'sectionend',
			),
		);
	}
}
