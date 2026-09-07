(function () {
	'use strict';

	var current = window.fqpData ? window.fqpData.product : null;

	function formatMoney(amount) {
		var currency = window.fqpData.currency;
		var decimals = parseInt(currency.decimals, 10);
		var fixed = Number(amount || 0).toFixed(decimals);
		var parts = fixed.split('.');
		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, currency.thousandSep);
		var number = parts.join(currency.decimalSep);
		return currency.priceFormat.replace('%1$s', currency.symbol).replace('%2$s', number);
	}

	function formatPercent(value) {
		value = Number(value || 0);
		return value ? parseFloat(value.toFixed(4)).toString() + '%' : '-';
	}

	function findTier(qty) {
		var rows = [{min_qty: 1, max_qty: 1, price: current.base_price_display, discount: 0, label: '1'}].concat((current.tiers || []).map(function (tier) {
			tier.price = tier.price_display;
			return tier;
		}));
		for (var i = 0; i < rows.length; i++) {
			var max = rows[i].max_qty;
			if (qty >= rows[i].min_qty && (max === null || max === '' || qty <= max)) {
				return rows[i];
			}
		}
		return rows[0];
	}

	function rebuildTable(box) {
		var tbody = box.querySelector('tbody');
		if (!tbody || !current) {
			return;
		}
		var html = '<tr data-min="1" data-max="1" data-price="' + current.base_price_display + '" data-discount="0"><td>1</td><td>-</td><td>' + formatMoney(current.base_price_display) + '</td></tr>';
		(current.tiers || []).forEach(function (tier) {
			html += '<tr data-min="' + tier.min_qty + '" data-max="' + (tier.max_qty === null ? '' : tier.max_qty) + '" data-price="' + tier.price_display + '" data-discount="' + tier.discount + '"><td>' + tier.label + '</td><td>' + formatPercent(tier.discount) + '</td><td>' + formatMoney(tier.price_display) + '</td></tr>';
		});
		tbody.innerHTML = html;
	}

	function update(box) {
		if (!box || !current || !current.enabled) {
			var hiddenSummary = box ? box.querySelector('.fqp-live-summary') : null;
			if (hiddenSummary) {
				hiddenSummary.style.display = 'none';
			}
			return;
		}
		var qtyInput = document.querySelector('form.cart input.qty');
		var qty = qtyInput ? Math.max(1, parseInt(qtyInput.value || '1', 10)) : 1;
		var tier = findTier(qty);
		var total = tier.price * qty;
		var original = current.base_price_display * qty;
		var savings = Math.max(0, original - total);
		var summary = box.querySelector('.fqp-live-summary');

		box.querySelectorAll('tbody tr').forEach(function (row) {
			var min = parseInt(row.dataset.min || '1', 10);
			var max = row.dataset.max === '' ? null : parseInt(row.dataset.max || '1', 10);
			row.classList.toggle('is-active', qty >= min && (max === null || qty <= max));
		});

		if (box.querySelector('[data-fqp-unit]')) {
			box.querySelector('[data-fqp-unit]').innerHTML = formatMoney(tier.price);
		}
		if (box.querySelector('[data-fqp-total]')) {
			box.querySelector('[data-fqp-total]').innerHTML = formatMoney(total);
		}
		if (box.querySelector('[data-fqp-savings]')) {
			var message = savings > 0 ? window.fqpData.i18n.you_save.replace('%s', formatMoney(savings)) : window.fqpData.i18n.no_discount;
			if (tier.discount > 0 && savings <= 0) {
				message = window.fqpData.i18n.applied.replace('%s', formatPercent(tier.discount));
			}
			box.querySelector('[data-fqp-savings]').innerHTML = message;
		}

		if (summary) {
			summary.style.display = '';
			summary.querySelector('.fqp-unit-row').style.display = summary.dataset.showUnit === 'yes' ? '' : 'none';
			summary.querySelector('.fqp-total-row').style.display = summary.dataset.showTotal === 'yes' ? '' : 'none';
			summary.querySelector('.fqp-savings-row').style.display = summary.dataset.showSavings === 'yes' ? '' : 'none';
			box.classList.toggle('fqp-no-active', summary.dataset.showActive !== 'yes');
		}
	}

	function setPayload(payload) {
		if (payload && payload.enabled) {
			current = payload;
		} else if (window.fqpData && window.fqpData.product) {
			current = window.fqpData.product;
		}
		var box = document.querySelector('[data-fqp-pricing]');
		if (box) {
			rebuildTable(box);
			update(box);
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		var box = document.querySelector('[data-fqp-pricing]');
		if (!box) {
			return;
		}
		update(box);
		document.addEventListener('input', function (event) {
			if (event.target.matches('form.cart input.qty')) {
				update(box);
			}
		});
		document.addEventListener('change', function (event) {
			if (event.target.matches('form.cart input.qty')) {
				update(box);
			}
		});

		if (window.jQuery) {
			window.jQuery('form.variations_form').on('found_variation', function (event, variation) {
				setPayload(variation.fqp_pricing);
			}).on('reset_data hide_variation', function () {
				setPayload(window.fqpData.product);
			});
		}
	});
}());
