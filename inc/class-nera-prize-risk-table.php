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
	 * Money: currency symbol + amount (2 dp for prices and revenue, 0 dp for costs and positions).
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
	 * End date in the site's date and time format.
	 *
	 * @param string $date LTY end date (site local time, Y-m-d H:i:s).
	 * @return string
	 */
	public static function closes( $date ) {
		$ts = $date ? strtotime( $date ) : false;
		if ( ! $ts ) {
			return '—';
		}
		return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts );
	}

	/**
	 * Output the table.
	 *
	 * @param array[] $rows Rows from Nera_Prize_Risk_Data::get_rows().
	 * @return void
	 */
	public static function render( $rows ) {
		$columns = self::columns();
		echo '<div class="nera-prize-risk-table-wrap"><table class="widefat striped nera-prize-risk-table" id="nera-prize-risk-table"><thead><tr>';
		foreach ( $columns as $key => $label ) {
			printf( '<th scope="col" class="column-%s">%s</th>', esc_attr( $key ), esc_html( $label ) );
		}
		echo '</tr></thead><tbody>';

		if ( empty( $rows ) ) {
			printf(
				'<tr class="no-items"><td colspan="%d">%s</td></tr>',
				count( $columns ),
				esc_html__( 'No competitions have a prize cost yet. Add one in the product\'s Prize risk tab.', 'nera-prize-risk' )
			);
		}

		foreach ( $rows as $row ) {
			$sub = '<br><span class="description">%s</span>';
			printf( '<tr data-product-id="%d">', (int) $row['id'] );
			printf(
				'<td class="column-competition"><a href="%s">%s</a></td>',
				esc_url( admin_url( 'post.php?post=' . (int) $row['id'] . '&action=edit' ) ),
				esc_html( $row['title'] )
			);
			printf( '<td class="column-category">%s</td>', esc_html( $row['category'] ) );
			printf( '<td class="column-status">%s</td>', esc_html( Nera_Prize_Risk_Data::status_label( $row['status'] ) ) );
			printf( '<td class="column-closes">%s</td>', esc_html( self::closes( $row['end_date'] ) ) );
			printf( '<td class="column-prize_cost">%s</td>', esc_html( self::money( $row['total_cost'], 0 ) ) );
			printf( '<td class="column-ticket_price">%s</td>', esc_html( self::money( $row['ticket_price'] ) ) );
			printf(
				'<td class="column-sold">%s' . $sub . '</td>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
				esc_html( number_format_i18n( $row['paid'] ) . ' / ' . number_format_i18n( $row['max'] ) ),
				esc_html( self::pct( $row['sell_through'] ) )
			);
			printf( '<td class="column-free">%s</td>', esc_html( number_format_i18n( $row['free'] ) ) );
			printf( '<td class="column-revenue">%s</td>', esc_html( self::money( $row['revenue_net'] ) ) );
			if ( null === $row['break_even_tix'] ) {
				echo '<td class="column-break_even">—</td>';
			} else {
				printf(
					'<td class="column-break_even">%s' . $sub . '</td>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
					esc_html( number_format_i18n( $row['break_even_tix'] ) ),
					esc_html( self::pct( $row['break_even_pct'] ) )
				);
			}
			printf( '<td class="column-position">%s</td>', esc_html( self::position_text( $row['position'] ) ) );
			printf(
				'<td class="column-risk"><span class="nera-prize-risk-pill is-%s">%s</span></td>',
				esc_attr( str_replace( '_', '-', $row['risk'] ) ),
				esc_html( self::risk_label( $row['risk'] ) )
			);
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}
}
