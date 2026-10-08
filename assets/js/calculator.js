/**
 * Prize Risk calculator: recalculates on every input, saves nothing, sends no requests.
 * Uses NeraPrizeRiskCalc (assets/js/calc.js). Config in window.neraPrizeRiskCalculator.
 * On the Prize Risk screen the form sits in a modal <dialog> opened by #nera-prize-risk-calc-open.
 * window.NeraPrizeRiskCalculator.open(values) fills the inputs, recalculates and opens the modal.
 */
(function () {
	'use strict';

	var cfg = window.neraPrizeRiskCalculator || {};
	var calc = window.NeraPrizeRiskCalc;
	var box = document.getElementById('nera-prize-risk-calc');
	if (!box || !calc) {
		return;
	}

	var i18n = cfg.i18n || {};
	var fee = Number(cfg.feeFraction) || 0;
	var sym = cfg.currencySymbol || '£';
	var EMPTY = i18n.empty || '—';

	function group(intStr) {
		return intStr.replace(/\B(?=(\d{3})+(?!\d))/g, cfg.thousandSep === undefined ? ',' : cfg.thousandSep);
	}

	function fixed(n, dp) {
		var parts = Math.abs(n).toFixed(dp).split('.');
		return group(parts[0]) + (parts[1] ? (cfg.decimalPoint || '.') + parts[1] : '');
	}

	function field(name) {
		var el = box.querySelector('[data-field="' + name + '"]');
		var v = el ? String(el.value).trim() : '';
		return v === '' ? null : Number(v);
	}

	function out(key, text, state) {
		var el = document.getElementById('nera-prize-risk-calc-out-' + key);
		if (!el) {
			return;
		}
		el.textContent = text;
		el.className = state ? 'nera-prize-risk-' + state : '';
	}

	// Whole-pound result with a profit/loss word: "£31,802 profit", "-£12,479 loss".
	function result(key, value) {
		if (value === null || !isFinite(value)) {
			out(key, EMPTY, '');
			return;
		}
		var rounded = Math.round(value);
		var loss = rounded < 0;
		out(key, (loss ? '-' : '') + sym + fixed(rounded, 0) + ' ' + (loss ? i18n.loss : i18n.profit), loss ? 'loss' : 'profit');
	}

	function update() {
		var prize = field('prize_cost');
		var other = field('other_costs');
		var price = field('ticket_price');
		var total = field('total_tickets');
		var sell = field('sell_through');

		var hasCost = prize !== null || other !== null;
		var cost = hasCost ? calc.total_cost(prize || 0, other || 0) : null;
		var net = price !== null && price > 0 ? calc.net_price(price, fee) : null;
		var totalOk = total !== null && total > 0;

		// Break-even.
		var tix = cost !== null && net !== null ? calc.break_even_tix(cost, net) : null;
		var pct = tix !== null && totalOk ? calc.break_even_pct(tix, total) : null;
		if (tix === null) {
			out('break_even', EMPTY, '');
		} else if (pct === null) {
			out('break_even', group(String(tix)), '');
		} else {
			out('break_even', (i18n.tickets || '%1$s tickets (%2$s%%)').replace('%1$s', group(String(tix))).replace('%2$s', fixed(pct * 100, 1)).replace('%%', '%'), '');
		}

		// Expected sell-through, sold out, nothing sells.
		var ready = cost !== null && net !== null && totalOk;
		var sold = ready && sell !== null ? total * Math.min(Math.max(sell, 0), 100) / 100 : null;
		result('expected', sold !== null ? calc.position(sold * net, cost) : null);
		result('sold_out', ready ? calc.sold_out_result(0, calc.max_remaining(total, 0, 0, net), cost) : null);
		result('nothing', cost !== null ? -cost : null);
	}

	box.addEventListener('input', update);
	update();

	// Modal: open from the title button; close with the Close button, Esc (native) or a backdrop click.
	var dialog = document.getElementById('nera-prize-risk-calc-dialog');
	var trigger = document.getElementById('nera-prize-risk-calc-open');

	function open(values) {
		if (values) {
			Object.keys(values).forEach(function (name) {
				var el = box.querySelector('[data-field="' + name + '"]');
				if (el) {
					el.value = values[name] === null || values[name] === undefined ? '' : values[name];
				}
			});
			update();
		}
		if (dialog && !dialog.open) {
			dialog.showModal();
		}
	}

	if (dialog) {
		if (trigger) {
			trigger.addEventListener('click', function (e) {
				e.preventDefault();
				open();
			});
		}
		var closeBtn = document.getElementById('nera-prize-risk-calc-close');
		if (closeBtn) {
			closeBtn.addEventListener('click', function () {
				dialog.close();
			});
		}
		// A click on the backdrop targets the dialog element itself, outside its box.
		dialog.addEventListener('click', function (e) {
			if (e.target !== dialog) {
				return;
			}
			var r = dialog.getBoundingClientRect();
			if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) {
				dialog.close();
			}
		});
		dialog.addEventListener('close', function () {
			if (trigger) {
				trigger.focus();
			}
		});
	}

	window.NeraPrizeRiskCalculator = { open: open, update: update };
})();
