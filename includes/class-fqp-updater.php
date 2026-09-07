<?php
/**
 * GitHub release updater.
 *
 * @package FRPSYCH_Quantity_Pricing
 */

defined( 'ABSPATH' ) || exit;

final class FQP_Updater {
	private const CACHE_KEY = '_fqp_github_release_cache';
	private const SLUG      = 'frpsych-quantity-pricing';

	public static function init(): void {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_for_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
	}

	public static function check_for_update( object $transient ): object {
		if ( empty( $transient->checked ) || empty( $transient->checked[ FQP_PLUGIN_BASENAME ] ) ) {
			return $transient;
		}

		$release = self::get_release();
		if ( empty( $release['version'] ) || version_compare( FQP_VERSION, $release['version'], '>=' ) ) {
			return $transient;
		}

		$package = self::get_package_url( $release );
		if ( ! $package ) {
			return $transient;
		}

		$transient->response[ FQP_PLUGIN_BASENAME ] = (object) array(
			'id'          => FQP_PLUGIN_BASENAME,
			'slug'        => self::SLUG,
			'plugin'      => FQP_PLUGIN_BASENAME,
			'new_version' => $release['version'],
			'url'         => $release['html_url'] ?? 'https://github.com/' . FQP_GITHUB_REPOSITORY,
			'package'     => $package,
			'tested'      => '6.6',
			'requires'    => '6.5',
			'requires_php'=> '8.1',
		);

		return $transient;
	}

	public static function plugin_info( mixed $result, string $action, object $args ): mixed {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}

		$release = self::get_release();
		if ( empty( $release['version'] ) ) {
			return $result;
		}

		return (object) array(
			'name'          => 'DiscountFlow for WooCommerce',
			'slug'          => self::SLUG,
			'version'       => $release['version'],
			'author'        => 'FRPSYCH',
			'homepage'      => $release['html_url'] ?? 'https://github.com/' . FQP_GITHUB_REPOSITORY,
			'requires'      => '6.5',
			'requires_php'  => '8.1',
			'tested'        => '6.6',
			'download_link' => self::get_package_url( $release ),
			'sections'      => array(
				'description' => __( 'Per-product quantity-based tier pricing for WooCommerce products and variations.', 'frpsych-quantity-pricing' ),
				'changelog'   => wp_kses_post( nl2br( $release['body'] ?? __( 'See the GitHub release for details.', 'frpsych-quantity-pricing' ) ) ),
			),
			'banners'       => array(),
		);
	}

	private static function get_release(): array {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			FQP_GITHUB_API_URL,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'FRPSYCH-Quantity-Pricing/' . FQP_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_site_transient( self::CACHE_KEY, array(), 6 * HOUR_IN_SECONDS );
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			set_site_transient( self::CACHE_KEY, array(), 6 * HOUR_IN_SECONDS );
			return array();
		}

		$body['version'] = ltrim( sanitize_text_field( $body['tag_name'] ), 'vV' );
		set_site_transient( self::CACHE_KEY, $body, 6 * HOUR_IN_SECONDS );

		return $body;
	}

	private static function get_package_url( array $release ): string {
		if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( isset( $asset['name'], $asset['browser_download_url'] ) && 'discountFlow-for-WooCommerce.zip' === $asset['name'] ) {
					return esc_url_raw( $asset['browser_download_url'] );
				}
			}
		}

		return isset( $release['zipball_url'] ) ? esc_url_raw( $release['zipball_url'] ) : '';
	}
}
