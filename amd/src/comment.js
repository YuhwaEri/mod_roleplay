define(['jquery', 'core/modal_factory'], function($, ModalFactory) {
	var trigger = $('.create-modal');

	var init = function() {
		ModalFactory.create({
			// title: 'test title',
			body: '<p>test body content</p>'
			// footer: 'test footer content',
		}, trigger)
		.then(function(modal) {
			// modal.show();
		})
	}

	return {
		init: init
	}
});