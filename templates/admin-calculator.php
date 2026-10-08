<?php
/**
 * Calculator page: the calculator inline, from the same partial the Prize Risk modal uses (parity).
 *
 * Variables from Nera_Prize_Risk_Admin::render_calculator(): $fee_pct (float), $settings_url (string).
 *
 * @package nera-prize-risk
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap nera-prize-risk">
	<h1><?php esc_html_e( 'Calculator', 'nera-prize-risk' ); ?></h1>

	<div class="card nera-prize-risk-calc-card">
		<?php include NERA_PRIZE_RISK_PLUGIN_DIR . 'templates/calculator-form.php'; ?>
	</div>
</div>
