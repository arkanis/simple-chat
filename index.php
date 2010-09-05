<?php

// Name of the message buffer file. You have to create it manually with read and write permissons for the webserver.
$messages_buffer_file = 'messages.json';
// Number of most recent messages kept in the buffer
$messages_buffer_size = 10;

if ( isset($_POST['content']) and isset($_POST['name']) )
{
	// Open, lock and read the message buffer file
	$buffer = fopen($messages_buffer_file, 'r+b');
	flock($buffer, LOCK_EX);
	$buffer_data = stream_get_contents($buffer);
	
	// Append new message to the buffer data or start with a message id of 0 if the buffer is empty
	$messages = $buffer_data ? json_decode($buffer_data, true) : array();
	$next_id = (count($messages) > 0) ? $messages[count($messages) - 1]['id'] + 1 : 0;
	$messages[] = array('id' => $next_id, 'time' => time(), 'name' => $_POST['name'], 'content' => $_POST['content']);
	
	// Remove old messages if necessary to keep the buffer size
	if (count($messages) > $messages_buffer_size)
		$messages = array_slice($messages, count($messages) - $messages_buffer_size);
	
	// Rewrite and unlock the message file
	ftruncate($buffer, 0);
	rewind($buffer);
	fwrite($buffer, json_encode($messages));
	flock($buffer, LOCK_UN);
	fclose($buffer);
	
	// Optional: Append message to log file (file appends are atomic)
	//file_put_contents('chatlog.txt', strftime('%F %T') . "\t" . strtr($_POST['name'], "\t", ' ') . "\t" . strtr($_POST['content'], "\t", ' ') . "\n", FILE_APPEND);
	
	exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Simple Chat - Arkanis Development</title>
	<link href="/weblog.xml" rel="alternate" title="Arkanis Development weblog" type="application/atom+xml" />
	<link href="/weblog/2010-09-04-a-simple-chat-in-about-50-lines-of-code.xml" rel="alternate" title="Comments newsfeed for a simple chat in about 50 lines of code" type="application/atom+xml" />
	<link rel="stylesheet" type="text/css" href="/styles/blue-frame/style.css" />
	<!--[if lte IE 8]><script src="/styles/modern-ambience/html5.js" type="text/javascript"></script><![endif]-->
	<script src="jquery-1.4.2.min.js"></script>
	<script type="text/javascript">
		// <![CDATA[
		$(document).ready(function(){
			// Remove the "loading…" list entry
			$('ul#messages > li').remove();
			
			$('form').submit(function(){
				var form = $(this);
				var name =  form.find("input[name='name']").val();
				var content =  form.find("input[name='content']").val();
				
				// Only send a new message if it's not empty (also it's ok for the server we don't need to send senseless messages)
				if (name == '' || content == '')
					return false;
				
				// Append a "pending" message (not yet confirmed from the server) as soon as the POST request is finished. The
				// text() method automatically escapes HTML so no one can harm the client.
				$.post(form.attr('action'), {'name': name, 'content': content}, function(data, status){
					$('<li class="pending" />').text(content).prepend($('<small />').text(name)).appendTo('ul#messages');
					$('ul#messages').scrollTop( $('ul#messages').get(0).scrollHeight );
					form.find("input[name='content']").val('').focus();
				});
				return false;
			});
			
			// Poll-function that looks for new messages
			var poll_for_new_messages = function(){
				$.ajax({url: 'messages.json', dataType: 'json', ifModified: true, timeout: 2000, success: function(messages, status){
					// Skip all responses with unmodified data
					if (!messages)
						return;
					
					// Remove the pending messages from the list (they are replaced by the ones from the server later)
					$('ul#messages > li.pending').remove();
					
					// Get the ID of the last inserted message or start with -1 (so the first message from the server with 0 will
					// automatically be shown).
					var last_message_id = $('ul#messages').data('last_message_id');
					if (last_message_id == null)
						last_message_id = -1;
					
					// Add a list entry for every incomming message, but only if we not already inserted it (hence the check for
					// the newer ID than the last inserted message).
					for(var i = 0; i < messages.length; i++)
					{
						var msg = messages[i];
						if (msg.id > last_message_id)
						{
							var date = new Date(msg.time * 1000);
							$('<li/>').text(msg.content).
								prepend( $('<small />').text(date.getHours() + ':' + date.getMinutes() + ':' + date.getSeconds() + ' ' + msg.name) ).
								appendTo('ul#messages');
							$('ul#messages').data('last_message_id', msg.id);
						}
					}
					
					// Remove all but the last 50 messages in the list to prevent browser slowdown with extremely large lists
					// and finally scroll down to the newes message.
					$('ul#messages > li').slice(0, -50).remove();
					$('ul#messages').scrollTop( $('ul#messages').get(0).scrollHeight );
				}});
			};
			
			// Kick of the poll function and repeat it every two seconds
			poll_for_new_messages();
			setInterval(poll_for_new_messages, 2000);
		});
		// ]]>
	</script>
	<style type="text/css">
		/*
		html { margin: 0em; padding: 0; }
		body { margin: 2em; padding: 0; font-family: sans-serif; font-size: medium; color: #333; }
		h1 { margin: 0; padding: 0; font-size: 2em; }
		p.subtitle { margin: 0; padding: 0 0 0 0.125em; font-size: 0.77em; color: gray; }
		*/
		
		ul#messages { overflow: auto; height: 15em; margin: 1em 0; padding: 0 3px; list-style: none; background-color: #f7f7f7; border: 1px solid #ccc; border-radius: 5px; box-shadow: inset 0 0 5px #ccc; }
		ul#messages li { margin: 0.35em 0; padding: 0; }
		ul#messages li small { display: block; font-size: 0.59em; color: gray; }
		ul#messages li.pending { color: #aaa; }
		
		ul#messages {
			-moz-border-radius: 5px;
			-webkit-border-radius: 5px;
			-o-box-shadow: inset 0 0 5px #ccc;
			-moz-box-shadow: inset 0 0 5px #ccc;
			-webkit-box-shadow: inset 0 0 5px #ccc;
		}
		
		form { font-size: 1em; margin: 1em 0; padding: 0; }
		form p { position: relative; margin: 0.5em 0; padding: 0; }
		form p input { font-size: 1em; }
		form p input#name { width: 10em; }
		form p button { position: absolute; top: 0; right: -0.5em; }
		
		ul#messages, form p, input#content { width: 40em; }
		
		pre { font-size: 0.77em; }
	</style>
	<meta name="author" content="Stephan Soller" />
</head>
<body>

<header>
	<h1><a href="/">Arkanis Development</a></h1>
	<ul>
		<li><a href="/projects">Projects</a></li>
		<li><a href="/projects/simple-chat/">Simple Chat</a></li>
	</ul>
</header>


<section>
	<header>
		<h2>Simple Chat</h2>
		<p>With about 20 lines of PHP and about 40 lines of JavaScript.</p>
		<p>By Stephan Soller &lt;<a href="&#x6d;&#x61;&#x69;&#108;&#116;&#111;&#x3a;&#115;&#116;&#101;p&#x68;&#x61;&#110;&#46;&#x73;&#111;l&#x6c;&#101;&#x72;&#x40;&#104;&#x65;&#108;&#x69;&#x6f;&#110;&#119;&#101;&#x62;&#46;&#100;&#x65;">&#115;&#116;&#101;p&#x68;&#x61;&#110;&#46;&#x73;&#111;l&#x6c;&#101;&#x72;&#x40;&#104;&#x65;&#108;&#x69;&#x6f;&#110;&#119;&#101;&#x62;&#46;&#100;&#x65;</a>&gt;, released under the <a href="#license">MIT-License</a>.</p>
	</header>
	<nav>
		<ul>
			<li><a href="#demo">Demo</a></li>
			<li><a href="example.php">Striped down example</a></li>
			<li><a href="#idea">The basic idea</a></li>
			<li><a href="/weblog/2010-09-05-simple-chat-the-details">The details</a></li>
			<li><a href="example.php#source">Some source code</a></li>
			<li><a href="#download">Download</a></li>
			<li><a href="#comments">Comments</a></li>
			<li><a href="#license">License</a></li>
		</ul>
	</nav>
	
	<p>If you're searching for a small simplistic chat but don't want some Flash, Java or other heavy-weight stuff you can stop
	searching. A PHP capable webserver and three files are all you need. And no, you don't need a database.</p>
	
	<p>This code here is inteded as a base for you to do your own stuff. However if it already is exactly what you want feel free
	to just copy it.</p>
	
	<ul>
		<li>Paste the PHP and JavaScript of the <a href="example.php#source">source code</a> into your page or take
		the <code>example.php</code> file from the <a href="#download">download</a>.</li>
		<li>Include jQuery into your page (v1.4.2 is used here).</li>
		<li>Create a <code>messages.json</code> file and make sure the webserver can read and write it.</li>
	</ul>
	
	<p>That's it. <span class="smiley smile">:)</span></p>
	
	
	<h3 id="demo">Demo</h3>
	
	<ul id="messages">
		<li>loading…</li>
	</ul>
	
	<form action="<?= $_SERVER['PHP_SELF']; ?>" method="post">
		<p>
			<input type="text" name="content" id="content" />
		</p>
		<p>
			<label>
				Name:
				<input type="text" name="name" id="name" value="Anonymous" />
			</label>
			<button type="submit">Send</button>
		</p>
	</form>
	
	<p>Take a look at the <a href="example.php#source">source code of <code>example.php</code></a> (it also
	shows the PHP code). It's a stripped down version of the chat so there are no fancy styles or documentation (except
	some comments) to distract you.</p>
	
	<h3 id="idea">The basic idea</h3>
	
	<ul>
		<li>Append new messages to a text file on the server, but only keep the last 10 or so messages. Optionally
		append the message to a chat log, too.</li>
		<li>Every client requests this text file every two seconds and displays all new messages inside it. These
		polling requests are very cheap (HTTP caching helps us).</li>
	</ul>
	
	<p>This simple design leads to a chat that doesn't need a database nor a large server or infrastructure. Just a small
	bunch of lines you can drop into your project and modify or extend as needed. It's so simple that it shouldn't be a
	problem to extend or adopt the code for your own purpose (e.g. multiple chat rooms, usage as message API, fancy
	styling, etc.).</p>
	
	<p>If you want to know more take a look at the <a href="/weblog/2010-09-05-simple-chat-the-details">detailed
	explanation of the design and implementation</a> of this chat.</p>
	
	
	<h3 id="download">Download</h3>
	
	<p>This archive contains everything you need for this chat: <a href="simple-chat-v1.0.0.zip">simple-chat-v1.0.0.zip</a>.</p>
	
	<p>It's the <a href="example.php">stripped down chat example</a> (<code>example.php</code>) as well as
	jQuery (<code>jquery-1.4.2.min.js</code>) and a shell script to create an empty file that's writeable by everyone
	(<code>setup.sh</code>, just in case…).</p>
	
	
	<h3 id="comments">Comments</h3>
	
	<p>If you have questions about this chat or want to share some thoughts or ideas, please post a comment on the
	<a href="http://arkanis.de/weblog/2010-09-04-a-simple-chat-in-about-50-lines-of-code">blog post of this project</a>.
	You can also send me a mail if you prefere that.</p>
	
	
	<h3 id="license">The MIT License</h3>
	
	<p>Copyright (c) 2010 Stephan Soller &lt;<a href="&#x6d;&#x61;&#x69;&#108;&#116;&#111;&#x3a;&#115;&#116;&#101;p&#x68;&#x61;&#110;&#46;&#x73;&#111;l&#x6c;&#101;&#x72;&#x40;&#104;&#x65;&#108;&#x69;&#x6f;&#110;&#119;&#101;&#x62;&#46;&#100;&#x65;">&#115;&#116;&#101;p&#x68;&#x61;&#110;&#46;&#x73;&#111;l&#x6c;&#101;&#x72;&#x40;&#104;&#x65;&#108;&#x69;&#x6f;&#110;&#119;&#101;&#x62;&#46;&#100;&#x65;</a>&gt;.</p>
	
	<p>Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files
	(the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish,
	distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the
	following conditions:</p>
	
	<p>The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.</p>
	
	<p>THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED
	TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
	THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF
	CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
	DEALINGS IN THE SOFTWARE.</p>
	
</section>

<footer>
	Design, code and content from <a href="http://arkanis.de/">Arkanis Development</a>.<br />
	<a href="http://www.w3.org/TR/html5/">HTML5</a>,
	<a href="http://www.w3.org/Style/CSS/current-work#CSS3">CSS3</a> and
	<a href="http://tools.ietf.org/html/rfc4287">Atom 1.0</a>
	in action.
</footer>

</body>
</html>