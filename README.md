Simple Chat
===========

In ~20 lines of PHP and ~50 lines of plain JavaScript. No dependencies and no database needed.

Meant for small chats. Not good for chats with a lot of users since every user looks for new messages every 2 seconds.

[Demo](http://arkanis.de/projects/simple-chat/)


Requirements
------------

- A webspace that supports PHP
- A directory in that webspace that is writable by the webserver. You might have to mark the directory writable in your admin interface.


Installation
------------

- Copy `index.php` into the directory
- Done

The chat will create a `messages.json` file the first time someone writes a message. That's why the directory needs to be writable.
Alternatively you can leave the directory read-only and create an empty writable `messages.json`.


### Optional: Enable the chatlog

By default the chat only remembers the last 50 messages. That way the chat cleans itself up automatically.

If you want a full history of all posted messages have to edit `index.php`. Change the line `$enable_chatlog = false;` to `$enable_chatlog = true;`.

With that every message gets appended to `chatlog.txt`. This is _not_ shown in the chat, it's just a log for yourself. It will grow with every posted message, so remember to clean it up if it grows to large.


Feedback and comments
---------------------

If you have questions about this chat or want to share some thoughts or ideas, feel free to open an issue or post a
comment on the [blog post of the project](http://arkanis.de/weblog/2010-09-04-a-simple-chat-in-about-50-lines-of-code).
You can also send me a mail if you prefer that (see [here](http://arkanis.de/profile)).


How the chat works
------------------

The idea is simple:

- Append new messages to a file on the server, but only keep the last few messages.
- Every client requests this file every two seconds and displays all new messages inside it (this approach is called "polling").

These polling requests are relatively cheap thanks to HTTP caching. But with a lot of users in the chat this adds up quickly and can get quite inefficient.

More complex chats can do a lot better but also require fancy stuff like WebSockets, way more code and usually a more complex setup.
For simple usecases with just a few users a simple chat gets the job done easily.

This simple design leads to a chat that doesn't need a database nor a large server or infrastructure. Just a small
bunch of lines you can drop into your project and modify or extend as needed. It's so simple that it shouldn't be a
problem to extend or adopt the code for your own purpose (e.g. multiple chat rooms, usage as message API, fancy styling,
etc.).

If you want to know more take a look at the [detailed explanation of the design and implementation](http://arkanis.de/weblog/2010-09-05-simple-chat-the-details) of the first version.