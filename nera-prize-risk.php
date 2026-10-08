<?php
/**
 * Plugin Name: Nera – Prize Risk
 * Plugin URI: https://github.com/Nera-Marketing/nera-prize-risk-plugin
 * Description: Prize cost, break-even and live exposure figures for Lottery for WooCommerce competitions. Admin only; nothing renders on the front end.
 * Version: 0.1.0
 * Author: Nera
 * Text Domain: nera-prize-risk
 * Requires at least: 6.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Requires Plugins: lottery-for-woocommerce, woocommerce
 */

defined( 'ABSPATH' ) || exit;

use YahnisElsts\PluginUpdateChecker\v5p5\Vcs\GitHubApi;

define( 'NERA_PRIZE_RISK_VERSION', '0.1.0' );
define( 'NERA_PRIZE_RISK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NERA_PRIZE_RISK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'NERA_PRIZE_RISK_PLUGIN_FILE', __FILE__ );

/** Lottery for WooCommerce main file (plugin slug / folder). */
const NERA_PRIZE_RISK_LFW_PLUGIN_FILE = 'lottery-for-woocommerce/lottery-for-woocommerce.php';

/** WooCommerce main file. */
const NERA_PRIZE_RISK_WC_PLUGIN_FILE = 'woocommerce/woocommerce.php';

// Pure calculation engine: no WordPress calls, safe to load always.
require_once NERA_PRIZE_RISK_PLUGIN_DIR . 'inc/class-nera-prize-risk-calc.php';

/**
 * GitHub update checker (same setup as nera-instant-win-threshold).
 *
 * The 4th argument to `PucFactory::buildUpdateChecker` is the check interval in hours.
 * A custom release filter plus maxReleases > 1 makes PUC read paginated /releases instead of /releases/latest.
 * PUC reads the remote readme.txt only when readme.txt exists locally, so keep it in the package.
 *
 * @link https://github.com/YahnisElsts/plugin-update-checker
 */
if ( ! defined( 'NERA_PRIZE_RISK_DISABLE_GITHUB_UPDATES' ) || ! NERA_PRIZE_RISK_DISABLE_GITHUB_UPDATES ) {
	$nera_prize_risk_github_repo = 'https://github.com/Nera-Marketing/nera-prize-risk-plugin/';
	if ( defined( 'NERA_PRIZE_RISK_GITHUB_REPO_URL' ) && is_string( NERA_PRIZE_RISK_GITHUB_REPO_URL ) && NERA_PRIZE_RISK_GITHUB_REPO_URL !== '' ) {
		$nera_prize_risk_github_repo = NERA_PRIZE_RISK_GITHUB_REPO_URL;
	}
	$nera_prize_risk_github_repo = apply_filters( 'nera_prize_risk_github_repo_url', $nera_prize_risk_github_repo );

	$nera_prize_risk_puc_loader = NERA_PRIZE_RISK_PLUGIN_DIR . 'lib/plugin-update-checker/load-v5p5.php';
	if ( is_readable( $nera_prize_risk_puc_loader ) ) {
		require_once $nera_prize_risk_puc_loader;
		$nera_prize_risk_update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			$nera_prize_risk_github_repo,
			__FILE__,
			'nera-prize-risk',
			6
		);
		$nera_prize_risk_update_checker->setBranch( 'main' );

		if ( defined( 'NERA_PRIZE_RISK_GITHUB_TOKEN' ) && is_string( NERA_PRIZE_RISK_GITHUB_TOKEN ) && NERA_PRIZE_RISK_GITHUB_TOKEN !== '' ) {
			$nera_prize_risk_update_checker->setAuthentication( NERA_PRIZE_RISK_GITHUB_TOKEN );
		}

		$nera_prize_risk_puc_vcs = $nera_prize_risk_update_checker->getVcsApi();
		if ( $nera_prize_risk_puc_vcs instanceof GitHubApi ) {
			$nera_prize_risk_puc_vcs->setReleaseFilter(
				static function ( $version_number, $release_object ) {
					unset( $version_number, $release_object );
					return true;
				},
				\YahnisElsts\PluginUpdateChecker\v5p5\Vcs\Api::RELEASE_FILTER_SKIP_PRERELEASE,
				20
			);
			$nera_prize_risk_puc_vcs->enableReleaseAssets();
		}
	}
}

/**
 * Declare HPOS (custom order tables) compatibility.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Names of required plugins that are not active.
 *
 * @return string[]
 */
function nera_prize_risk_missing_dependencies() {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$missing = array();
	if ( ! class_exists( 'WooCommerce' ) && ! is_plugin_active( NERA_PRIZE_RISK_WC_PLUGIN_FILE ) ) {
		$missing[] = 'WooCommerce';
	}
	if ( ! is_plugin_active( NERA_PRIZE_RISK_LFW_PLUGIN_FILE ) ) {
		$missing[] = 'Lottery for WooCommerce';
	}

	return $missing;
}

/**
 * Boot: load the plugin only when LTY and WooCommerce are active; otherwise show an admin notice.
 *
 * @return void
 */
function nera_prize_risk_boot() {
	$missing = nera_prize_risk_missing_dependencies();

	if ( ! empty( $missing ) ) {
		add_action(
			'admin_notices',
			static function () use ( $missing ) {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					sprintf(
						/* translators: %s: comma-separated list of missing plugin names */
						esc_html__( 'Nera – Prize Risk needs these plugins active: %s. The plugin is not loaded until they are.', 'nera-prize-risk' ),
						esc_html( implode( ', ', $missing ) )
					)
				);
			}
		);
		return;
	}

	/**
	 * Fires once the plugin's dependencies are confirmed; later features hook in here.
	 */
	do_action( 'nera_prize_risk_loaded' );
}
add_action( 'plugins_loaded', 'nera_prize_risk_boot', 20 );

/**
 * Admin: Prize risk tab on the product edit screen.
 */
add_action(
	'nera_prize_risk_loaded',
	static function () {
		if ( ! is_admin() ) {
			return;
		}
		require_once NERA_PRIZE_RISK_PLUGIN_DIR . 'inc/class-nera-prize-risk-product.php';
		Nera_Prize_Risk_Product::init();
	}
);

/**
 * Report data and its cache invalidation (every request: orders change at checkout, by cron and over REST).
 */
add_action(
	'nera_prize_risk_loaded',
	static function () {
		require_once NERA_PRIZE_RISK_PLUGIN_DIR . 'inc/class-nera-prize-risk-data.php';
		Nera_Prize_Risk_Data::init();
	}
);

/**
 * Admin: Prize Risk menu (report screen with calculator and competitions table, settings).
 */
add_action(
	'nera_prize_risk_loaded',
	static function () {
		if ( ! is_admin() ) {
			return;
		}
		require_once NERA_PRIZE_RISK_PLUGIN_DIR . 'inc/class-nera-prize-risk-admin.php';
		require_once NERA_PRIZE_RISK_PLUGIN_DIR . 'inc/class-nera-prize-risk-table.php';
		Nera_Prize_Risk_Admin::init();
	}
);
