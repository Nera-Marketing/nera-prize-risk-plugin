<?php
/**
 * Prize Risk admin menu: report screen (calculator in a modal), Calculator page and the Payment fee % setting.
 *
 * Admin only: loaded on `nera_prize_risk_loaded` when is_admin(). Nothing hooks the front end.
 *
 * @package nera-prize-risk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin menu, settings and calculator.
 */
class Nera_Prize_Risk_Admin {

	const CAPABILITY     = 'manage_woocommerce';
	const PAGE_SLUG      = 'nera-prize-risk';
	const CALC_SLUG      = 'nera-prize-risk-calculator';
	const SETTINGS_SLUG  = 'nera-prize-risk-settings';
	const SETTINGS_GROUP = 'nera_prize_risk_settings';
	const OPTION_FEE_PCT = 'nera_prize_risk_fee_pct';

	/**
	 * Report page hook suffix.
	 *
	 * @var string
	 */
	private static $report_hook = '';

	/**
	 * Calculator page hook suffix.
	 *
	 * @var string
	 */
	private static $calc_hook = '';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_filter( 'option_page_capability_' . self::SETTINGS_GROUP, array( __CLASS__, 'settings_capability' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Payment fee as a stored percentage (1.4 = 1.4%).
	 *
	 * @return float
	 */
	public static function fee_pct() {
		$pct = get_option( self::OPTION_FEE_PCT, 0 );
		return is_numeric( $pct ) ? max( 0, min( 100, (float) $pct ) ) : 0.0;
	}

	/**
	 * Menu and submenu.
	 *
	 * @return void
	 */
	public static function add_menu() {
		self::$report_hook = add_menu_page(
			__( 'Prize Risk', 'nera-prize-risk' ),
			__( 'Prize Risk', 'nera-prize-risk' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( __CLASS__, 'render_report' ),
			'dashicons-shield',
			'56.1'
		);

		self::$calc_hook = (string) add_submenu_page(
			self::PAGE_SLUG,
			__( 'Calculator', 'nera-prize-risk' ),
			__( 'Calculator', 'nera-prize-risk' ),
			self::CAPABILITY,
			self::CALC_SLUG,
			array( __CLASS__, 'render_calculator' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Prize Risk settings', 'nera-prize-risk' ),
			__( 'Settings', 'nera-prize-risk' ),
			self::CAPABILITY,
			self::SETTINGS_SLUG,
			array( __CLASS__, 'render_settings' )
		);
	}

	/**
	 * Shop Managers may save the settings (options.php defaults to manage_options).
	 *
	 * @return string
	 */
	public static function settings_capability() {
		return self::CAPABILITY;
	}

	/**
	 * Settings API registration.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_FEE_PCT,
			array(
				'type'              => 'number',
				'default'           => 0,
				'sanitize_callback' => array( __CLASS__, 'sanitize_fee_pct' ),
			)
		);
	}

	/**
	 * Accept a number from 0 to 100; anything else keeps the stored value and adds an error notice.
	 *
	 * @param mixed $value Submitted value.
	 * @return mixed
	 */
	public static function sanitize_fee_pct( $value ) {
		static $notice_added = false;

		$raw        = is_scalar( $value ) ? trim( (string) $value ) : '';
		$normalised = str_replace( ',', '.', $raw );

		if ( '' !== $normalised && is_numeric( $normalised ) && (float) $normalised >= 0 && (float) $normalised <= 100 ) {
			return (string) ( 0 + $normalised );
		}

		if ( ! $notice_added ) {
			$notice_added = true;
			add_settings_error(
				self::OPTION_FEE_PCT,
				'nera_prize_risk_fee_pct_invalid',
				__( 'Payment fee % must be a number from 0 to 100. The previous value was kept.', 'nera-prize-risk' ),
				'error'
			);
		}

		return get_option( self::OPTION_FEE_PCT, 0 );
	}

	/**
	 * Report screen.
	 *
	 * @return void
	 */
	public static function render_report() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$fee_pct      = self::fee_pct();
		$settings_url = admin_url( 'admin.php?page=' . self::SETTINGS_SLUG );
		$all_rows     = Nera_Prize_Risk_Data::get_rows();
		$filters      = Nera_Prize_Risk_Export::current_filters();
		$options      = Nera_Prize_Risk_Export::filter_options( $all_rows );
		$rows         = Nera_Prize_Risk_Export::filter_rows( $all_rows, $filters );
		$export_url   = Nera_Prize_Risk_Export::export_url( $filters );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view toggle.
		$view         = ( isset( $_GET['view'] ) && 'items' === $_GET['view'] ) ? 'items' : 'list';
		$view_urls    = array(
			'list'  => add_query_arg( array_filter( array_merge( array( 'page' => self::PAGE_SLUG ), $filters ) ), admin_url( 'admin.php' ) ),
			'items' => add_query_arg( array_filter( array_merge( array( 'page' => self::PAGE_SLUG ), $filters, array( 'view' => 'items' ) ) ), admin_url( 'admin.php' ) ),
		);
		include NERA_PRIZE_RISK_PLUGIN_DIR . 'templates/admin-report.php';
	}

	/**
	 * Calculator page: the same form partial and JS as the report-screen modal, inline.
	 *
	 * @return void
	 */
	public static function render_calculator() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$fee_pct      = self::fee_pct();
		$settings_url = admin_url( 'admin.php?page=' . self::SETTINGS_SLUG );
		include NERA_PRIZE_RISK_PLUGIN_DIR . 'templates/admin-calculator.php';
	}

	/**
	 * Settings screen.
	 *
	 * @return void
	 */
	public static function render_settings() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		?>
		<div class="wrap nera-prize-risk nera-prize-risk-settings">
			<h1><?php esc_html_e( 'Prize Risk settings', 'nera-prize-risk' ); ?></h1>
			<?php settings_errors(); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( self::OPTION_FEE_PCT ); ?>"><?php esc_html_e( 'Payment fee %', 'nera-prize-risk' ); ?></label></th>
						<td>
							<input type="text" inputmode="decimal" class="small-text" id="<?php echo esc_attr( self::OPTION_FEE_PCT ); ?>" name="<?php echo esc_attr( self::OPTION_FEE_PCT ); ?>" value="<?php echo esc_attr( (string) get_option( self::OPTION_FEE_PCT, 0 ) ); ?>" /> %
							<p class="description"><?php esc_html_e( 'Transaction fee taken from gross ticket revenue (e.g. 1.4 for Cashflows at 1.4%). A number from 0 to 100.', 'nera-prize-risk' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Calculator scripts and table styles on the report screen and the Calculator page only.
	 *
	 * @param string $hook Admin page hook.
	 * @return void
	 */
	public static function enqueue( $hook ) {
		if ( ! in_array( $hook, array_filter( array( self::$report_hook, self::$calc_hook ) ), true ) ) {
			return;
		}

		wp_enqueue_style( 'nera-prize-risk-admin', NERA_PRIZE_RISK_PLUGIN_URL . 'assets/css/admin.css', array(), NERA_PRIZE_RISK_VERSION );
		wp_register_script( 'nera-prize-risk-calc', NERA_PRIZE_RISK_PLUGIN_URL . 'assets/js/calc.js', array(), NERA_PRIZE_RISK_VERSION, true );
		wp_enqueue_script( 'nera-prize-risk-calculator', NERA_PRIZE_RISK_PLUGIN_URL . 'assets/js/calculator.js', array( 'nera-prize-risk-calc' ), NERA_PRIZE_RISK_VERSION, true );
		if ( $hook === self::$report_hook ) {
			wp_enqueue_script( 'nera-prize-risk-sort', NERA_PRIZE_RISK_PLUGIN_URL . 'assets/js/sort.js', array(), NERA_PRIZE_RISK_VERSION, true );
		}
		wp_localize_script(
			'nera-prize-risk-calculator',
			'neraPrizeRiskCalculator',
			array(
				'feeFraction'    => self::fee_pct() / 100,
				'currencySymbol' => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
				'thousandSep'    => wc_get_price_thousand_separator(),
				'decimalPoint'   => wc_get_price_decimal_separator(),
				'i18n'           => array(
					'empty'   => '—',
					/* translators: 1: tickets, 2: percent of total tickets */
					'tickets' => __( '%1$s tickets (%2$s%%)', 'nera-prize-risk' ),
					/* translators: 1: break-even tickets, 2: total tickets */
					'warning' => __( 'Can\'t break even: needs %1$s tickets, only %2$s exist.', 'nera-prize-risk' ),
					'profit'  => __( 'profit', 'nera-prize-risk' ),
					'loss'    => __( 'loss', 'nera-prize-risk' ),
				),
			)
		);
	}
}
