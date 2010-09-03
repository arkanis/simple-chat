#!/bin/bash

# Create the empty message buffer file and give the webserver read and write permission.
# The chmod command below gives every one read and write access (because I don't know
# your setup) but if possible only give the webserver read and write access.
touch messages.json
chmod ugo=rw messages.json