$(function() {
	switch ($(document.body).attr('id'))
	{
		case 'index':
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