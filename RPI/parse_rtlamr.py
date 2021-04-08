#!/usr/bin/env python3
import sys,json
import urllib.request
#usage rtlamr -json <...> | this_script.py

#config
url="" #URL of homeStats script
#sensor ids/names
meters = {
    "1111":"water",
    "2222":"gas"
}

while True:
    line = sys.stdin.readline()
    if not line:
        break
    msg = json.loads(line.strip())
    meter = msg['Message']['EndpointID']
    value = msg['Message']['Consumption']
    data = "%s,%d"%(meters[str(meter)],value)
    payload = data.encode("ascii")
    print (payload)
    req = urllib.request.Request(url)
    result = urllib.request.urlopen(req, payload)


