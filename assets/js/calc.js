/**
 * Prize risk calculation engine (JS twin of inc/class-nera-prize-risk-calc.php).
 *
 * Same function names and maths, no rounding: round only for display.
 * Fee is a fraction (1.4% = 0.014). Functions that would divide by zero return null.
 * Plain JS, no build step: sets window.NeraPrizeRiskCalc in the browser, module.exports in node.
 */
(function (root) {
	'use strict';

	var calc = {
		net_price: function (ticketPrice, feePct) {
			return Number(ticketPrice) * (1 - Number(feePct));
		},
		total_cost: function (prizeCost, otherCosts) {
			return Number(prizeCost) + Number(otherCosts);
		},
		break_even_tix: function (totalCost, netPrice) {
			if (!(Number(netPrice) > 0)) {
				return null;
			}
			return Math.ceil(Number(totalCost) / Number(netPrice));
		},
		break_even_pct: function (breakEvenTix, totalTickets) {
			if (breakEvenTix === null || breakEvenTix === undefined || !(parseInt(totalTickets, 10) > 0)) {
				return null;
			}
			return parseInt(breakEvenTix, 10) / parseInt(totalTickets, 10);
		},
		can_break_even: function (breakEvenTix, totalTickets) {
			if (breakEvenTix === null || breakEvenTix === undefined || !(parseInt(totalTickets, 10) > 0)) {
				return true;
			}
			return parseInt(breakEvenTix, 10) <= parseInt(totalTickets, 10);
		},
		revenue_net: function (grossLineTotals, feePct) {
			return Number(grossLineTotals) * (1 - Number(feePct));
		},
		position: function (revenueNet, totalCost) {
			return Number(revenueNet) - Number(totalCost);
		},
		sell_through: function (paidTickets, freeEntries, totalTickets) {
			if (!(parseInt(totalTickets, 10) > 0)) {
				return null;
			}
			return (parseInt(paidTickets, 10) + parseInt(freeEntries, 10)) / parseInt(totalTickets, 10);
		},
		max_remaining: function (totalTickets, paidTickets, freeEntries, netPrice) {
			return (parseInt(totalTickets, 10) - parseInt(paidTickets, 10) - parseInt(freeEntries, 10)) * Number(netPrice);
		},
		sold_out_result: function (revenueNet, maxRemaining, totalCost) {
			return Number(revenueNet) + Number(maxRemaining) - Number(totalCost);
		}
	};

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = calc;
	}
	if (root) {
		root.NeraPrizeRiskCalc = calc;
	}
})(typeof window !== 'undefined' ? window : null);
