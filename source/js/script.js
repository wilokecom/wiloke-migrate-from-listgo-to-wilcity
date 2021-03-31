(function ($) {
	'use strict';
	
	$(document).ready(function () {
		$('.wilcity-import-listgo').on('submit', function (event) {
			event.preventDefault();
			var $this = $(this);
			$this.addClass('loading');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: $this.data('ajax'),
					nonce: $('#wilcity_nonce_fields').val(),
					data: $this.find('.data').val()
				},
				success: function (response) {
					if ( response.success ){
						alert(response.data.msg);
					}
					$this.removeClass('loading');
				}
			})
		})
	})
	
})(jQuery);