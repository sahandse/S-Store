/* global spbAdmin, wp */
(function ($) {
	'use strict';

	var nextIdx  = spbAdmin.nextIdx || 0;
	var mediaFrame;

	/* ── Tabs ────────────────────────────────────────── */
	function showTab(id) {
		$('.spb-tab').hide();
		$('#spb-tab-' + id).show();
		$('.spb-nav .nav-tab').removeClass('nav-tab-active');
		$('.spb-nav .nav-tab[href="#' + id + '"]').addClass('nav-tab-active');
	}

	$('.spb-nav .nav-tab').on('click', function (e) {
		e.preventDefault();
		var id = $(this).attr('href').replace('#', '');
		showTab(id);
		history.replaceState(null, '', '?page=support-button&spb-tab=' + id);
	});

	showTab(spbAdmin.activeTab || 'channels');

	/* ── Logo upload ─────────────────────────────────── */
	$('#spb-logo-upload').on('click', function (e) {
		e.preventDefault();
		if (mediaFrame) { mediaFrame.open(); return; }
		mediaFrame = wp.media({
			title: 'انتخاب لوگو',
			button: { text: 'انتخاب' },
			multiple: false,
			library: { type: 'image' }
		});
		mediaFrame.on('select', function () {
			var attachment = mediaFrame.state().get('selection').first().toJSON();
			$('#spb-logo-id').val(attachment.id);
			var url = (attachment.sizes && attachment.sizes.thumbnail)
				? attachment.sizes.thumbnail.url
				: attachment.url;
			$('#spb-logo-preview').html('<img src="' + url + '" alt="" />');
			$('#spb-logo-remove').show();
		});
		mediaFrame.open();
	});

	$('#spb-logo-remove').on('click', function (e) {
		e.preventDefault();
		$('#spb-logo-id').val('');
		$('#spb-logo-preview').empty();
		$(this).hide();
		mediaFrame = null;
	});

	/* init logo if already set */
	if (spbAdmin.logoUrl) {
		$('#spb-logo-preview').html('<img src="' + spbAdmin.logoUrl + '" alt="" />');
	}

	/* ── Animation picker ────────────────────────────── */
	$('.spb-anim-opt').on('click', function () {
		$('.spb-anim-opt').removeClass('is-active');
		$(this).addClass('is-active');
	});

	/* ── Position picker ─────────────────────────────── */
	$('.spb-pos-opt').on('click', function () {
		$('.spb-pos-opt').removeClass('is-active');
		$(this).addClass('is-active');
	});

	/* ── Toggle sub-sections ─────────────────────────── */
	$('#spb-sch-on').on('change', function () {
		$('#spb-sch-box').toggle(this.checked);
	});
	$('#spb-bub-on').on('change', function () {
		$('#spb-bub-box').toggle(this.checked);
	});

	/* ── Channel row helpers ─────────────────────────── */
	var presetColors = {};
	$('#spb-row-tpl').contents().filter('tr').find('option[data-color]').each(function () {
		presetColors[$(this).val()] = $(this).data('color');
	});

	/* read preset colors from any existing row select */
	$('.spb-net-sel').first().find('option').each(function () {
		presetColors[$(this).val()] = $(this).data('color');
	});

	function nameRow(tr, idx) {
		tr.find('select, input').each(function () {
			var name = $(this).attr('name') || '';
			if (!name) {
				/* template row — build names from scratch */
				var el = $(this);
				if (el.hasClass('spb-net-sel')) {
					el.attr('name', 'support_btn_channels[' + idx + '][network]');
				} else if (el.hasClass('spb-custom-name')) {
					el.attr('name', 'support_btn_channels[' + idx + '][custom_name]');
				} else if (el.attr('type') === 'text') {
					el.attr('name', 'support_btn_channels[' + idx + '][operator]');
				} else if (el.attr('type') === 'url') {
					el.attr('name', 'support_btn_channels[' + idx + '][url]');
				} else if (el.attr('type') === 'color') {
					el.attr('name', 'support_btn_channels[' + idx + '][color]');
				}
			} else {
				$(this).attr('name', name.replace(/\[\d+\]/, '[' + idx + ']'));
			}
		});
	}

	function reindex() {
		$('#spb-rows .spb-row').each(function (i) {
			nameRow($(this), i);
		});
		nextIdx = $('#spb-rows .spb-row').length;
	}

	function bindRowEvents(tr) {
		tr.find('.spb-remove').on('click', function () {
			tr.remove();
			reindex();
		});

		tr.find('.spb-net-sel').on('change', function () {
			var net = $(this).val();
			var isCustom = (net === 'custom');
			tr.find('.spb-custom-name').toggle(isCustom);
			if (!isCustom && presetColors[net]) {
				tr.find('.spb-color-inp').val(presetColors[net]);
			}
		});
	}

	/* bind existing rows */
	$('#spb-rows .spb-row').each(function () {
		bindRowEvents($(this));
	});

	/* ── Add row ─────────────────────────────────────── */
	$('#spb-add').on('click', function () {
		var tpl = document.getElementById('spb-row-tpl');
		if (!tpl) return;
		var clone = $(tpl.content.querySelector('tr')).clone(true);
		nameRow(clone, nextIdx++);
		$('#spb-rows').append(clone);
		bindRowEvents(clone);
		clone.find('input[type="url"]').focus();
	});

	/* ── Drag & Drop ─────────────────────────────────── */
	var dragSrc = null;

	$('#spb-rows').on('dragstart', '.spb-row', function (e) {
		dragSrc = this;
		e.originalEvent.dataTransfer.effectAllowed = 'move';
		$(this).addClass('dragging');
	});

	$('#spb-rows').on('dragend', '.spb-row', function () {
		$(this).removeClass('dragging');
		$('#spb-rows .spb-row').removeClass('drag-over');
		dragSrc = null;
	});

	$('#spb-rows').on('dragover', '.spb-row', function (e) {
		e.preventDefault();
		e.originalEvent.dataTransfer.dropEffect = 'move';
		if (this !== dragSrc) {
			$('#spb-rows .spb-row').removeClass('drag-over');
			$(this).addClass('drag-over');
		}
	});

	$('#spb-rows').on('drop', '.spb-row', function (e) {
		e.preventDefault();
		if (dragSrc && this !== dragSrc) {
			var srcIdx = $(dragSrc).index();
			var tgtIdx = $(this).index();
			if (srcIdx < tgtIdx) {
				$(dragSrc).insertAfter(this);
			} else {
				$(dragSrc).insertBefore(this);
			}
			reindex();
		}
		$('#spb-rows .spb-row').removeClass('drag-over');
	});

})(jQuery);
