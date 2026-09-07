=== DiscountFlow for WooCommerce ===
Contributors: seamkt
Tags: woocommerce, quantity pricing, bulk discounts, variable products
Requires at least: 6.5
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 1.0.3
License: GPLv2 or later

Per-product quantity-based tier pricing for WooCommerce.

== Description ==

DiscountFlow for WooCommerce adds configurable quantity discount tiers to WooCommerce product edit screens. It supports simple products, variable products, variation-specific overrides, percentage discounts, fixed unit prices, sale price handling, frontend pricing tables, live totals, and real cart/checkout price adjustments.

== Installation ==

1. Zip the folder: DiscountFlow for WooCommerce
2. Upload through WordPress Admin > Plugins > Add Plugin > Upload Plugin
3. Activate DiscountFlow for WooCommerce
4. Edit a WooCommerce product
5. Go to Product Data > Quantity Pricing
6. Enable pricing and configure tiers

== GitHub Update Workflow ==

Current plugin version: 1.0.3

For a new release:

1. Update the plugin header Version.
2. Update FQP_VERSION in frpsych-quantity-pricing.php.
3. Update this readme changelog.
4. Commit and push to GitHub.
5. Create a GitHub tag such as v1.0.3.
6. Create a GitHub Release.
7. Build discountFlow-for-WooCommerce.zip.
8. Attach discountFlow-for-WooCommerce.zip to the release.
9. Existing WordPress sites should detect the new version.
10. Admin clicks Update now.

The ZIP should contain the top-level DiscountFlow for WooCommerce folder directly. Do not nest another plugin folder inside it.

Existing product settings are stored as product meta and are not deleted during normal plugin updates.

== Changelog ==

= 1.0.3 =
Improved Quickview shortcode rendering for products with pricing tables already rendered elsewhere on the page.

= 1.0.2 =
Added Quickview shortcode support, manual update checking, improved tier validation, and Seamkt plugin branding.

= 1.0.1 =
Added Function Short code

= 1.0.0 =
Initial release.
