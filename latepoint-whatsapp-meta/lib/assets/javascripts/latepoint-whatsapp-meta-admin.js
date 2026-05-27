/**
 * Searchable dropdown enhancement for the WhatsApp template selector.
 *
 * The base LatePoint plugin renders a native <select> with class
 * `process-action-type-whatsapp-template-selector`. This script wraps each such
 * select with a trigger button + dropdown panel containing a search input and
 * a filterable option list. The native <select> stays in the DOM (hidden) as
 * the source of truth — the existing 'change' handler in the base plugin
 * fires unchanged when an option is picked.
 */
(function ($) {
	if (typeof $ === 'undefined') return;

	var ENHANCED_FLAG = 'lpWmEnhanced';

	function enhanceSelect($select) {
		if ($select.data(ENHANCED_FLAG)) return;
		$select.data(ENHANCED_FLAG, true);

		var options = $select.find('option').map(function () {
			return { value: $(this).val(), label: $(this).text() };
		}).get();

		var currentValue = String($select.val() || '');
		var currentLabel = '';
		options.some(function (o) {
			if (String(o.value) === currentValue) { currentLabel = o.label; return true; }
			return false;
		});
		if (!currentLabel && options.length) currentLabel = options[0].label;

		var $wrapper = $('<div class="latepoint-whatsapp-template-searchable-select"></div>');
		var $trigger = $(
			'<div class="latepoint-whatsapp-template-trigger" tabindex="0">' +
				'<span class="latepoint-whatsapp-template-trigger-label"></span>' +
				'<i class="latepoint-icon latepoint-icon-angle-down"></i>' +
			'</div>'
		);
		$trigger.find('.latepoint-whatsapp-template-trigger-label').text(currentLabel);

		var $panel = $(
			'<div class="latepoint-whatsapp-template-panel">' +
				'<div class="latepoint-whatsapp-template-search-w">' +
					'<i class="latepoint-icon latepoint-icon-search"></i>' +
					'<input type="text" class="latepoint-whatsapp-template-search" autocomplete="off" />' +
				'</div>' +
				'<div class="latepoint-whatsapp-template-options"></div>' +
			'</div>'
		);
		$panel.find('.latepoint-whatsapp-template-search').attr(
			'placeholder',
			(window.latepoint_helper && latepoint_helper.string_start_typing_to_search) || 'Start typing to search...'
		);

		$wrapper.data('lp-wm-options', options);
		$select.before($wrapper);
		$wrapper.append($trigger).append($panel).append($select);
	}

	function renderOptions($wrapper, term) {
		var $select = $wrapper.find('> .process-action-type-whatsapp-template-selector');
		var $optionsHolder = $wrapper.find('.latepoint-whatsapp-template-options');
		var options = $wrapper.data('lp-wm-options') || [];
		var selectedValue = String($select.val() || '');
		var needle = (term || '').toLowerCase().trim();
		var filtered = needle
			? options.filter(function (o) { return o.label.toLowerCase().indexOf(needle) !== -1; })
			: options;

		$optionsHolder.empty();
		if (!filtered.length) {
			$optionsHolder.append(
				$('<div class="latepoint-whatsapp-template-option-empty"></div>').text('No results')
			);
			return;
		}
		filtered.forEach(function (o) {
			var $opt = $('<div class="latepoint-whatsapp-template-option"></div>')
				.attr('data-value', o.value)
				.text(o.label);
			if (String(o.value) === selectedValue) $opt.addClass('is-selected');
			$optionsHolder.append($opt);
		});
	}

	function scanAndEnhance(root) {
		$('.process-action-type-whatsapp-template-selector', root).each(function () {
			enhanceSelect($(this));
		});
		if (root && root.nodeType === 1 && $(root).is('.process-action-type-whatsapp-template-selector')) {
			enhanceSelect($(root));
		}
	}

	$(function () {
		scanAndEnhance(document.body);

		if (typeof MutationObserver !== 'undefined') {
			var observer = new MutationObserver(function (mutations) {
				mutations.forEach(function (m) {
					m.addedNodes.forEach(function (node) {
						if (node.nodeType === 1) scanAndEnhance(node);
					});
				});
			});
			observer.observe(document.body, { childList: true, subtree: true });
		}

		$(document).on('click', '.latepoint-whatsapp-template-trigger', function (e) {
			e.stopPropagation();
			var $wrapper = $(this).closest('.latepoint-whatsapp-template-searchable-select');
			var wasOpen = $wrapper.hasClass('is-open');
			$('.latepoint-whatsapp-template-searchable-select.is-open').removeClass('is-open');
			if (wasOpen) return;
			$wrapper.addClass('is-open');
			renderOptions($wrapper, '');
			$wrapper.find('.latepoint-whatsapp-template-search').val('').trigger('focus');
		});

		$(document).on('input', '.latepoint-whatsapp-template-search', function () {
			var $wrapper = $(this).closest('.latepoint-whatsapp-template-searchable-select');
			renderOptions($wrapper, $(this).val());
		});

		$(document).on('click', '.latepoint-whatsapp-template-option', function () {
			var $opt = $(this);
			var value = String($opt.attr('data-value'));
			var label = $opt.text();
			var $wrapper = $opt.closest('.latepoint-whatsapp-template-searchable-select');
			var $select = $wrapper.find('> .process-action-type-whatsapp-template-selector');
			$wrapper.removeClass('is-open');
			if (String($select.val()) === value) return;
			$select.val(value).trigger('change');
			$wrapper.find('.latepoint-whatsapp-template-trigger-label').text(label);
		});

		$(document).on('click.lpWmTemplateSelector', function (e) {
			if (!$(e.target).closest('.latepoint-whatsapp-template-searchable-select').length) {
				$('.latepoint-whatsapp-template-searchable-select.is-open').removeClass('is-open');
			}
		});
	});
})(window.jQuery);
