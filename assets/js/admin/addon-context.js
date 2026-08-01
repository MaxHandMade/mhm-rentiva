(function () {
	'use strict';

	const ALLOWED = {
		rental:   ['per_booking', 'per_day'],
		transfer: ['per_booking', 'per_passenger'],
		both:     ['per_booking', 'per_day', 'per_passenger'],
	};

	function applyConstraints() {
		const radios = document.querySelectorAll('input[name="mhmrentiva_addon_context"]');
		const select = document.querySelector('select[name="_mhmrentiva_addon_pricing_type"]');
		if (!radios.length || !select) {
			return;
		}

		const selectedRadio = Array.from(radios).find((r) => r.checked);
		if (!selectedRadio) {
			return;
		}

		const allowed = ALLOWED[selectedRadio.value] || ['per_booking'];
		const incompatibleLabel = (window.mhmRentivaAddonContextI18n && window.mhmRentivaAddonContextI18n.incompatible)
			|| ' (incompatible with context)';
		Array.from(select.options).forEach((opt) => {
			const isAllowed = allowed.indexOf(opt.value) !== -1;
			opt.disabled = !isAllowed;
			// Mark the label so admins notice why it's greyed.
			const original = opt.dataset.originalText || opt.textContent;
			opt.dataset.originalText = original;
			opt.textContent = isAllowed
				? original
				: original + incompatibleLabel;
		});

		// If the current selection is no longer allowed, snap to per_booking.
		const currentOpt = select.options[select.selectedIndex];
		if (currentOpt && currentOpt.disabled) {
			select.value = 'per_booking';
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		applyConstraints();
		document.querySelectorAll('input[name="mhmrentiva_addon_context"]').forEach((r) => {
			r.addEventListener('change', applyConstraints);
		});
	});
})();
