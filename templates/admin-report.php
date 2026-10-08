<?php
/**
 * Prize Risk screen: competitions table under the title; the calculator opens in a modal from the filter-bar button.
 *
 * Variables from Nera_Prize_Risk_Admin::render_report(): $fee_pct (float), $settings_url (string), $all_rows (array[]),
 * $rows (array[], filtered), $filters (array), $options (array), $export_url (string), $view (list|items),
 * $view_urls (array{list:string,items:string}, current filters kept).
 *
 * @package nera-prize-risk
 */

defined( 'ABSPATH' ) || exit;

$nera_prize_risk_filter_labels = array(
	'month'  => array( __( 'Month', 'nera-prize-risk' ), __( 'All months', 'nera-prize-risk' ) ),
	'cat'    => array( __( 'Category', 'nera-prize-risk' ), __( 'All categories', 'nera-prize-risk' ) ),
	'status' => array( __( 'Status', 'nera-prize-risk' ), __( 'All statuses', 'nera-prize-risk' ) ),
);
?>
<div class="wrap nera-prize-risk">
	<h1><?php esc_html_e( 'Prize Risk', 'nera-prize-risk' ); ?></h1>
	<hr class="wp-header-end">

	<dialog id="nera-prize-risk-calc-dialog" class="nera-prize-risk-dialog" aria-labelledby="nera-prize-risk-calc-title">
		<div class="nera-prize-risk-dialog-header">
			<h2 id="nera-prize-risk-calc-title"><?php esc_html_e( 'Calculator', 'nera-prize-risk' ); ?></h2>
			<button type="button" class="button-link nera-prize-risk-dialog-close" id="nera-prize-risk-calc-close">
				<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Close calculator', 'nera-prize-risk' ); ?></span>
			</button>
		</div>
		<div class="nera-prize-risk-dialog-body">
			<?php include NERA_PRIZE_RISK_PLUGIN_DIR . 'templates/calculator-form.php'; ?>
		</div>
	</dialog>

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
		<button type="button" class="button nera-prize-risk-calc-open" id="nera-prize-risk-calc-open" aria-haspopup="dialog" aria-controls="nera-prize-risk-calc-dialog"><?php esc_html_e( 'Calculator', 'nera-prize-risk' ); ?></button>
		<a class="button nera-prize-risk-export" id="nera-prize-risk-export" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'nera-prize-risk' ); ?></a>
	</form>

	<?php
	Nera_Prize_Risk_Table::render_summary( Nera_Prize_Risk_Data::rollup( $rows, 'category' )['total'], Nera_Prize_Risk_Data::live_exposure( $rows ) );

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
