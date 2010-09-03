/**
 * $('ul#messages').chat()
 * 
 * $('form').chat();
 * $('form').chat({message_list: 'ul.messages', messages_file: 'messages.json', message_limit: 100, poll_interval: 2});
 * $('form').bind('send', function(){
 * 	// input validation
 * 	// send post request
 * 	// insert pending message
 * });
 * $('form').bind('poll', function(){
 * 	// remove pending messages
 * 	// fire receive for each incomming message
 * 	// remove to old messages from the top of the message list
 * });
 * $('form').bind('receive', function(){
 * 	// insert message into the message list
 * });
 * 
 * form submit → send → insert pending message
 * poll → receive → compact list
 */

jQuery.fn.simpleChat = function(options){
	settings = jQuery.extend({
		list: 'ul#messages',
		list_limit: 100,
		poll_file: 'messages.json',
		poll_interval: 2,
		msg_name_selector: "input[name='name']",
		msg_text_selector: "input[name='message']",
		message: function(msg){
			
		},
		pending: function(name, text){
			return $('<li class="pending" />').text(message).prepend($('<small />').text(name));
		}
	}, options);
	
	this.each(function(){
		$('form').submit(function(){
			var form = $(this);
			var name =  form.find("input[name='name']").val();
			var message =  form.find("input[name='message']").val();
			if (name == '' || message == '')
				return false;
			
			$.post(form.attr('action'), {'name': name, 'message': message}, function(data, status){
				$('<li class="pending" />').text(message).prepend($('<small />').text(name)).appendTo('ul#messages');
				$('ul#messages').scrollTop( $('ul#messages').get(0).scrollHeight );
				form.find("input[name='message']").val('').focus();
			});
			return false;
		});
		
		var poll_for_new_messages = function(){
			$.ajax({url: 'messages.json', dataType: 'json', ifModified: true, timeout: 2000, success: function(messages, status){
				if (!messages)
					return;
				
				$('ul#messages > li.pending').remove();
				var last_message_id = $('ul#messages').data('last_message_id');
				if (last_message_id == null)
					last_message_id = -1;
				
				for(var i = 0; i < messages.length; i++)
				{
					var msg = messages[i];
					if (msg.id > last_message_id)
					{
						var date = new Date(msg.time * 1000);
						$('<li/>').text(msg.message).
							prepend( $('<small />').text(date.getHours() + ':' + date.getMinutes() + ':' + date.getSeconds() + ' ' + msg.name) ).
							appendTo('ul#messages');
						$('ul#messages').data('last_message_id', msg.id);
					}
				}
				
				$('ul#messages > li').slice(0, -50).remove();
				$('ul#messages').scrollTop( $('ul#messages').get(0).scrollHeight );
			}});
		};
		
		poll_for_new_messages();
		setInterval(poll_for_new_messages, 2000);
	});
};