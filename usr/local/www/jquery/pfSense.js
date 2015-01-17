$(function() {
	// Enable popovers globally
	$('[data-toggle="popover"]').popover()

	switch ($(document.body).attr('id')) {
		case 'index':
			// Hide configuration button for panels without configuration
			$('.container .panel-heading a.config').each(function (idx, el){
				var config = $(el).parents('.panel').children('.panel-footer');
				if (config.length == 1)
					$(el).removeClass('hidden');
			});

			// Initial state & toggle icons of collapsed panel
			$('.container .panel-heading a[data-toggle="collapse"] i').each(function (idx, el){
				var body = $(el).parents('.panel').children('.panel-body'), isOpen = body.hasClass('in');
				$(el).toggleClass('icon-plus-sign', !isOpen);
				$(el).toggleClass('icon-minus-sign', isOpen);

				body.on('show.bs.collapse', function(){ $(el).toggleClass('icon-minus-sign', true); $(el).toggleClass('icon-plus-sign', false); });
				body.on('hide.bs.collapse', function(){ $(el).toggleClass('icon-minus-sign', false); $(el).toggleClass('icon-plus-sign', true); });
			});

			// Make panels destroyable
			$('.container .panel-heading a[data-toggle="close"] i').each(function (idx, el){
				$(el).on('click', function(e){
					$(el).parents('.panel').collapse('hide');
				})
			});

			// Make panels sortable
			$('.container .col-md-6').sortable({
				handle: '.panel-heading',
				cursor: 'grabbing',
				connectWith: '.container .col-md-6',
				update: function(event, ui) {
					var isOpen, sequence = '';

					$('.container .col-md-6').each(function(idx, col){
						$('.panel', col).each(function(idx, widget){
							isOpen = $('.panel-body', widget).hasClass('in');

							sequence += widget.id.split('-')[1] +':'+ col.id.split('-')[1] +':'+ (isOpen ? 'open' : 'close') +','; 
						});
					});

					$('#widgetSequence').removeClass('hidden');
					$('input[name=sequence]', $('#widgetSequence'))[0].value = sequence;
				}
			});
		break;
	}
});