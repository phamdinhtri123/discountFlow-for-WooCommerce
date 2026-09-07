(function () {
	'use strict';

	function renumberRows(box) {
		box.querySelectorAll('.fqp-tier-row').forEach(function (row, index) {
			row.querySelectorAll('input').forEach(function (input) {
				input.name = input.name.replace(/\[tiers]\[\d+]/, '[tiers][' + index + ']');
			});
		});
	}

	function setMode(box) {
		var mode = box.querySelector('select[name$="[mode]"]');
		var fixed = mode && mode.value === 'fixed';
		box.dataset.mode = fixed ? 'fixed' : 'percentage';
		box.querySelectorAll('.fqp-discount-field').forEach(function (input) {
			input.style.display = fixed ? 'none' : '';
		});
		box.querySelectorAll('.fqp-price-field').forEach(function (input) {
			input.style.display = fixed ? '' : 'none';
		});
	}

	function addTier(box) {
		var tbody = box.querySelector('.fqp-tier-table tbody');
		var firstInput = box.querySelector('.fqp-tier-row input');
		var nameBase = firstInput ? firstInput.name.replace(/\[tiers].*$/, '') : box.dataset.name;
		var index = tbody.querySelectorAll('.fqp-tier-row').length;
		var row = document.createElement('tr');
		row.className = 'fqp-tier-row';
		row.draggable = true;
		row.innerHTML =
			'<td class="fqp-handle">::</td>' +
			'<td><input type="number" min="2" step="1" name="' + nameBase + '[tiers][' + index + '][min_qty]" value="2"></td>' +
			'<td><input type="number" min="2" step="1" name="' + nameBase + '[tiers][' + index + '][max_qty]" placeholder="' + (window.fqpAdmin && fqpAdmin.i18n ? fqpAdmin.i18n.unlimited : 'Unlimited') + '"></td>' +
			'<td><input class="fqp-discount-field" type="number" min="0" max="100" step="0.0001" name="' + nameBase + '[tiers][' + index + '][discount]" value="0"><input class="fqp-price-field" type="number" min="0" step="0.0001" name="' + nameBase + '[tiers][' + index + '][price]" value="" style="display:none"></td>' +
			'<td><button type="button" class="button fqp-remove-tier">Remove</button></td>';
		tbody.appendChild(row);
		setMode(box);
		updatePreview(box);
	}

	function updatePreview(box) {
		var preview = box.querySelector('.fqp-preview-lines');
		if (!preview) {
			return;
		}
		var regular = parseFloat((document.querySelector('#_regular_price') || {}).value || '0');
		var sale = parseFloat((document.querySelector('#_sale_price') || {}).value || '0');
		var source = (box.querySelector('select[name$="[base_source]"]') || {}).value || 'active';
		var base = source === 'regular' ? regular : (sale > 0 ? sale : regular);
		var lines = ['<p>1 -> ' + (base > 0 ? base.toFixed(2) : (window.fqpAdmin && fqpAdmin.i18n ? fqpAdmin.i18n.qty_one : 'Quantity 1 uses the normal WooCommerce price.')) + '</p>'];
		var mode = box.dataset.mode || 'percentage';
		box.querySelectorAll('.fqp-tier-row').forEach(function (row) {
			var min = row.querySelector('[name$="[min_qty]"]').value || '2';
			var max = row.querySelector('[name$="[max_qty]"]').value;
			var value = mode === 'fixed' ? row.querySelector('.fqp-price-field').value : row.querySelector('.fqp-discount-field').value;
			var label = max ? (min === max ? min : min + '-' + max) : min + '+';
			var numeric = parseFloat(value || '0');
			var suffix = mode === 'fixed' ? numeric.toFixed(2) : (base > 0 ? (base * (1 - numeric / 100)).toFixed(2) : value + '%');
			lines.push('<p>' + label + ' -> ' + suffix + '</p>');
		});
		preview.innerHTML = lines.join('');
	}

	function initBox(box) {
		setMode(box);
		updatePreview(box);
		box.querySelectorAll('.fqp-tier-row').forEach(function (row) {
			row.draggable = true;
		});
	}

	document.addEventListener('click', function (event) {
		var add = event.target.closest('.fqp-add-tier');
		if (add) {
			addTier(add.closest('[data-fqp-admin]'));
			return;
		}
		var remove = event.target.closest('.fqp-remove-tier');
		if (remove) {
			var box = remove.closest('[data-fqp-admin]');
			remove.closest('.fqp-tier-row').remove();
			renumberRows(box);
			updatePreview(box);
		}
	});

	document.addEventListener('change', function (event) {
		var box = event.target.closest('[data-fqp-admin]');
		if (!box) {
			return;
		}
		if (event.target.matches('select[name$="[mode]"]')) {
			setMode(box);
		}
		updatePreview(box);
	});

	document.addEventListener('input', function (event) {
		var box = event.target.closest('[data-fqp-admin]');
		if (box) {
			updatePreview(box);
		}
	});

	var dragged;
	document.addEventListener('dragstart', function (event) {
		if (event.target.classList.contains('fqp-tier-row')) {
			dragged = event.target;
		}
	});
	document.addEventListener('dragover', function (event) {
		var row = event.target.closest('.fqp-tier-row');
		if (row && dragged && row !== dragged) {
			event.preventDefault();
			row.parentNode.insertBefore(dragged, row.nextSibling);
		}
	});
	document.addEventListener('dragend', function (event) {
		var box = event.target.closest('[data-fqp-admin]');
		if (box) {
			renumberRows(box);
			updatePreview(box);
		}
		dragged = null;
	});

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-fqp-admin]').forEach(initBox);
	});
}());
