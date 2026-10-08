<?php
/**
 * Prize Risk screen: calculator box on top, competitions table below it.
 *
 * Variables from Nera_Prize_Risk_Admin::render_report(): $fee_pct (float), $settings_url (string), $all_rows (array[]),
 * $rows (array[], filtered), $filters (array), $options (array), $export_url (string), $view (list|items),
 * $view_urls (array{list:string,items:string}, current filters kept).
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

$nera_prize_risk_filter_labels = array(
	'month'  => array( __( 'Month', 'nera-prize-risk' ), __( 'All months', 'nera-prize-risk' ) ),
	'cat'    => array( __( 'Category', 'nera-prize-risk' ), __( 'All categories', 'nera-prize-risk' ) ),
	'status' => array( __( 'Status', 'nera-prize-risk' ), __( 'All statuses', 'nera-prize-risk' ) ),
);

$nera_prize_risk_outputs = array(
	'break_even' => __( 'Break-even', 'nera-prize-risk' ),
	'expected'   => __( 'Result at expected sell-through', 'nera-prize-risk' ),
	'sold_out'   => __( 'Result if sold out', 'nera-prize-risk' ),
	'nothing'    => __( 'Loss if nothing sells', 'nera-prize-risk' ),
);
?>
<div class="wrap nera-prize-risk">
	<h1><?php esc_html_e( 'Prize Risk', 'nera-prize-risk' ); ?></h1>

	<div class="card nera-prize-risk-calc" id="nera-prize-risk-calc">
		<h2 class="title"><?php esc_html_e( 'Calculator', 'nera-prize-risk' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Try out a competition before you set it up. Nothing here is saved.', 'nera-prize-risk' ); ?></p>

		<table class="form-table" role="presentation">
			<?php foreach ( $nera_prize_risk_inputs as $nera_prize_risk_key => $nera_prize_risk_label ) : ?>
				<tr>
					<th scope="row"><label for="nera-prize-risk-calc-<?php echo esc_attr( $nera_prize_risk_key ); ?>"><?php echo esc_html( $nera_prize_risk_label ); ?></label></th>
					<td><input type="number" min="0" step="any" inputmode="decimal" class="regular-text" id="nera-prize-risk-calc-<?php echo esc_attr( $nera_prize_risk_key ); ?>" data-field="<?php echo esc_attr( $nera_prize_risk_key ); ?>" autocomplete="off" /></td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Payment fee', 'nera-prize-risk' ); ?></th>
				<td>
					<strong id="nera-prize-risk-calc-fee"><?php echo esc_html( wc_format_localized_decimal( (string) ( 0 + $fee_pct ) ) . '%' ); ?></strong>
					<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Change in Settings', 'nera-prize-risk' ); ?></a>
				</td>
			</tr>
		</table>

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

	<h2 class="nera-prize-risk-table-title"><?php esc_html_e( 'Competitions', 'nera-prize-risk' ); ?></h2>

	<ul class="subsubsub nera-prize-risk-view" id="nera-prize-risk-view">
		<li><a id="nera-prize-risk-view-list" href="<?php echo esc_url( $view_urls['list'] ); ?>"<?php echo 'list' === $view ? ' class="current" aria-current="page"' : ''; ?>><?php esc_html_e( 'Competitions', 'nera-prize-risk' ); ?></a> |</li>
		<li><a id="nera-prize-risk-view-items" href="<?php echo esc_url( $view_urls['items'] ); ?>"<?php echo 'items' === $view ? ' class="current" aria-current="page"' : ''; ?>><?php esc_html_e( 'Items', 'nera-prize-risk' ); ?></a></li>
	</ul>

	<form method="get" class="nera-prize-risk-filters" id="nera-prize-risk-filters">
		<input type="hidden" name="page" value="<?php echo esc_attr( Nera_Prize_Risk_Admin::PAGE_SLUG ); ?>" />
		<?php if ( 'items' === $view ) : ?>
			<input type="hidden" name="view" value="items" />
		<?php endif; ?>
		<?php foreach ( $nera_prize_risk_filter_labels as $nera_prize_risk_key => $nera_prize_risk_label ) : ?>
			<label class="screen-reader-text" for="nera-prize-risk-filter-<?php echo esc_attr( $nera_prize_risk_key ); ?>"><?php echo esc_html( $nera_prize_risk_label[0] ); ?></label>
			<select name="<?php echo esc_attr( $nera_prize_risk_key ); ?>" id="nera-prize-risk-filter-<?php echo esc_attr( $nera_prize_risk_key ); ?>">
				<option value=""><?php echo esc_html( $nera_prize_risk_label[1] ); ?></option>
				<?php foreach ( $options[ $nera_prize_risk_key ] as $nera_prize_risk_value => $nera_prize_risk_text ) : ?>
					<option value="<?php echo esc_attr( $nera_prize_risk_value ); ?>" <?php selected( (string) $filters[ $nera_prize_risk_key ], (string) $nera_prize_risk_value ); ?>><?php echo esc_html( $nera_prize_risk_text ); ?></option>
				<?php endforeach; ?>
			</select>
		<?php endforeach; ?>
		<?php submit_button( __( 'Filter', 'nera-prize-risk' ), '', '', false, array( 'id' => 'nera-prize-risk-filter-submit' ) ); ?>
		<?php if ( array_filter( $filters ) ) : ?>
			<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=' . Nera_Prize_Risk_Admin::PAGE_SLUG . ( 'items' === $view ? '&view=items' : '' ) ) ); ?>"><?php esc_html_e( 'Clear filters', 'nera-prize-risk' ); ?></a>
		<?php endif; ?>
		<a class="button nera-prize-risk-export" id="nera-prize-risk-export" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'nera-prize-risk' ); ?></a>
	</form>

	<?php
	if ( 'items' === $view ) {
		Nera_Prize_Risk_Table::render_rollup( Nera_Prize_Risk_Data::rollup( $rows, 'title' ), 'title', __( 'Item', 'nera-prize-risk' ) );
	} else {
		Nera_Prize_Risk_Table::render(
			$rows,
			( $all_rows && ! $rows ) ? __( 'No competitions match these filters.', 'nera-prize-risk' ) : ''
		);
	}
	?>

	<h2 class="nera-prize-risk-rollup-title"><?php esc_html_e( 'By category', 'nera-prize-risk' ); ?></h2>
	<?php Nera_Prize_Risk_Table::render_rollup( Nera_Prize_Risk_Data::rollup( $rows, 'category' ), 'category', __( 'Category', 'nera-prize-risk' ) ); ?>

	<h2 class="nera-prize-risk-rollup-title"><?php esc_html_e( 'By month', 'nera-prize-risk' ); ?></h2>
	<?php Nera_Prize_Risk_Table::render_rollup( Nera_Prize_Risk_Data::rollup( $rows, 'month' ), 'month', __( 'Month (close date)', 'nera-prize-risk' ) ); ?>
</div>
