(function () {
	'use strict';

	const modal = document.getElementById('rentiva-transfer-addon-modal');
	if (!modal || modal.dataset.empty === '1') {
		return; // No transfer addons configured — let direct-add flow work.
	}

	const dataScript = document.getElementById('rentiva-transfer-addon-modal-data');
	const ADDONS = JSON.parse(dataScript.textContent || '[]');

	const listEl = modal.querySelector('[data-addon-list]');
	const totalLine = modal.querySelector('[data-total-line]');
	const routeLine = modal.querySelector('[data-route-line]');
	const submitBtn = modal.querySelector('[data-modal-submit]');

	let currentContext = null;

	function format(amount) {
		const symbol = (window.mhmRentivaAddons && window.mhmRentivaAddons.currencySymbol) || '';
		return amount.toFixed(2).replace('.', ',') + ' ' + symbol;
	}

	function multiplier(type, ctx) {
		if (type === 'per_passenger') return Math.max(1, (ctx.adults || 0) + (ctx.children || 0));
		return 1;
	}

	function buildPriceLabel(addon, ctx) {
		const m = multiplier(addon.pricing_type, ctx);
		const total = addon.price * m;
		if (addon.pricing_type === 'per_passenger' && m > 1) {
			return '+ ' + format(addon.price) + ' × ' + m + ' ' + (window.mhmRentivaTransferModalI18n?.passengers || 'passengers') + ' = ' + format(total);
		}
		return '+ ' + format(total);
	}

	function recompute() {
		const checkboxes = listEl.querySelectorAll('.addon-row__check');
		let addonTotal = 0;
		checkboxes.forEach((cb) => {
			if (!cb.checked) return;
			const id = parseInt(cb.value, 10);
			const addon = ADDONS.find((a) => a.id === id);
			if (!addon) return;
			addonTotal += addon.price * multiplier(addon.pricing_type, currentContext);
		});
		const grand = (currentContext.baseTotal || 0) + addonTotal;
		const vehicleLabel = window.mhmRentivaTransferModalI18n?.vehicleLabel || 'Vehicle';
		const addonsLabel = window.mhmRentivaTransferModalI18n?.addonsLabel || 'Add-ons';
		totalLine.innerHTML =
			vehicleLabel + ' ' + format(currentContext.baseTotal || 0) +
			' + ' + addonsLabel + ' ' + format(addonTotal) +
			' = <strong>' + format(grand) + '</strong>';
	}

	function escapeHtml(s) {
		const div = document.createElement('div');
		div.textContent = s;
		return div.innerHTML;
	}

	function renderList() {
		listEl.innerHTML = '';
		const requiredText = window.mhmRentivaTransferModalI18n?.required || 'required';
		ADDONS.forEach((addon) => {
			const row = document.createElement('label');
			row.className = 'addon-row';
			const requiredMark = addon.required
				? '<span class="addon-required">' + requiredText + '</span>'
				: '';
			row.innerHTML =
				'<input type="checkbox" class="addon-row__check" value="' + addon.id + '"' +
				(addon.required ? ' checked disabled' : '') + '>' +
				'<div class="addon-row__body">' +
				'<div class="addon-row__title">' + escapeHtml(addon.title) + requiredMark + '</div>' +
				(addon.description ? '<div class="addon-row__desc">' + escapeHtml(addon.description) + '</div>' : '') +
				'<div class="addon-row__price">' + buildPriceLabel(addon, currentContext) + '</div>' +
				'</div>';
			listEl.appendChild(row);
		});
		listEl.querySelectorAll('.addon-row__check').forEach((cb) => {
			cb.addEventListener('change', recompute);
		});
	}

	function open(context) {
		currentContext = context;
		routeLine.textContent =
			(context.originName || '') + ' ➝ ' + (context.destinationName || '');
		renderList();
		recompute();
		modal.dataset.open = '1';
	}

	function close() {
		modal.dataset.open = '0';
	}

	function submit() {
		const selected = Array.from(listEl.querySelectorAll('.addon-row__check'))
			.filter((cb) => cb.checked)
			.map((cb) => parseInt(cb.value, 10));

		if (typeof currentContext.onConfirm === 'function') {
			currentContext.onConfirm(selected);
		}
		close();
	}

	modal.querySelectorAll('[data-modal-close]').forEach((el) => {
		el.addEventListener('click', close);
	});
	submitBtn.addEventListener('click', submit);
	document.addEventListener('keydown', (e) => {
		if (e.key === 'Escape' && modal.dataset.open === '1') close();
	});

	// Public API for rentiva-transfer.js
	window.RentivaTransferAddonModal = {
		open,
		close,
		hasAddons: () => ADDONS.length > 0,
	};
})();
