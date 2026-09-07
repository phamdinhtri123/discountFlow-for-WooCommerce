<?php
/**
 * Plugin Name: DiscountFlow for WooCommerce
 * Plugin URI: https://github.com/USERNAME/discountFlow-for-WooCommerce
 * Description: Per-product quantity-based tier pricing for WooCommerce products and variations.
 * Version: 1.0.3
 * Author: Seamkt
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * WC requires at least: 9.0
 * Text Domain: frpsych-quantity-pricing
 * Domain Path: /languages
 *
 * @package FRPSYCH_Quantity_Pricing
 */

defined( 'ABSPATH' ) || exit;

define( 'FQP_VERSION', '1.0.3' );
define( 'FQP_PLUGIN_FILE', __FILE__ );
define( 'FQP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FQP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FQP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'FQP_GITHUB_REPOSITORY', 'phamdinhtri123/discountFlow-for-WooCommerce' );
define( 'FQP_GITHUB_API_URL', 'https://api.github.com/repos/' . FQP_GITHUB_REPOSITORY . '/releases/latest' );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

require_once FQP_PLUGIN_DIR . 'includes/class-fqp-helper.php';
require_once FQP_PLUGIN_DIR . 'includes/class-fqp-admin.php';
require_once FQP_PLUGIN_DIR . 'includes/class-fqp-pricing.php';
require_once FQP_PLUGIN_DIR . 'includes/class-fqp-frontend.php';
require_once FQP_PLUGIN_DIR . 'includes/class-fqp-ajax.php';
require_once FQP_PLUGIN_DIR . 'includes/class-fqp-updater.php';

/**
 * Main plugin container.
 */
final class FQP_Plugin {
	/**
	 * Boot plugin.
	 */
	public static function init(): void {
		add_action( 'admin_notices', array( __CLASS__, 'maybe_show_dependency_notice' ) );

		if ( ! self::is_woocommerce_active() ) {
			return;
		}

		FQP_Admin::init();
		FQP_Pricing::init();
		FQP_Frontend::init();
		FQP_Ajax::init();
		FQP_Updater::init();
	}

	/**
	 * Check WooCommerce availability.
	 */
	private static function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Dependency notice.
	 */
	public static function maybe_show_dependency_notice(): void {
		if ( self::is_woocommerce_active() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'DiscountFlow for WooCommerce requires WooCommerce to be installed and active.', 'frpsych-quantity-pricing' );
		echo '</p></div>';
	}
}

add_action( 'plugins_loaded', array( 'FQP_Plugin', 'init' ) );
