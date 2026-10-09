<?php
/**
 * Report filters (month, category, status) and the CSV export of the filtered rows.
 *
 * @package nera-prize-risk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Filters the report rows and streams them as CSV.
 */
class Nera_Prize_Risk_Export {

	const ACTION = 'nera_prize_risk_export';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_export' ) );
	}

	/**
	 * Status filter keys → LTY statuses (D-2).
	 *
	 * @return array<string,string>
	 */
	public static function status_keys() {
		return array(
			'live'      => 'lty_lottery_started',
			'scheduled' => 'lty_lottery_not_started',
			'ended'     => 'lty_lottery_closed',
			'drawn'     => 'lty_lottery_finished',
			'failed'    => 'lty_lottery_failed',
		);
	}

	/**
	 * Current filters from the query string, validated (invalid values are ignored).
	 *
	 * @return array{month:string,cat:int,status:string}
	 */
	public static function current_filters() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only GET filters.
		$month  = isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : '';
		$cat    = isset( $_GET['cat'] ) ? absint( $_GET['cat'] ) : 0;
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		// phpcs:enable

		return array(
			'month'  => preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $month ) ? $month : '',
			'cat'    => $cat,
			'status' => isset( self::status_keys()[ $status ] ) ? $status : '',
		);
	}

	/**
	 * Close month (YYYY-MM) of a row, or '' without an end date.
	 *
	 * @param array $row Report row.
	 * @return string
	 */
	public static function row_month( $row ) {
		$ts = $row['end_date'] ? strtotime( $row['end_date'] ) : false;
		return $ts ? gmdate( 'Y-m', $ts ) : '';
	}

	/**
	 * Rows matching every set filter.
	 *
	 * @param array[] $rows    Rows from Nera_Prize_Risk_Data::get_rows().
	 * @param array   $filters From current_filters().
	 * @return array[]
	 */
	public static function filter_rows( $rows, $filters ) {
		$statuses = self::status_keys();
		return array_values(
			array_filter(
				$rows,
				static function ( $row ) use ( $filters, $statuses ) {
					if ( '' !== $filters['month'] && self::row_month( $row ) !== $filters['month'] ) {
						return false;
					}
					if ( $filters['cat'] && (int) $row['category_id'] !== $filters['cat'] ) {
						return false;
					}
					if ( '' !== $filters['status'] && $row['status'] !== $statuses[ $filters['status'] ] ) {
						return false;
					}
					return true;
				}
			)
		);
	}

	/**
	 * Select options for the filter bar: months and categories present in the rows, all five statuses.
	 *
	 * @param array[] $rows All (unfiltered) rows.
	 * @return array{month:array<string,string>,cat:array<int,string>,status:array<string,string>}
	 */
	public static function filter_options( $rows ) {
		$months = array();
		$cats   = array();
		foreach ( $rows as $row ) {
			$month = self::row_month( $row );
			if ( '' !== $month ) {
				$months[ $month ] = date_i18n( 'F Y', strtotime( $month . '-01 00:00:00' ) );
			}
			if ( $row['category_id'] ) {
				$cats[ (int) $row['category_id'] ] = $row['category'];
			}
		}
		ksort( $months );
		natcasesort( $cats );

		$status = array();
		foreach ( self::status_keys() as $key => $lty_status ) {
			$status[ $key ] = Nera_Prize_Risk_Data::status_label( $lty_status );
		}

		return array(
			'month'  => $months,
			'cat'    => $cats,
			'status' => $status,
		);
	}

	/**
	 * Export link for the given filters (nonced).
	 *
	 * @param array $filters From current_filters().
	 * @return string
	 */
	public static function export_url( $filters ) {
		$args = array_filter( array_merge( array( 'action' => self::ACTION ), $filters ) );
		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), self::ACTION );
	}

	/**
	 * Neutralise spreadsheet formulas in a text cell: prefix =, +, -, @ (and tab/CR) with a quote.
	 *
	 * @param string $value Cell text.
	 * @return string
	 */
	public static function text_cell( $value ) {
		$value = (string) $value;
		if ( '' !== $value && false !== strpos( "=+-@\t\r", $value[0] ) ) {
			return "'" . $value;
		}
		return $value;
	}

	/**
	 * Raw number with a dot decimal and no thousand separator; '' for null.
	 *
	 * @param float|int|null $value    Number.
	 * @param int            $decimals Decimals.
	 * @return string
	 */
	public static function number_cell( $value, $decimals = 2 ) {
		if ( null === $value ) {
			return '';
		}
		$out = number_format( (float) $value, $decimals, '.', '' );
		return '-0' === rtrim( rtrim( $out, '0' ), '.' ) ? number_format( 0, $decimals, '.', '' ) : $out;
	}

	/**
	 * CSV header row.
	 *
	 * @return string[]
	 */
	public static function csv_header() {
		return array(
			'ID',
			'Competition',
			'Category',
			'Status',
			'Closes',
			'Prize cost',
			'Ticket price',
			'Total tickets',
			'Paid tickets',
			'Free entries',
			'Sell-through %',
			'Revenue (net)',
			'Break-even tickets',
			'Break-even %',
			'Position',
			'Risk',
		);
	}

	/**
	 * One CSV row.
	 *
	 * @param array $row Report row.
	 * @return array
	 */
	public static function csv_row( $row ) {
		return array(
			(int) $row['id'],
			self::text_cell( $row['title'] ),
			self::text_cell( $row['category'] ),
			self::text_cell( Nera_Prize_Risk_Data::status_label( $row['status'] ) ),
			self::text_cell( $row['end_date'] ),
			self::number_cell( $row['total_cost'] ),
			self::number_cell( $row['ticket_price'] ),
			(int) $row['max'],
			(int) $row['paid'],
			(int) $row['free'],
			self::number_cell( null === $row['sell_through'] ? null : $row['sell_through'] * 100 ),
			self::number_cell( $row['revenue_net'] ),
			null === $row['break_even_tix'] ? '' : (int) $row['break_even_tix'],
			self::number_cell( null === $row['break_even_pct'] ? null : $row['break_even_pct'] * 100 ),
			self::number_cell( $row['position'] ),
			self::text_cell( Nera_Prize_Risk_Table::risk_label( $row['risk'] ) ),
		);
	}

	/**
	 * admin-post handler: capability + nonce, then stream the filtered rows.
	 *
	 * @return void
	 */
	public static function handle_export() {
		if ( ! current_user_can( Nera_Prize_Risk_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to export this report.', 'nera-prize-risk' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION );

		$filters = self::current_filters();
		$rows    = self::filter_rows( Nera_Prize_Risk_Data::get_rows(), $filters );

		$parts    = array_filter( array( 'prize-risk', $filters['month'], $filters['cat'] ? 'cat-' . $filters['cat'] : '', $filters['status'], wp_date( 'Y-m-d' ) ) );
		$filename = sanitize_file_name( implode( '-', $parts ) . '.csv' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFilesystem.PHPNativeFunctions_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFilesystem.PHPNativeFunctions_fwrite -- UTF-8 BOM so Excel reads names correctly.
		fputcsv( $out, self::csv_header(), ',', '"', '' );
		foreach ( $rows as $row ) {
			fputcsv( $out, self::csv_row( $row ), ',', '"', '' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFilesystem.PHPNativeFunctions_fclose
		exit;
	}
}
