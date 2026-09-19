/**
 * Smart SEO AI Suite Pro - Admin Controller
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		const config = window.smartSeoAiData || {};

		// 1. Live Character Counter & Google SERP Preview Sync
		const $titleInput = $('#smart_seo_title');
		const $descInput  = $('#smart_seo_meta_desc');

		function updateSerpPreview() {
			if ($titleInput.length) {
				const titleVal = $titleInput.val() || $titleInput.attr('placeholder') || '';
				$('#serp-title-preview').text(titleVal);
				$('#smart_seo_title_count').text(titleVal.length + ' / 60');
			}

			if ($descInput.length) {
				const descVal = $descInput.val() || $descInput.attr('placeholder') || '';
				$('#serp-desc-preview').text(descVal);
				$('#smart_seo_meta_desc_count').text(descVal.length + ' / 160');
			}
		}

		$titleInput.on('input keyup', updateSerpPreview);
		$descInput.on('input keyup', updateSerpPreview);

		// 2. Full Site Scan Action
		$('#btn-run-full-scan').on('click', function(e) {
			e.preventDefault();
			const $btn = $(this);
			const origHtml = $btn.html();

			$btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + (config.i18n ? config.i18n.scanning : 'Scanning...'));

			$.ajax({
				url: config.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'smart_seo_ai_run_full_scan',
					security: config.nonce
				},
				success: function(res) {
					$btn.prop('disabled', false).html(origHtml);
					if (res.success) {
						alert(res.data.message || 'اسکن با موفقیت انجام شد.');
						window.location.reload();
					} else {
						alert(res.data.message || 'خطا در اجرای اسکن.');
					}
				},
				error: function() {
					$btn.prop('disabled', false).html(origHtml);
					alert('خطا در ارتباط با سرور.');
				}
			});
		});

		// 3. Auto-Fix All Action
		$('.smart-btn-autofix-all, .smart-btn-quick-fix').on('click', function(e) {
			e.preventDefault();
			if (!confirm(config.i18n ? config.i18n.confirmAutoFix : 'آیا از اجرای رفع خودکار مشکلات اطمینان دارید؟')) {
				return;
			}

			const $btn = $(this);
			const origHtml = $btn.html();
			$btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + (config.i18n ? config.i18n.fixing : 'Fixing...'));

			$.ajax({
				url: config.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'smart_seo_ai_run_autofix',
					security: config.nonce
				},
				success: function(res) {
					$btn.prop('disabled', false).html(origHtml);
					if (res.success) {
						alert(res.data.message || 'مشکلات با موفقیت رفع شدند.');
						window.location.reload();
					} else {
						alert(res.data.message || 'خطا در رفع مشکلات.');
					}
				},
				error: function() {
					$btn.prop('disabled', false).html(origHtml);
					alert('خطا در ارتباط با سرور.');
				}
			});
		});

		// 4. One-Click Rollback
		$(document).on('click', '.smart-btn-rollback', function(e) {
			e.preventDefault();
			const backupId = $(this).data('backup-id');
			if (!backupId) return;

			if (!confirm(config.i18n ? config.i18n.confirmRollback : 'آیا از بازگردانی این تغییر اطمینان دارید؟')) {
				return;
			}

			const $btn = $(this);
			$btn.prop('disabled', true).text('...');

			$.ajax({
				url: config.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'smart_seo_ai_rollback',
					backup_id: backupId,
					security: config.nonce
				},
				success: function(res) {
					if (res.success) {
						alert(res.data.message || 'تغییرات با موفقیت بازگردانی شدند.');
						window.location.reload();
					} else {
						alert(res.data.message || 'خطا در بازگردانی.');
						$btn.prop('disabled', false).text('Rollback');
					}
				},
				error: function() {
					alert('خطا در ارتباط با سرور.');
					$btn.prop('disabled', false).text('Rollback');
				}
			});
		});

		// 5. AI Studio Generator
		$('#btn-run-ai-generate').on('click', function(e) {
			e.preventDefault();
			const $btn = $(this);
			const task = $('#ai-studio-task').val();
			const topic = $('#ai-studio-topic').val();
			const keyword = $('#ai-studio-keyword').val();
			const content = $('#ai-studio-content').val();
			const $resultBox = $('#ai-studio-result-wrapper');

			if (!topic && !content && !keyword) {
				alert('لطفا حداقل یک موضوع، کلمه کلیدی یا متن وارد کنید.');
				return;
			}

			const origHtml = $btn.html();
			$btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + (config.i18n ? config.i18n.aiGenerating : 'Generating...'));
			$resultBox.addClass('loading').text('در حال پردازش هوش مصنوعی...');

			$.ajax({
				url: config.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'smart_seo_ai_generate_content',
					task: task,
					topic: topic,
					keyword: keyword,
					content: content,
					security: config.nonce
				},
				success: function(res) {
					$btn.prop('disabled', false).html(origHtml);
					$resultBox.removeClass('loading');
					if (res.success) {
						$resultBox.text(res.data.result);
						$('#ai-result-actions').show();
					} else {
						$resultBox.text('خطا: ' + (res.data.message || 'مشکلی در اتصال به هوش مصنوعی رخ داد.'));
					}
				},
				error: function() {
					$btn.prop('disabled', false).html(origHtml);
					$resultBox.removeClass('loading').text('خطا در ارتباط با سرور.');
				}
			});
		});

		// 6. Copy AI Result
		$('#btn-copy-ai-result').on('click', function() {
			const text = $('#ai-studio-result-wrapper').text();
			if (!text) return;

			navigator.clipboard.writeText(text).then(function() {
				alert('متن با موفقیت در کلیپ‌بورد کپی شد.');
			}).catch(function() {
				alert('امکان کپی خودکار وجود ندارد.');
			});
		});

		// 7. AI API Key Test
		$('#btn-test-ai-key').on('click', function(e) {
			e.preventDefault();
			const $btn = $(this);
			const $res = $('#ai-key-test-result');
			$btn.prop('disabled', true);
			$res.text('در حال بررسی...');

			$.ajax({
				url: config.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'smart_seo_ai_test_api_key',
					security: config.nonce
				},
				success: function(response) {
					$btn.prop('disabled', false);
					if (response.success) {
						$res.css('color', '#059669').text(response.data.message);
					} else {
						$res.css('color', '#dc2626').text(response.data.message);
					}
				},
				error: function() {
					$btn.prop('disabled', false);
					$res.css('color', '#dc2626').text('خطا در اتصال به سرور.');
				}
			});
		});

		// 8. Single Post AI Auto-Fill Meta
		$('#smart-btn-ai-fill-meta').on('click', function(e) {
			e.preventDefault();
			const $btn = $(this);
			const title = $('#title').val() || $('[name="post_title"]').val() || '';
			const keyword = $('#smart_seo_focus_keyword').val() || '';

			if (!title) {
				alert('لطفا ابتدا عنوان نوشته را وارد کنید.');
				return;
			}

			$btn.prop('disabled', true).text('در حال تولید با AI...');

			$.ajax({
				url: config.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'smart_seo_ai_generate_content',
					task: 'generate_meta_desc',
					topic: title,
					keyword: keyword,
					security: config.nonce
				},
				success: function(res) {
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-superhero"></span> تولید خودکار متا با هوش مصنوعی');
					if (res.success && res.data.result) {
						$('#smart_seo_meta_desc').val(res.data.result).trigger('input');
					}
				},
				error: function() {
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-superhero"></span> تولید خودکار متا با هوش مصنوعی');
				}
			});
		});
	});
})(jQuery);
