jQuery(function($) {
	$('.notice[data-ye-dismiss-nonce]').on('click', '.notice-dismiss', function() {
		var $notice = $(this).closest('.notice'),
			nonce = $notice.data('ye-dismiss-nonce'),
			id = $notice.attr('id');

		$.post(
			ajaxurl,
			{
				"action": 'ye_v1_dismiss-' + id,
				"_ajax_nonce": nonce,
				"id": id,
				"notice-data": $notice.attr('data-ye-notice-data'), //Use $.attr() because it doesn't parse JSON.
				"signature": $notice.data('ye-signature')
			}
		);
	});
});