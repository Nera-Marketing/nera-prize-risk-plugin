<?php
/**
 * CLI test for the calculation engine: php tests/calc-test.php
 * Asserts the brief's worked example (task.md §5). Exits 1 on any failure.
 */

define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__ ) . '/inc/class-nera-prize-risk-calc.php';

$c = 'Nera_Prize_Risk_Calc';

// Worked example inputs.
$prize_cost    = 42000;
$other_costs   = 0;
$ticket_price  = 4.99;
$total_tickets = 15000;
$fee_pct       = 0.014;
$paid          = 6000;
$free          = 0;

$net        = $c::net_price( $ticket_price, $fee_pct );
$total_cost = $c::total_cost( $prize_cost, $other_costs );
$be_tix     = $c::break_even_tix( $total_cost, $net );
$be_pct     = $c::break_even_pct( $be_tix, $total_tickets );
$sold_out   = $c::sold_out_result( 0, $c::max_remaining( $total_tickets, 0, 0, $net ), $total_cost );
$rev_6000   = $c::revenue_net( $paid * $ticket_price, $fee_pct );
$pos_6000   = $c::position( $rev_6000, $total_cost );
$sold_out_6 = $c::sold_out_result( $rev_6000, $c::max_remaining( $total_tickets, $paid, $free, $net ), $total_cost );

$fail  = 0;
$check = static function ( $label, $actual, $expected, $dp ) use ( &$fail ) {
	$shown = null === $actual ? 'null' : rtrim( rtrim( number_format( $actual, $dp, '.', '' ), '0' ), '.' );
	$ok    = null !== $actual && abs( $actual - $expected ) < pow( 10, -$dp ) / 2;
	if ( ! $ok ) {
		++$fail;
	}
	echo $label . ' ' . $shown . ( $ok ? '' : '  FAIL (expected ' . $expected . ')' ) . "\n";
};

$check( 'net_price', $net, 4.92014, 5 );
$check( 'break_even_tix', $be_tix, 8537, 0 );
$check( 'break_even_pct', $be_pct, 0.5691, 4 );
$check( 'sold_out_result', $sold_out, 31802.1, 2 );
$check( 'position@6000', $pos_6000, -12479.16, 2 );
$check( 'sold_out_result@6000', $sold_out_6, 31802.1, 2 );
$check( 'sell_through@6000', $c::sell_through( $paid, $free, $total_tickets ), 0.4, 4 );

// Guard: the rounded net price gives the wrong answer, so the engine must not round.
if ( abs( $sold_out - ( 4.92 * $total_tickets - $total_cost ) ) < 1 ) {
	++$fail;
	echo "net_price was rounded before use  FAIL\n";
}
// Divide-by-zero guards.
if ( null !== $c::break_even_tix( 100, 0 ) || null !== $c::break_even_pct( 10, 0 ) || null !== $c::sell_through( 1, 0, 0 ) ) {
	++$fail;
	echo "zero guards  FAIL\n";
}

echo $fail ? "FAILED: $fail\n" : "OK\n";
exit( $fail ? 1 : 0 );
