/**
 * Prize risk tab: live break-even line on the product edit screen.
 *
 * Reads LTY's ticket price (sale price when set, else regular) and max tickets inputs plus the
 * Prize risk fields as typed, saved or not. Same text as Nera_Prize_Risk_Product::break_even_text().
 */
(function ($, calc, cfg) {
	'use strict';

	if (!calc || !cfg) {
		return;
	}

	var fmt = function (n, digits) {
		return Number(n).toLocaleString('en-GB', { minimumFractionDigits: digits, maximumFractionDigits: digits });
	};

	var num = function (sel) {
		var raw = String($(sel).val() || '').trim();
		if (raw === '') {
			return null;
		}
		raw = raw.split(cfg.decimalPoint).join('.');
		return isFinite(raw) ? Number(raw) : null;
	};

	var update = function () {
		var $line = $('#nera-prize-risk-breakeven');
		if (!$line.length) {
			return;
		}
		var sale = num('#_lty_sale_price');
		var price = sale !== null && sale > 0 ? sale : num('#_lty_regular_price');
		var total = num('#_lty_maximum_tickets');
		total = total === null ? 0 : Math.floor(total);
		if (!(price > 0) || !(total > 0)) {
			$line.text(cfg.i18n.missing);
			return;
		}
		var net = calc.net_price(price, cfg.feeFraction);
		var cost = calc.total_cost(num('#_nera_prize_cost') || 0, num('#_nera_other_costs') || 0);
		var tix = calc.break_even_tix(cost, net);
		var pct = calc.break_even_pct(tix, total);
		var profit = Math.round(total * net - cost);
		var money = (profit < 0 ? '-' : '') + cfg.currencySymbol + fmt(Math.abs(profit), 0);
		if (!calc.can_break_even(tix, total)) {
			$line.text(
				cfg.i18n.warning
					.replace('%1$s', fmt(tix, 0))
					.replace('%2$s', fmt(total, 0))
					.replace('%3$s', cfg.currencySymbol + fmt(Math.abs(profit), 0))
			);
			return;
		}
		$line.text(
			cfg.i18n.line
				.replace('%1$s', fmt(tix, 0))
				.replace('%2$s', fmt(pct * 100, 1))
				.replace('%%', '%')
				.replace('%3$s', fmt(total, 0))
				.replace('%4$s', money)
		);
	};

	$(function () {
		$(document).on(
			'input change',
			'#_lty_regular_price, #_lty_sale_price, #_lty_maximum_tickets, #_nera_prize_cost, #_nera_other_costs',
			update
		);
		update();
	});
})(jQuery, window.NeraPrizeRiskCalc, window.neraPrizeRiskProduct);
