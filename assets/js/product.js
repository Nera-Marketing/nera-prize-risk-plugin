/**
 * Prize risk tab: live break-even line on the product edit screen.
 *
 * Reads LTY's Ticket Price Type, ticket price (sale price when not empty and below regular, like WC is_on_sale(),
 * else regular) and max tickets inputs plus the Prize risk fields as typed, saved or not. Same text as
 * Nera_Prize_Risk_Product::break_even_text() gives after saving the same values.
 */
(function ($, calc, cfg) {
	'use strict';

	if (!calc || !cfg) {
		return;
	}

	var fmt = function (n, digits) {
		return Number(n).toLocaleString('en-GB', { minimumFractionDigits: digits, maximumFractionDigits: digits });
	};

	var q = function (s) {
		return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
	};

	// Prize risk fields, strict like posted_decimal(): null = empty, NaN = invalid.
	var own = function (sel) {
		var raw = String($(sel).val() || '').trim();
		if (raw === '') {
			return null;
		}
		if (cfg.thousandSep && raw.indexOf(cfg.thousandSep) !== -1) {
			// Thousand separators only in valid grouping positions (42,000 / 1,500.50); 2,5 is a typo, not 25.
			var grouped = new RegExp('^\\d{1,3}(?:' + q(cfg.thousandSep) + '\\d{3})+(?:' + q(cfg.decimalPoint) + '\\d+)?$');
			if (!grouped.test(raw)) {
				return NaN;
			}
			raw = raw.split(cfg.thousandSep).join('');
		}
		raw = raw.split(cfg.decimalPoint).join('.');
		// Plain decimals only: no sign, exponent (1e3) or hex.
		return /^\d+(\.\d+)?$/.test(raw) ? Number(raw) : NaN;
	};

	// LTY fields, read the way LTY saves them (wc_format_decimal): decimal separator to '.', keep the last '.',
	// drop everything but digits, '.' and '-'. null = empty after cleaning, NaN = not a number.
	var lty = function (sel) {
		var raw = String($(sel).val() || '').trim();
		if (cfg.decimalPoint) {
			raw = raw.split(cfg.decimalPoint).join('.');
		}
		raw = raw.replace(/\.(?![^.]+$)|[^0-9.-]/g, '');
		return raw === '' ? null : Number(raw);
	};

	var update = function () {
		var $line = $('#nera-prize-risk-breakeven');
		if (!$line.length) {
			return;
		}
		var type = $('#_lty_ticket_price_type');
		var sale = lty('#_lty_sale_price');
		var regular = lty('#_lty_regular_price');
		// LTY saves empty prices for a Free ticket price type.
		var free = type.length && type.val() !== '1';
		var price = free ? 0 : sale !== null && regular !== null && regular > sale ? sale : regular;
		var total = lty('#_lty_maximum_tickets');
		total = total === null ? 0 : Math.floor(total);
		if (!(price > 0) || !(total > 0)) {
			$line.text(cfg.i18n.missing);
			return;
		}
		var prize = own('#_nera_prize_cost');
		var other = own('#_nera_other_costs');
		if (isNaN(prize) || isNaN(other)) {
			$line.text(cfg.i18n.invalid);
			return;
		}
		var net = calc.net_price(price, cfg.feeFraction);
		var cost = calc.total_cost(prize || 0, other || 0);
		var tix = calc.break_even_tix(cost, net);
		var pct = calc.break_even_pct(tix, total);
		var profit = Math.round(total * net - cost);
		var money = (profit < 0 ? '-' : '') + cfg.currencySymbol + fmt(Math.abs(profit), 0);
		if (tix === null) {
			$line.text(cfg.i18n.noNet.replace('%s', cfg.currencySymbol + fmt(Math.abs(profit), 0)));
			return;
		}
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
			'#_lty_ticket_price_type, #_lty_regular_price, #_lty_sale_price, #_lty_maximum_tickets, #_nera_prize_cost, #_nera_other_costs',
			update
		);
		update();
	});
})(jQuery, window.NeraPrizeRiskCalc, window.neraPrizeRiskProduct);
