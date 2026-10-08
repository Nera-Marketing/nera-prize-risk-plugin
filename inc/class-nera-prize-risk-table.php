<?php
/**
 * Prize Risk competitions table (plain widefat table, rows from Nera_Prize_Risk_Data::get_rows()).
 *
 * @package nera-prize-risk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the report table.
 */
class Nera_Prize_Risk_Table {

	/**
	 * Column keys and headings, in brief order (task.md §4a).
	 *
	 * @return array<string,string>
	 */
	public static function columns() {
		return array(
			'competition'  => __( 'Competition', 'nera-prize-risk' ),
			'category'     => __( 'Category', 'nera-prize-risk' ),
			'status'       => __( 'Status', 'nera-prize-risk' ),
			'closes'       => __( 'Closes', 'nera-prize-risk' ),
			'prize_cost'   => __( 'Prize cost', 'nera-prize-risk' ),
			'ticket_price' => __( 'Ticket price', 'nera-prize-risk' ),
			'sold'         => __( 'Sold / total', 'nera-prize-risk' ),
			'free'         => __( 'Free entries', 'nera-prize-risk' ),
			'revenue'      => __( 'Revenue (net)', 'nera-prize-risk' ),
			'break_even'   => __( 'Break-even', 'nera-prize-risk' ),
			'position'     => __( 'Position', 'nera-prize-risk' ),
			'risk'         => __( 'Risk', 'nera-prize-risk' ),
		);
	}

	/**
	 * Money: currency symbol + amount. Call sites pass 0 dp for costs, revenue, positions and margins; ticket prices keep 2 dp.
	 *
	 * @param float $amount   Amount.
	 * @param int   $decimals Decimals.
	 * @return string Plain text.
	 */
	public static function money( $amount, $decimals = 2 ) {
		$symbol = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );
		$value  = number_format( abs( (float) $amount ), $decimals, wc_get_price_decimal_separator(), wc_get_price_thousand_separator() );
		return ( round( (float) $amount, $decimals ) < 0 ? '-' : '' ) . $symbol . $value;
	}

	/**
	 * Percentage from a fraction: 1 dp, or 2 dp below 1% (0.18%).
	 *
	 * @param float|null $fraction Fraction.
	 * @return string
	 */
	public static function pct( $fraction ) {
		if ( null === $fraction ) {
			return '—';
		}
		$pct = $fraction * 100;
		return number_format( $pct, ( $pct > 0 && $pct < 1 ) ? 2 : 1, wc_get_price_decimal_separator(), wc_get_price_thousand_separator() ) . '%';
	}

	/**
	 * Position text: "Covered, £X profit" or "Exposed by £X" (whole pounds).
	 *
	 * @param float $position Revenue net minus total cost.
	 * @return string
	 */
	public static function position_text( $position ) {
		if ( $position >= 0 ) {
			/* translators: %s: profit with currency */
			return sprintf( __( 'Covered, %s profit', 'nera-prize-risk' ), self::money( $position, 0 ) );
		}
		/* translators: %s: shortfall with currency */
		return sprintf( __( 'Exposed by %s', 'nera-prize-risk' ), self::money( abs( $position ), 0 ) );
	}

	/**
	 * Risk pill label.
	 *
	 * @param string $risk sold_out|covered|exposed.
	 * @return string
	 */
	public static function risk_label( $risk ) {
		$labels = array(
			'sold_out' => __( 'Sold out', 'nera-prize-risk' ),
			'covered'  => __( 'Covered', 'nera-prize-risk' ),
			'exposed'  => __( 'Exposed', 'nera-prize-risk' ),
		);
		return isset( $labels[ $risk ] ) ? $labels[ $risk ] : $risk;
	}

	/**
	 * End date in the site's date and/or time format.
	 *
	 * @param string $date LTY end date (site local time, Y-m-d H:i:s).
	 * @param string $part both|date|time.
	 * @return string Empty for the time part when there is no date.
	 */
	public static function closes( $date, $part = 'both' ) {
		$ts = $date ? strtotime( $date ) : false;
		if ( ! $ts ) {
			return 'time' === $part ? '' : '—';
		}
		$formats = array(
			'date' => get_option( 'date_format' ),
			'time' => get_option( 'time_format' ),
			'both' => get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
		);
		return date_i18n( isset( $formats[ $part ] ) ? $formats[ $part ] : $formats['both'], $ts );
	}

	/**
	 * Output the table.
	 *
	 * @param array[] $rows          Rows from Nera_Prize_Risk_Data::get_rows() (filtered).
	 * @param string  $empty_message Message when there are no rows (default: nothing costed yet).
	 * @return void
	 */
	/**
	 * Calculator values for a row's "Model this" action: costs kept separate, sell-through as a percent to 1 dp.
	 *
	 * @param array $row Row from Nera_Prize_Risk_Data::build_row().
	 * @return array
	 */
	public static function model_values( $row ) {
		// Whole numbers as ints so the inputs show "600", not "600.0".
		$n = static function ( $v ) {
			$v = (float) $v;
			return floor( $v ) === $v ? (int) $v : $v;
		};
		return array(
			'prize_cost'    => $n( $row['prize_cost'] ),
			'other_costs'   => $n( $row['other_costs'] ),
			'ticket_price'  => $n( $row['ticket_price'] ),
			'total_tickets' => (int) $row['max'],
			'sell_through'  => null === $row['sell_through'] ? null : $n( round( $row['sell_through'] * 100, 1 ) ),
		);
	}

	public static function render( $rows, $empty_message = '' ) {
		$columns = self::columns();
		echo '<div class="nera-prize-risk-table-wrap"><table class="widefat striped nera-prize-risk-table" id="nera-prize-risk-table"><thead><tr>';
		foreach ( $columns as $key => $label ) {
			self::sort_th( $key, $label );
		}
		echo '</tr></thead><tbody>';

		if ( empty( $rows ) ) {
			printf(
				'<tr class="no-items"><td colspan="%d">%s</td></tr>',
				count( $columns ),
				esc_html( '' !== $empty_message ? $empty_message : __( 'No competitions have a prize cost yet. Add one in the product\'s Prize risk tab.', 'nera-prize-risk' ) )
			);
		}

		foreach ( $rows as $row ) {
			$sub = '<br><span class="description nera-prize-risk-sub">%s</span>';
			printf( '<tr data-product-id="%d">', (int) $row['id'] );
			printf(
				'<td class="column-competition" data-sort="%s"><a href="%s">%s</a><div class="row-actions visible"><button type="button" class="button-link nera-prize-risk-model" data-calc="%s">%s</button></div></td>',
				esc_attr( $row['title'] ),
				esc_url( admin_url( 'post.php?post=' . (int) $row['id'] . '&action=edit' ) ),
				esc_html( $row['title'] ),
				esc_attr( wp_json_encode( self::model_values( $row ) ) ),
				esc_html__( 'Model this', 'nera-prize-risk' )
			);
			printf( '<td class="column-category">%s</td>', esc_html( $row['category'] ) );
			printf(
				'<td class="column-status"><span class="nera-prize-risk-status is-%s">%s</span></td>',
				esc_attr( self::status_slug( $row['status'] ) ),
				esc_html( Nera_Prize_Risk_Data::status_label( $row['status'] ) )
			);
			$closes_time = self::closes( $row['end_date'], 'time' );
			$closes_ts   = $row['end_date'] ? strtotime( $row['end_date'] ) : false;
			printf(
				'<td class="column-closes" data-value="%s">%s%s</td>',
				esc_attr( $closes_ts ? (string) $closes_ts : '' ),
				esc_html( self::closes( $row['end_date'], 'date' ) ),
				'' === $closes_time ? '' : sprintf( $sub, esc_html( $closes_time ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup, escaped value.
			);
			printf( '<td class="column-prize_cost" data-value="%s">%s</td>', esc_attr( self::raw( $row['total_cost'] ) ), esc_html( self::money( $row['total_cost'], 0 ) ) );
			printf( '<td class="column-ticket_price" data-value="%s">%s</td>', esc_attr( self::raw( $row['ticket_price'] ) ), esc_html( self::money( $row['ticket_price'] ) ) );
			printf(
				'<td class="column-sold" data-value="%s">%s' . $sub . '</td>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
				esc_attr( null === $row['sell_through'] ? '' : self::raw( $row['sell_through'], 6 ) ),
				esc_html( number_format_i18n( $row['paid'] ) . ' / ' . number_format_i18n( $row['max'] ) ),
				esc_html( self::pct( $row['sell_through'] ) )
			);
			printf( '<td class="column-free" data-value="%d">%s</td>', (int) $row['free'], esc_html( number_format_i18n( $row['free'] ) ) );
			printf( '<td class="column-revenue" data-value="%s">%s</td>', esc_attr( self::raw( $row['revenue_net'] ) ), esc_html( self::money( $row['revenue_net'], 0 ) ) );
			if ( null === $row['break_even_tix'] ) {
				echo '<td class="column-break_even" data-value="">—</td>';
			} elseif ( ! Nera_Prize_Risk_Calc::can_break_even( $row['break_even_tix'], $row['max'] ) ) {
				printf(
					'<td class="column-break_even" data-value="%s"><span class="nera-prize-risk-warning">%s</span>' . $sub . '</td>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
					esc_attr( self::raw( $row['break_even_tix'], 0 ) ),
					esc_html__( 'Can\'t break even', 'nera-prize-risk' ),
					esc_html(
						sprintf(
							/* translators: 1: break-even tickets, 2: total tickets */
							__( 'needs %1$s of %2$s', 'nera-prize-risk' ),
							number_format_i18n( $row['break_even_tix'] ),
							number_format_i18n( $row['max'] )
						)
					)
				);
			} else {
				printf(
					'<td class="column-break_even" data-value="%s">%s' . $sub . '</td>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
					esc_attr( self::raw( $row['break_even_tix'], 0 ) ),
					esc_html( number_format_i18n( $row['break_even_tix'] ) ),
					esc_html( self::pct( $row['break_even_pct'] ) )
				);
			}
			printf( '<td class="column-position" data-value="%s">%s</td>', esc_attr( self::raw( $row['position'] ) ), esc_html( self::position_text( $row['position'] ) ) );
			printf(
				'<td class="column-risk"><span class="nera-prize-risk-pill is-%s">%s</span></td>',
				esc_attr( str_replace( '_', '-', $row['risk'] ) ),
				esc_html( self::risk_label( $row['risk'] ) )
			);
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Sortable column header: a button with WP's sorting indicators (assets/js/sort.js sorts client-side).
	 *
	 * @param string $key   Column key (class column-<key>).
	 * @param string $label Heading.
	 * @param string $title Optional title attribute.
	 * @return void
	 */
	private static function sort_th( $key, $label, $title = '' ) {
		printf(
			'<th scope="col" class="column-%s sortable"%s><button type="button" class="nera-prize-risk-sort"><span>%s</span><span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span></button></th>',
			esc_attr( $key ),
			$title ? ' title="' . esc_attr( $title ) . '"' : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			esc_html( $label )
		);
	}

	/**
	 * Status badge slug (live, scheduled, ended, drawn, failed) from an LTY status.
	 *
	 * @param string $status LTY lottery status.
	 * @return string
	 */
	public static function status_slug( $status ) {
		$slug = array_search( $status, Nera_Prize_Risk_Export::status_keys(), true );
		return false === $slug ? sanitize_html_class( (string) $status ) : $slug;
	}

	/**
	 * Margin % text: "—" when revenue is 0 (null) or |margin %| > 999%, where the figure is noise.
	 *
	 * @param float|null $fraction Margin as a fraction of revenue.
	 * @return string
	 */
	public static function margin_pct( $fraction ) {
		return ( null === $fraction || abs( $fraction ) > 9.99 ) ? '—' : self::pct( $fraction );
	}

	/**
	 * Raw number for data-value attributes (dot decimal, no separators).
	 *
	 * @param float $value    Number.
	 * @param int   $decimals Decimals.
	 * @return string
	 */
	public static function raw( $value, $decimals = 2 ) {
		$out = number_format( (float) $value, $decimals, '.', '' );
		return (float) $out === 0.0 ? number_format( 0, $decimals, '.', '' ) : $out;
	}

	/**
	 * Rollup columns (task.md §4b), after the group label column.
	 *
	 * @return array<string,string>
	 */
	public static function rollup_columns() {
		return array(
			'comps'            => __( 'Comps', 'nera-prize-risk' ),
			'prize_cost'       => __( 'Prize cost', 'nera-prize-risk' ),
			'revenue'          => __( 'Revenue (net)', 'nera-prize-risk' ),
			'avg_sell_through' => __( 'Avg sell-through', 'nera-prize-risk' ),
			'margin'           => __( 'Margin', 'nera-prize-risk' ),
			'margin_pct'       => __( 'Margin %', 'nera-prize-risk' ),
		);
	}

	/**
	 * Output one rollup table (by category, by month, or the Items view by title).
	 *
	 * @param array  $rollup From Nera_Prize_Risk_Data::rollup().
	 * @param string $key    category|month|title (table id suffix).
	 * @param string $label  Heading of the group column.
	 * @return void
	 */
	public static function render_rollup( $rollup, $key, $label ) {
		$columns = self::rollup_columns();
		printf(
			'<div class="nera-prize-risk-table-wrap"><table class="widefat striped nera-prize-risk-table nera-prize-risk-rollup" id="nera-prize-risk-rollup-%s"><thead><tr>',
			esc_attr( $key )
		);
		self::sort_th( 'label', $label );
		foreach ( $columns as $col => $heading ) {
			self::sort_th( $col, $heading, 'avg_sell_through' === $col ? __( "Mean of each competition's sell-through", 'nera-prize-risk' ) : '' );
		}
		echo '</tr></thead><tbody>';

		if ( empty( $rollup['groups'] ) ) {
			printf( '<tr class="no-items"><td colspan="%d">%s</td></tr>', count( $columns ) + 1, esc_html__( 'No competitions to sum.', 'nera-prize-risk' ) );
		}
		foreach ( $rollup['groups'] as $group ) {
			printf(
				'<tr data-key="%s"><th scope="row" class="column-label"%s>%s</th>',
				esc_attr( $group['key'] ),
				'month' === $key ? ' data-sort="' . esc_attr( $group['key'] ) . '"' : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				esc_html( $group['label'] )
			);
			self::rollup_cells( $group );
			echo '</tr>';
		}

		printf( '</tbody><tfoot><tr class="nera-prize-risk-total"><th scope="row" class="column-label">%s</th>', esc_html__( 'Total', 'nera-prize-risk' ) );
		self::rollup_cells( $rollup['total'] );
		echo '</tr></tfoot></table></div>';
	}

	/**
	 * Summary strip above the table: comps, total cost, revenue, margin, live exposure.
	 *
	 * @param array $total    Rollup total from Nera_Prize_Risk_Data::rollup().
	 * @param float $exposure From Nera_Prize_Risk_Data::live_exposure().
	 * @return void
	 */
	public static function render_summary( $total, $exposure ) {
		$margin = round( (float) $total['margin'], 2 );
		$tiles  = array(
			'comps'    => array( __( 'Comps', 'nera-prize-risk' ), (string) $total['comps'], number_format_i18n( $total['comps'] ), '' ),
			'cost'     => array( __( 'Total cost', 'nera-prize-risk' ), self::raw( $total['prize_cost'] ), self::money( $total['prize_cost'], 0 ), '' ),
			'revenue'  => array( __( 'Revenue (net)', 'nera-prize-risk' ), self::raw( $total['revenue'] ), self::money( $total['revenue'], 0 ), '' ),
			'margin'   => array( __( 'Margin', 'nera-prize-risk' ), self::raw( $total['margin'] ), self::money( $total['margin'], 0 ), $margin < 0 ? 'is-negative' : ( $margin > 0 ? 'is-positive' : '' ) ),
			'exposure' => array( __( 'Live exposure', 'nera-prize-risk' ), self::raw( $exposure ), self::money( $exposure, 0 ), '' ),
		);
		echo '<div class="nera-prize-risk-summary" id="nera-prize-risk-summary">';
		foreach ( $tiles as $key => $tile ) {
			printf(
				'<div class="nera-prize-risk-summary-tile %s" data-key="%s" data-value="%s"><span class="nera-prize-risk-summary-label">%s</span><span class="nera-prize-risk-summary-value">%s</span></div>',
				esc_attr( $tile[3] ),
				esc_attr( $key ),
				esc_attr( $tile[1] ),
				esc_html( $tile[0] ),
				esc_html( $tile[2] )
			);
		}
		echo '</div>';
	}

	/**
	 * Figure cells of one rollup row.
	 *
	 * @param array $group Rollup group.
	 * @return void
	 */
	private static function rollup_cells( $group ) {
		$cells = array(
			'comps'            => array( (string) $group['comps'], number_format_i18n( $group['comps'] ) ),
			'prize_cost'       => array( self::raw( $group['prize_cost'] ), self::money( $group['prize_cost'], 0 ) ),
			'revenue'          => array( self::raw( $group['revenue'] ), self::money( $group['revenue'], 0 ) ),
			'avg_sell_through' => array( null === $group['avg_sell_through'] ? '' : self::raw( $group['avg_sell_through'], 6 ), self::pct( $group['avg_sell_through'] ) ),
			'margin'           => array( self::raw( $group['margin'] ), self::money( $group['margin'], 0 ) ),
			'margin_pct'       => array( null === $group['margin_pct'] ? '' : self::raw( $group['margin_pct'], 6 ), self::margin_pct( $group['margin_pct'] ) ),
		);
		foreach ( $cells as $col => $cell ) {
			printf( '<td class="column-%s" data-value="%s">%s</td>', esc_attr( $col ), esc_attr( $cell[0] ), esc_html( $cell[1] ) );
		}
	}
}
