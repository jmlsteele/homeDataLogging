#!/usr/bin/env python3
import bme280
import smbus2
import urllib.request

#configuration
port = 1 # GPIO pins 3 and 5
address = 0x76 # Adafruit BME280 address. Other BME280s mcy be differenp
url = "" #URL of homeStats script
sensorName = ""  #sensor NAme

#read the sensor data
bus = smbus2.SMBus(port)
bme280.load_calibration_params(bus,address)
bme280_data = bme280.sample(bus,address)
humidity  = bme280_data.humidity
pressure  = bme280_data.pressure
temperature = bme280_data.temperature

#create the payload
data = "%s,%0.2f,%02.f,%0.2f"%(sensorName,temperature,humidity,pressure/10)
payload = data.encode("ascii")
#print (payload)
#send the request
req = urllib.request.Request(url)
result = urllib.request.urlopen(req, payload)

