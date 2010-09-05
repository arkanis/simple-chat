#!/usr/bin/ruby

# A small script to put some load on the simple chat
# By Stephan Soller

require 'net/http'
require 'thwait'

num_clients = 150

def output_response(text, response)
	if response.code.to_i == 200
		print text
	else
		puts 'X'
		puts response.body
	end
	$stdout.flush
end

def simulate_client(name)
	url = URI.parse('http://localhost/')
	Net::HTTP.start url.host, url.port do |con|
		while true do
			response = con.get('/simple-chat/messages.json')
			output_response('.', response)
			
			if rand <= 0.25
				response = con.post('/simple-chat/chat.php', "name=#{URI.escape(name)}&content=#{URI.escape(rand.inspect + ' from ' + name)}")
				output_response('p', response)
			end
			
			sleep 2
		end
	end
end

puts "Starting simulated clients… press ctrl+c to stop"
tracker = ThreadsWait.new
num_clients.times do |i|
	client = Thread.new(i){|id| simulate_client "ruby#{id}"}
	tracker.join_nowait client
end
tracker.all_waits