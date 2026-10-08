<?php
/**
 * Prize risk calculation engine.
 *
 * Pure static functions, no WordPress calls and no rounding: round only for display.
 * Never round net_price before using it (4.92 x 15,000 gives 73,800, not 73,802.10).
 * The JS twin is assets/js/calc.js (window.NeraPrizeRiskCalc): same function names, same maths.
 *
 * Fee is a fraction (1.4% = 0.014). Functions that would divide by zero return null.
 *
 * @package NeraPrizeRisk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prize risk formulas (task.md §5).
 */
final class Nera_Prize_Risk_Calc {

	/**
	 * Ticket price after the payment fee: ticket_price x (1 - fee_pct).
	 *
	 * @param float $ticket_price Ticket price.
	 * @param float $fee_pct      Fee as a fraction (0.014).
	 * @return float
	 */
	public static function net_price( $ticket_price, $fee_pct ) {
		return (float) $ticket_price * ( 1 - (float) $fee_pct );
	}

	/**
	 * Prize cost plus other costs.
	 *
	 * @param float $prize_cost  Prize cost.
	 * @param float $other_costs Other costs.
	 * @return float
	 */
	public static function total_cost( $prize_cost, $other_costs ) {
		return (float) $prize_cost + (float) $other_costs;
	}

	/**
	 * Tickets needed to cover total cost: ceil(total_cost / net_price).
	 *
	 * @param float $total_cost Total cost.
	 * @param float $net_price  Net ticket price (unrounded).
	 * @return int|null Null when net_price is not positive.
	 */
	public static function break_even_tix( $total_cost, $net_price ) {
		if ( (float) $net_price <= 0 ) {
			return null;
		}
		return (int) ceil( (float) $total_cost / (float) $net_price );
	}

	/**
	 * Break-even share of all tickets: break_even_tix / total_tickets.
	 *
	 * @param int|null $break_even_tix Break-even tickets.
	 * @param int      $total_tickets  Total tickets.
	 * @return float|null Null when either input is missing or total_tickets is not positive.
	 */
	public static function break_even_pct( $break_even_tix, $total_tickets ) {
		if ( null === $break_even_tix || (int) $total_tickets <= 0 ) {
			return null;
		}
		return (int) $break_even_tix / (int) $total_tickets;
	}

	/**
	 * Net revenue: gross line totals (processing + completed, refunds already subtracted) x (1 - fee_pct).
	 *
	 * @param float $gross_line_totals Sum of line totals minus refunds.
	 * @param float $fee_pct           Fee as a fraction.
	 * @return float
	 */
	public static function revenue_net( $gross_line_totals, $fee_pct ) {
		return (float) $gross_line_totals * ( 1 - (float) $fee_pct );
	}

	/**
	 * Current position: revenue_net - total_cost (negative = exposed).
	 *
	 * @param float $revenue_net Net revenue.
	 * @param float $total_cost  Total cost.
	 * @return float
	 */
	public static function position( $revenue_net, $total_cost ) {
		return (float) $revenue_net - (float) $total_cost;
	}

	/**
	 * Share of tickets gone: (paid_tickets + free_entries) / total_tickets.
	 *
	 * @param int $paid_tickets  Paid tickets.
	 * @param int $free_entries  Free (postal) entries.
	 * @param int $total_tickets Total tickets.
	 * @return float|null Null when total_tickets is not positive.
	 */
	public static function sell_through( $paid_tickets, $free_entries, $total_tickets ) {
		if ( (int) $total_tickets <= 0 ) {
			return null;
		}
		return ( (int) $paid_tickets + (int) $free_entries ) / (int) $total_tickets;
	}

	/**
	 * Most net revenue still to come: (total_tickets - paid_tickets - free_entries) x net_price.
	 *
	 * @param int   $total_tickets Total tickets.
	 * @param int   $paid_tickets  Paid tickets.
	 * @param int   $free_entries  Free entries.
	 * @param float $net_price     Net ticket price (unrounded).
	 * @return float
	 */
	public static function max_remaining( $total_tickets, $paid_tickets, $free_entries, $net_price ) {
		return ( (int) $total_tickets - (int) $paid_tickets - (int) $free_entries ) * (float) $net_price;
	}

	/**
	 * Result if every remaining ticket sells: revenue_net + max_remaining - total_cost.
	 *
	 * @param float $revenue_net   Net revenue.
	 * @param float $max_remaining Max remaining net revenue.
	 * @param float $total_cost    Total cost.
	 * @return float
	 */
	public static function sold_out_result( $revenue_net, $max_remaining, $total_cost ) {
		return (float) $revenue_net + (float) $max_remaining - (float) $total_cost;
	}
}
