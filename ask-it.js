// remap jQuery to $
(function($){
	
	$(document).ready(function(){
		
		$('.askit-q a.askit-toggle').click(function(){
			$(this).siblings('.askit-q-inside').toggle();
			
			return false;
		});
		
		$('.askit-q .askit-q-controls a').click(function(){
			var action = $(this).attr('href'),
				id = $(this).attr('data-id'),
				message = ' ';
				
			$.ajax({
				url: action,
				type: 'post',
				data: 'askit=true&id=' + id
			});
			
			if($(this).hasClass('askit-make-public')){
				//$(this).parents('.askit-q').find('.askit-response').html('<div class="updated"><p>This question has been made public!</p></div>');
				$(this).parent().prepend('<span class="askit-public">Public</span>');
				$(this).remove();
			} else if($(this).hasClass('askit-remove')){
				$(this).parents('.askit-q').next('.askit-sep').remove();
				$(this).parents('.askit-q').remove();
			}
						
			return false;
		});
	
		$('#askit-question').submit(function(e){
			e.preventDefault();
			
			if( ( $('#askit-title').val() == "" ) || ( $('#askit-excerpt').val() == "" ) )
				return false;
			
			$(this).find('.askit-publishing-action img').css('visibility', 'visible');
			$(this).find('input[type="reset"], input[type="submit"]').attr('disabled', 'disabled');
			
			var	title = $('#askit-title').val(),
				question = $('#askit-question').val(),				
				askitAction = $('#askit-action').val(),
				asker = $('#askit-asker').val(),
				askerEmail = $('#askit-asker-email').val();
				
			$.ajax({
				url: askitAction,
				type: 'post',
				data: 'askitQuestionAsked=true&title=' + title + '&asker=' + asker + '&askerEmail=' + askerEmail + '&question=' + question
			});
			
			var action = $('#askit-question').attr('action');
				
			$('#askit-question p.askit-response').css('visibility', 'hidden').load( action, $(this).serializeArray(), function(){
				$('#askit-question p.askit-response p.textright, #askit-question p.askit-response form').remove();
				$(this).find('.updated p').html('Question asked. You will be notified via email when it has been answered.');
				$(this).css('visibility', 'visible');
				$('#askit-question').find('.askit-publishing-action img').css('visibility', 'hidden');
				$('#askit-question').find('input[type="reset"], input[type="submit"]').removeAttr('disabled');
				$('#askit-question').find('input[type="reset"]').trigger('click');
			});
			
			return false;
		});
		
		$('.askit-nav').click(function(){
			if($(this).hasClass('current'))
				return false;
			
			$('.askit-settings-pane').slideUp();	
			$( $(this).attr('href') ).slideDown();
			$('.askit-nav.current').removeClass('current');
			$(this).addClass('current');
			
			return false;
		});
		
		$('#notification-question-asked-email').change(function(){
			if($(this).is(':checked'))
				$('#askit-question-asked-email-notification').slideDown();
			else
				$('#askit-question-asked-email-notification').slideUp();
		});
		
		$('#notification-question-asked-text').change(function(){
			if($(this).is(':checked'))
				$('#askit-question-asked-text-notification').slideDown();
			else
				$('#askit-question-asked-text-notification').slideUp();
		});
		
		$('#notification-question-answered-email').change(function(){
			if($(this).is(':checked'))
				$('#askit-question-answered-email-notification').slideDown();
			else
				$('#askit-question-answered-email-notification').slideUp();
		});
	
	});
	
})(window.jQuery);
