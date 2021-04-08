#include <WiFi.h>
#include <HTTPClient.h>
#include "esp_wpa2.h"
#include <Arduino.h>
#include <Wire.h>
#include <Adafruit_BME280.h>

//wifi config
const char *ssid = "";
const char *username = "";
const char *password = "";
const char *dataURL = ""; //URL to homeStats.php

const int sampleDelay = 1000*60*10; //in ms

//sensor config
const char *s1Identifier = ""; //sensor name
/***END CONFIG***/

Adafruit_BME280 s1BME280Sensor;

bool s1 = true;

WiFiClient client;

typedef struct _data {
  float temperature;
  float humidity;
  float pressure;
} SensorData;

int counter=0;

void setup() {
  Serial.begin(115200);
  delay(5000);
  //Start the connection to wifi
  WiFi.disconnect(true);  //disconnect form wifi to set new wifi connection
  WiFi.mode(WIFI_STA); //init wifi mode
  //my wifi uses enterprise encryption if you don't you'll have to tinker with this
  esp_wifi_sta_wpa2_ent_set_identity((uint8_t *)username, strlen(username)); //provide identity
  esp_wifi_sta_wpa2_ent_set_username((uint8_t *)username, strlen(username)); //provide username --> identity and username is same
  esp_wifi_sta_wpa2_ent_set_password((uint8_t *)password, strlen(password)); //provide password
  esp_wpa2_config_t config = WPA2_CONFIG_INIT_DEFAULT(); //set config settings to default
  esp_wifi_sta_wpa2_ent_enable(&config); //set config settings to enable function
  WiFi.begin(ssid); //connect to wifi


  //detect/configure the first sensor
  if (!s1BME280Sensor.begin(BME280_ADDRESS_ALTERNATE)) {
    Serial.println("Error finding first sensor!");
    s1=false;
  }

  Serial.print("Connecting to network: ");
  Serial.println(ssid);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
    counter++;
    if(counter>=60){ //after 30 seconds timeout - reset board
      ESP.restart();
    }
  }
  Serial.println("");
  Serial.println("WiFi connected");
  Serial.println("IP address set: ");
  Serial.println(WiFi.localIP()); //print LAN IP


  Serial.println();
  Serial.println("Initialization complete.");
}

void loop() {
  SensorData data;
  if (s1) {
    Serial.print("[Sensor 1] ");
    readData(&s1BME280Sensor, &data);
    displayData(&data);
    sendData(&data, s1Identifier);
  }

  //sleep until next sample
  //ESP.deepSleep(sampleDelay*1000); //deepSleep is in microseconds, sampledelay is in milliseconds
  delay(sampleDelay);
}

void sendData(SensorData *data, const char *id) {
  HTTPClient http;
  char payload[100];
  sprintf(payload,"%s,%0.2f,%0.2f,%0.2f",id,data->temperature,data->humidity,data->pressure/1000);
  Serial.print("[Payload] ");
  Serial.println(payload);
  if (http.begin(client, dataURL)) {  // HTTP
    // start connection and send HTTP header
    int httpCode = http.POST(String(payload));

    // httpCode will be negative on error
    if (httpCode > 0) {
      // HTTP header has been send and Server response header has been handled
      Serial.printf("[HTTP] Code: %d\n", httpCode);

      // file found at server
      if (httpCode == HTTP_CODE_OK || httpCode == HTTP_CODE_MOVED_PERMANENTLY) {
        String result = http.getString();

        if (result.compareTo(payload) == 0) {
          Serial.print("[HTTP] Payload sent sucessfully");
        } else {
          Serial.print("[HTTP] Unexpected result: ");
          Serial.println(result);
        }

      }
    } else {
      Serial.printf("[HTTP] GET... failed, error: %s\n", http.errorToString(httpCode).c_str());
    }

    http.end();
  } else {
    Serial.printf("[HTTP} Unable to connect\n");
  }
}

void readData(Adafruit_BME280 *bme, SensorData *data) {
    data->temperature=bme->readTemperature();
    data->pressure=bme->readPressure();
    data->humidity=bme->readHumidity();
}

void displayData(SensorData *data) {
    Serial.print("T=");
    Serial.print(data->temperature);
    Serial.print(" *C");

    Serial.print(" P=");
    Serial.print(data->pressure);
    Serial.print(" Pa ");

    Serial.print(F("Humidity = "));
    Serial.print(data->humidity);
    Serial.println("% RH");

}
