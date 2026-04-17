<?php

/**
 * Plugin Name:       Space Booking
 * Plugin URI:        https://example.com/space-booking
 * Description:       Hourly space rental & shared asset booking plugin with WooCommerce payments.
 * Requires Plugins:   woocommerce
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Senior WP Architect
 * License:           GPL-2.0-or-later
 * Text Domain:       space-booking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

// ── Constants ────────────────────────────────────────────────────────────────
define('SB_VERSION', '1.0.0');
define('SB_FILE', __FILE__);
define('SB_DIR', plugin_dir_path(__FILE__));
define('SB_URL', plugin_dir_url(__FILE__));
define('SB_ASSETS_URL', SB_URL . 'assets/');
define('SB_PLUGIN_SLUG', 'space-booking');

if (file_exists(SB_DIR . 'plugin-update-checker/plugin-update-checker.php')) {
	require SB_DIR . 'plugin-update-checker/plugin-update-checker.php';
	if (class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
		$myUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://github.com/xzud/space-booking/',
			__FILE__,
			'space-booking'
		);
		$myUpdateChecker->setBranch('main');
	}
}

// ── Autoloader ───────────────────────────────────────────────────────────────
spl_autoload_register(static function (string $class): void {
	$prefix = 'SpaceBooking\\';
	$base = SB_DIR . 'includes/';

	if (!str_starts_with($class, $prefix)) {
		return;
	}

	$relative = str_replace('\\', '/', substr($class, strlen($prefix)));
	$file = $base . $relative . '.php';

	if (is_readable($file)) {
		require $file;
	}
});

// ── Bootstrap ────────────────────────────────────────────────────────────────
add_action('plugins_loaded', static function (): void {
	\SpaceBooking\Plugin::instance()->boot();
});

register_activation_hook(__FILE__, [\SpaceBooking\Installer::class, 'activate']);
register_deactivation_hook(__FILE__, [\SpaceBooking\Installer::class, 'deactivate']);