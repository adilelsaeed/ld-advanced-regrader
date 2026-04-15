/**
 * Advanced Regrader for LearnDash - Admin Scripts
 *
 * @package LD_Advanced_Regrader
 * @since   1.0.0
 */

/* global jQuery, ldAdvancedRegrader */
(function ($) {
	'use strict';

	$(document).ready(function () {

		// Select All checkbox
		var selectAll = document.getElementById('select-all');
		if (selectAll) {
			selectAll.addEventListener('click', function () {
				var checkboxes = document.querySelectorAll("input[name='users_to_update[]']");
				for (var i = 0; i < checkboxes.length; i++) {
					checkboxes[i].checked = this.checked;
				}
			});
		}

		// Batch Regrade All button
		$('#btn-regrade-all').on('click', function (e) {
			e.preventDefault();

			if (!confirm(ldAdvancedRegrader.i18n.confirmBatch)) {
				return;
			}

			$('#btn-update-selected, #btn-regrade-all').prop('disabled', true);
			$('#batch-progress-container').show();

			var totalItems     = parseInt(ldAdvancedRegrader.totalItems, 10);
			var quizId         = parseInt(ldAdvancedRegrader.quizId, 10);
			var groupId        = parseInt(ldAdvancedRegrader.groupId, 10);
			var searchQuery    = ldAdvancedRegrader.searchQuery;
			var updateCourse   = $('#update_course_progress').is(':checked') ? 1 : 0;

			function processBatch(offset) {
				$.post(ldAdvancedRegrader.ajaxUrl, {
					action: 'ld_regrade_batch_process',
					quiz_id: quizId,
					group_id: groupId,
					search_query: searchQuery,
					update_course_progress: updateCourse,
					offset: offset,
					nonce: ldAdvancedRegrader.nonce
				}, function (response) {
					if (response && response.success) {
						var newOffset = offset + response.data.processed;
						$('#batch-progress-bar').val(newOffset);
						$('#batch-progress-text').text(newOffset + ' / ' + totalItems);

						if (response.data.completed) {
							$('#batch-progress-text').text(ldAdvancedRegrader.i18n.completed);
							setTimeout(function () {
								location.reload();
							}, 1500);
						} else {
							processBatch(newOffset);
						}
					} else {
						alert(ldAdvancedRegrader.i18n.errorBatch);
						$('#btn-update-selected, #btn-regrade-all').prop('disabled', false);
					}
				}).fail(function () {
					alert(ldAdvancedRegrader.i18n.serverError);
					$('#btn-update-selected, #btn-regrade-all').prop('disabled', false);
				});
			}

			processBatch(0);
		});
	});
})(jQuery);
