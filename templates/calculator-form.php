<?php
/**
 * Calculator form (inputs, payment fee, four outputs). Shared by the Prize Risk modal and the Calculator page,
 * so both stay identical. Driven by assets/js/calculator.js, which finds it by #nera-prize-risk-calc.
 *
 * Variables: $fee_pct (float), $settings_url (string).
 *
 * @package nera-prize-risk
 */

defined( 'ABSPATH' ) || exit;

$nera_prize_risk_inputs = array(
	'prize_cost'    => __( 'Prize cost', 'nera-prize-risk' ),
	'other_costs'   => __( 'Other costs', 'nera-prize-risk' ),
	'ticket_price'  => __( 'Ticket price', 'nera-prize-risk' ),
	'total_tickets' => __( 'Total tickets', 'nera-prize-risk' ),
	'sell_through'  => __( 'Expected sell-through %', 'nera-prize-risk' ),
);

$nera_prize_risk_outputs = array(
	'break_even' => __( 'Break-even', 'nera-prize-risk' ),
	'expected'   => __( 'Result at expected sell-through', 'nera-prize-risk' ),
	'sold_out'   => __( 'Result if sold out', 'nera-prize-risk' ),
	'nothing'    => __( 'Loss if nothing sells', 'nera-prize-risk' ),
);
?>
<div class="nera-prize-risk-calc" id="nera-prize-risk-calc">
	<p class="description"><?php esc_html_e( 'Try out a competition before you set it up. Nothing here is saved.', 'nera-prize-risk' ); ?></p>

	<div class="nera-prize-risk-calc-grid">
		<?php foreach ( $nera_prize_risk_inputs as $nera_prize_risk_key => $nera_prize_risk_label ) : ?>
			<p class="nera-prize-risk-calc-field">
				<label for="nera-prize-risk-calc-<?php echo esc_attr( $nera_prize_risk_key ); ?>"><?php echo esc_html( $nera_prize_risk_label ); ?></label>
				<input type="number" min="0" step="any" inputmode="decimal" id="nera-prize-risk-calc-<?php echo esc_attr( $nera_prize_risk_key ); ?>" data-field="<?php echo esc_attr( $nera_prize_risk_key ); ?>" autocomplete="off" />
			</p>
		<?php endforeach; ?>
	</div>

	<p class="nera-prize-risk-calc-fee">
		<?php esc_html_e( 'Payment fee', 'nera-prize-risk' ); ?>
		<strong id="nera-prize-risk-calc-fee"><?php echo esc_html( wc_format_localized_decimal( (string) ( 0 + $fee_pct ) ) . '%' ); ?></strong>
		<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Change in Settings', 'nera-prize-risk' ); ?></a>
	</p>

	<table class="widefat striped nera-prize-risk-calc-results" aria-live="polite">
		<tbody>
			<?php foreach ( $nera_prize_risk_outputs as $nera_prize_risk_key => $nera_prize_risk_label ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $nera_prize_risk_label ); ?></th>
					<td id="nera-prize-risk-calc-out-<?php echo esc_attr( $nera_prize_risk_key ); ?>">—</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
