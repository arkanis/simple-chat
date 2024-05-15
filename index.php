<?php

/**
 * Simple Chat v2.0.2 by Stephan Soller
 * http://arkanis.de/projects/simple-chat/
 */

// Name of the message buffer file. You have to create it manually with read and write permissions for the webserver.
$messages_buffer_file = "messages.json";
// Number of most recent messages kept in the buffer.
// Note that message list on clients only shows 1000 messages to avoid slowdown (see JavaScript code below).
$messages_buffer_size = 50;
// Disabled by default, set to true to enable. Appends each chat messages to a chatlog.txt text file.
// This log file is uncapped, so you have to clean it form time to time or it can get very large.
$enable_chatlog = false;

if ( isset($_POST["content"]) and isset($_POST["name"]) ) {
	// Create the message buffer file if it doesn't exist yet. That way we don't need a setup and it's writable since it
	// was created by the process executing PHP (usually the webserver).
	if ( ! file_exists($messages_buffer_file) )
		touch($messages_buffer_file);
	
	// Open, lock and read the message buffer file
	$buffer = fopen($messages_buffer_file, "r+b");
	flock($buffer, LOCK_EX);
	$buffer_data = stream_get_contents($buffer);
	
	// Append new message to the buffer data or start with a message id of 0 if the buffer is empty
	$messages = $buffer_data ? json_decode($buffer_data, true) : [];
	$next_id = (count($messages) > 0) ? $messages[count($messages) - 1]["id"] + 1 : 0;
	$messages[] = [ "id" => $next_id, "time" => time(), "name" => $_POST["name"], "content" => $_POST["content"] ];
	
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
	if ($enable_chatlog)
		file_put_contents("chatlog.txt", date("Y-m-d H:i:s") . "\t" . strtr($_POST["name"], "\t", " ") . "\t" . strtr($_POST["content"], "\t", " ") . "\n", FILE_APPEND);
	
	exit();
}

?>
<!DOCTYPE html>
<meta charset=utf-8>
<meta name=viewport content="initial-scale=1.0">
<meta name=author content="Stephan Soller">
<title>Simple Chat</title>
<script type=module>
	// Remove the "loading…" list entry
	document.querySelector("ul#messages > li").remove()
	
	document.querySelector("form").addEventListener("submit", async event => {
		const form = event.target
		const name =  form.name.value
		const content =  form.content.value
		
		// Prevent the browsers default action (send form data and show the result page). We just want to send the message without reloading the page.
		event.preventDefault()
		
		// Only send a new message if it's not empty (also it's ok for the server we don't need to send senseless messages)
		if (name == "" || content == "")
			return
		
		// Append a "pending" message (a message not yet confirmed from the server) as soon as the POST request is send. The
		// textContent property automatically escapes HTML so no one can harm the client by injecting JavaSript code.
		await fetch(form.action, { method: "POST", body: new URLSearchParams({name, content}) })
		const messageList = document.querySelector("ul#messages")
		const messageElement = messageList.querySelector("template").content.cloneNode(true)
			messageElement.querySelector("small").textContent = name
			messageElement.querySelector("span").textContent = content
		messageList.append(messageElement)
		
		messageList.scrollTop = messageList.scrollHeight
		form.content.value = ""
		form.content.focus()
	})
	
	// Poll-function that looks for new messages
	async function poll_for_new_messages() {
		// We want the browser to revalidate the cached messages.json file every time. That is it should send a
		// conditional request with an If-Modified-Since header. This is the default behaviour in Firefox 115.
		// In Chrome 114 it's not. It just uses the cached response without revalidation, thus missing new messages.
		// Hence we explicitly tell fetch to revalidate via a conditional request. Because naming things is hard the
		// option to do just that is { cache: "no-cache" }. See https://javascript.info/fetch-api#cache
		// or https://developer.mozilla.org/en-US/docs/Web/HTTP/Caching#force_revalidation
		const response = await fetch("messages.json", { cache: "no-cache" })
		
		// Do nothing if messages.json wasn't found (doesn't exist yet probably)
		if (!response.ok)
			return
		
		const messages = await response.json()
		const messageList = document.querySelector("ul#messages")
		const messageTemplate = messageList.querySelector("template").content.querySelector("li")
		
		// Determine if we should scroll the message list down to the bottom once we inserted all new messages.
		// Only do that if the user already is almost at the bottom (50px at max from it). Otherwise it gets really
		// annoying when the list scrolls down every 2 seconds while you want to read old messages further up. Check the
		// pixel distance before changing the message list. Otherwise the check gets thrown off by removed or new messages.
		const pixelDistanceFromListeBottom = messageList.scrollHeight - messageList.scrollTop - messageList.clientHeight
		const scrollToBottom = (pixelDistanceFromListeBottom < 50)
		
		// Remove the pending messages from the list (they are replaced by the ones from the server later)
		for (const li of messageList.querySelectorAll("li.pending"))
			li.remove()
		
		// Get the ID of the last inserted message or start with -1 (so the first message from the server with 0 will
		// automatically be shown).
		const lastMessageId = parseInt(messageList.dataset.lastMessageId ?? "-1")
		
		// Add a list entry for every incomming message, but only if we not already inserted it (hence the check for
		// the newer ID than the last inserted message).
		for (const msg of messages) {
			if (msg.id > lastMessageId) {
				const date = new Date(msg.time * 1000);
				const messageElement = messageTemplate.cloneNode(true)
					messageElement.classList.remove("pending")
					messageElement.querySelector("small").textContent = Intl.DateTimeFormat(undefined, { dateStyle: "medium", timeStyle: "short" }).format(date) + ": " + msg.name
					messageElement.querySelector("span").textContent = msg.content
				messageList.append(messageElement)
				messageList.dataset.lastMessageId = msg.id
			}
		}
		
		// Remove all but the last 1000 messages in the list to prevent browser slowdown with extremely large lists
		for (const li of Array.from(messageList.querySelectorAll("li")).slice(0, -1000))
			li.remove()
		
		// Finally scroll down to the newes messages
		if (scrollToBottom)
			messageList.scrollTop = messageList.scrollHeight - messageList.clientHeight
	}
	
	// Kick of the poll function and repeat it every two seconds
	poll_for_new_messages()
	setInterval(poll_for_new_messages, 2000)
</script>
<style>
	html { margin: 0em; padding: 0; }
	body { height: 100vh; box-sizing: border-box; margin: 0; padding: 2em;
		font-family: sans-serif; font-size: medium; color: #333;
		display: flex; flex-direction: column; gap: 1em; }
	body > h1 { flex: 0 0 auto; }
	body > ul#messages { flex: 1 1 auto; }
	body > form { flex: 0 0 auto; }
	
	h1 { margin: 0; padding: 0; font-size: 2em; }
	
	ul#messages { overflow: auto; margin: 0; padding: 0 3px; list-style: none; border: 1px solid gray; }
	ul#messages li { margin: 0.35em 0; padding: 0; }
	ul#messages li small { display: block; font-size: 0.59em; color: gray; }
	ul#messages li.pending { color: #aaa; }
	
	form { font-size: 1em; margin: 0; padding: 0; }
	form p { margin: 0; padding: 0; display: flex; gap: 0.5em; }
	form p input { font-size: 1em; min-width: 0; }
	form p input[name=name] { flex: 0 1 10em; }
	form p input[name=content] { flex: 1 1 auto; }
	form p button {}
	
	h1, ul#messages, form { width: 100%; max-width: 40rem; box-sizing: border-box; margin: 0 auto; }
</style>

<h1>Simple Chat</h1>

<ul id=messages>
	<li>loading…</li>
	<template>
		<li class=pending>
			<small>…</small>
			<span>…</span>
		</li>
	</template>
</ul>

<form method=post action="<?= htmlentities($_SERVER["PHP_SELF"], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, "UTF-8") ?>">
	<p>
		<input type=text name=name placeholder="Name" value="Anonymous">
		<input type=text name=content placeholder="Message" autofocus>
		<button>Send</button>
	</p>
</form>
