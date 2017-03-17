jQuery(function($) {
	$('.notice[data-ye-dismiss-nonce]').on('click', '.notice-dismiss', function() {
		var $notice = $(this).closest('.notice'),
			nonce = $notice.data('ye-dismiss-nonce'),
			id = $notice.attr('id');

		$.post(
			ajaxurl,
			{
				"action": 'ye_dismiss-' + id,
				"_ajax_nonce": nonce,
				"id": id
			}
		);
	});
});