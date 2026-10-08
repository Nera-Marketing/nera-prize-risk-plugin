<?php
/**
 * Product edit screen: "Prize risk" tab with Prize cost, Other costs and a live break-even line.
 *
 * @package nera-prize-risk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prize risk product fields (lottery products only).
 */
class Nera_Prize_Risk_Product {

	const META_PRIZE_COST  = '_nera_prize_cost';
	const META_OTHER_COSTS = '_nera_other_costs';
	const OPTION_FEE_PCT   = 'nera_prize_risk_fee_pct';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'render_panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Payment fee as a fraction (option is a percent: 1.4 → 0.014).
	 *
	 * @return float
	 */
	public static function fee_fraction() {
		$pct = get_option( self::OPTION_FEE_PCT, 0 );
		return is_numeric( $pct ) ? max( 0, (float) $pct ) / 100 : 0.0;
	}

	/**
	 * Add the "Prize risk" tab, shown only for lottery products.
	 *
	 * @param array $tabs Product data tabs.
	 * @return array
	 */
	public static function add_tab( $tabs ) {
		$tabs['nera_prize_risk'] = array(
			'label'    => __( 'Prize risk', 'nera-prize-risk' ),
			'target'   => 'nera_prize_risk_tab',
			'class'    => array( 'show_if_lottery' ),
			'priority' => 90,
		);
		return $tabs;
	}

	/**
	 * Ticket price used for the plan line: LTY sale price when set, else regular price.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private static function ticket_price( $product ) {
		$sale = is_callable( array( $product, 'get_lty_sale_price' ) ) ? $product->get_lty_sale_price( 'edit' ) : '';
		if ( is_numeric( $sale ) && (float) $sale > 0 ) {
			return (string) $sale;
		}
		return is_callable( array( $product, 'get_lty_regular_price' ) ) ? (string) $product->get_lty_regular_price( 'edit' ) : (string) $product->get_price( 'edit' );
	}

	/**
	 * Break-even line text (the JS twin in assets/js/product.js builds the same string).
	 *
	 * @param mixed $ticket_price  Ticket price.
	 * @param mixed $total_tickets Total tickets.
	 * @param mixed $prize_cost    Prize cost ('' = 0).
	 * @param mixed $other_costs   Other costs ('' = 0).
	 * @return string
	 */
	public static function break_even_text( $ticket_price, $total_tickets, $prize_cost, $other_costs ) {
		$price = is_numeric( $ticket_price ) ? (float) $ticket_price : 0;
		$total = is_numeric( $total_tickets ) ? (int) $total_tickets : 0;
		if ( $price <= 0 || $total <= 0 ) {
			return __( 'Set ticket price and total tickets to see break-even', 'nera-prize-risk' );
		}

		$net        = Nera_Prize_Risk_Calc::net_price( $price, self::fee_fraction() );
		$total_cost = Nera_Prize_Risk_Calc::total_cost( is_numeric( $prize_cost ) ? $prize_cost : 0, is_numeric( $other_costs ) ? $other_costs : 0 );
		$tix        = Nera_Prize_Risk_Calc::break_even_tix( $total_cost, $net );
		$pct        = Nera_Prize_Risk_Calc::break_even_pct( $tix, $total );
		$profit     = round( $total * $net - $total_cost );
		$symbol     = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );

		if ( ! Nera_Prize_Risk_Calc::can_break_even( $tix, $total ) ) {
			return sprintf(
				/* translators: 1: break-even tickets, 2: total tickets, 3: sold out loss with currency */
				__( 'Can\'t break even: needs %1$s tickets, only %2$s exist. Sold out loss: %3$s.', 'nera-prize-risk' ),
				number_format( $tix ),
				number_format( $total ),
				$symbol . number_format( abs( $profit ) )
			);
		}

		return sprintf(
			/* translators: 1: break-even tickets, 2: percent of total, 3: total tickets, 4: sold out profit with currency */
			__( 'Break-even: %1$s tickets (%2$s%% of %3$s). Sold out profit: %4$s.', 'nera-prize-risk' ),
			number_format( $tix ),
			number_format( $pct * 100, 1 ),
			number_format( $total ),
			( $profit < 0 ? '-' : '' ) . $symbol . number_format( abs( $profit ) )
		);
	}

	/**
	 * Render the tab panel.
	 *
	 * @return void
	 */
	public static function render_panel() {
		global $product_object;

		$product     = $product_object instanceof WC_Product ? $product_object : null;
		$prize_cost  = $product ? $product->get_meta( self::META_PRIZE_COST, true, 'edit' ) : '';
		$other_costs = $product ? $product->get_meta( self::META_OTHER_COSTS, true, 'edit' ) : '';
		$symbol      = get_woocommerce_currency_symbol();
		$total       = ( $product && is_callable( array( $product, 'get_lty_maximum_tickets' ) ) ) ? $product->get_lty_maximum_tickets( 'edit' ) : '';
		$line        = self::break_even_text( $product ? self::ticket_price( $product ) : '', $total, $prize_cost, $other_costs );

		echo '<div id="nera_prize_risk_tab" class="panel woocommerce_options_panel hidden"><div class="options_group">';

		woocommerce_wp_text_input(
			array(
				'id'                => self::META_PRIZE_COST,
				/* translators: %s: currency symbol */
				'label'             => sprintf( __( 'Prize cost (%s)', 'nera-prize-risk' ), $symbol ),
				'value'             => wc_format_localized_price( $prize_cost ),
				'desc_tip'          => true,
				'description'       => __( 'What it costs to buy and deliver the prize. Leave empty to keep this competition out of the prize risk report.', 'nera-prize-risk' ),
				'custom_attributes' => array( 'inputmode' => 'decimal' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => self::META_OTHER_COSTS,
				/* translators: %s: currency symbol */
				'label'             => sprintf( __( 'Other costs (%s)', 'nera-prize-risk' ), $symbol ),
				'value'             => wc_format_localized_price( $other_costs ),
				'placeholder'       => '0',
				'desc_tip'          => true,
				'description'       => __( 'Anything else spent on this competition. Optional; empty means 0.', 'nera-prize-risk' ),
				'custom_attributes' => array( 'inputmode' => 'decimal' ),
			)
		);

		printf(
			'<p class="form-field nera-prize-risk-breakeven"><label>%s</label><span id="nera-prize-risk-breakeven" aria-live="polite">%s</span></p>',
			esc_html__( 'Plan', 'nera-prize-risk' ),
			esc_html( $line )
		);

		echo '</div></div>';
	}

	/**
	 * Validate a posted money field.
	 *
	 * @param string $key Field name.
	 * @return array{0:string,1:bool} [ formatted decimal or '', valid ].
	 */
	private static function posted_decimal( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC verifies woocommerce_meta_nonce before this hook.
		$raw = isset( $_POST[ $key ] ) ? trim( wc_clean( wp_unslash( $_POST[ $key ] ) ) ) : '';
		if ( '' === $raw ) {
			return array( '', true );
		}
		$normalised = str_replace( wc_get_price_decimal_separator(), '.', $raw );
		if ( ! is_numeric( $normalised ) || (float) $normalised < 0 ) {
			return array( '', false );
		}
		return array( wc_format_decimal( $normalised ), true );
	}

	/**
	 * Save the fields. Invalid values (negative, non-numeric) keep the stored value and show an admin notice.
	 *
	 * @param WC_Product $product Product being saved.
	 * @return void
	 */
	public static function save( $product ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC verifies woocommerce_meta_nonce before this hook.
		if ( ! isset( $_POST[ self::META_PRIZE_COST ] ) && ! isset( $_POST[ self::META_OTHER_COSTS ] ) ) {
			return;
		}

		$fields = array(
			self::META_PRIZE_COST  => __( 'Prize cost', 'nera-prize-risk' ),
			self::META_OTHER_COSTS => __( 'Other costs', 'nera-prize-risk' ),
		);

		foreach ( $fields as $key => $label ) {
			list( $value, $valid ) = self::posted_decimal( $key );
			if ( ! $valid ) {
				WC_Admin_Meta_Boxes::add_error(
					sprintf(
						/* translators: %s: field label */
						__( 'Prize risk: %s must be a number of 0 or more. The previous value was kept.', 'nera-prize-risk' ),
						$label
					)
				);
				continue;
			}
			if ( '' === $value ) {
				$product->delete_meta_data( $key );
			} else {
				$product->update_meta_data( $key, $value );
			}
		}
	}

	/**
	 * Enqueue the live line script on product edit screens.
	 *
	 * @param string $hook Admin page hook.
	 * @return void
	 */
	public static function enqueue( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		wp_register_script( 'nera-prize-risk-calc', NERA_PRIZE_RISK_PLUGIN_URL . 'assets/js/calc.js', array(), NERA_PRIZE_RISK_VERSION, true );
		wp_enqueue_script( 'nera-prize-risk-product', NERA_PRIZE_RISK_PLUGIN_URL . 'assets/js/product.js', array( 'jquery', 'nera-prize-risk-calc' ), NERA_PRIZE_RISK_VERSION, true );
		wp_localize_script(
			'nera-prize-risk-product',
			'neraPrizeRiskProduct',
			array(
				'feeFraction'    => self::fee_fraction(),
				'currencySymbol' => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
				'decimalPoint'   => wc_get_price_decimal_separator(),
				'i18n'           => array(
					'missing' => __( 'Set ticket price and total tickets to see break-even', 'nera-prize-risk' ),
					/* translators: 1: break-even tickets, 2: percent of total, 3: total tickets, 4: sold out profit with currency */
					'line'    => __( 'Break-even: %1$s tickets (%2$s%% of %3$s). Sold out profit: %4$s.', 'nera-prize-risk' ),
					/* translators: 1: break-even tickets, 2: total tickets, 3: sold out loss with currency */
					'warning' => __( 'Can\'t break even: needs %1$s tickets, only %2$s exist. Sold out loss: %3$s.', 'nera-prize-risk' ),
				),
			)
		);
	}
}
