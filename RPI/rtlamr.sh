#!/bin/bash

#start rtl_tcp if it isn't running
if ! pgrep -x "rtl_tcp" > /dev/null
then
        echo -n "Starting rtl_tcp..."
        screen -dmS rtl_tcp /usr/local/bin/rtl_tcp
        sleep 10
        echo " Running"
fi
echo "Starting rtlamr..."
#do the actual meter reading/sending of data
cd ~
#NOTE: replace 1111,2222 with your list of sensor IDs
./go/bin/rtlamr -duration 8m -msgtype scm+ -unique -format=json -symbollength 8 -filterid 1111,2222 | ./parse_rtlamr.py
echo "Done!"

