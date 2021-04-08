<?php

//this script is intended to be run on the web server (where homeStats.data live)

# Put the correct URL for your station here
# see https://dd.weather.gc.ca/citypage_weather/docs/site_list_towns_en.csv for a list of stations to read from
# This is Kingston
$stationURL="https://dd.weather.gc.ca/citypage_weather/xml/ON/s0000531_e.xml";
$pathToData="/path/to/homeStats.data"; //path to the homestats datafile
$sensorName="weather.g531";

#we might be sleeping for minutes, ignore a timeout just in case
set_time_limit(0);
class JsonSerializer extends SimpleXmlElement implements JsonSerializable
{
    /**
     * SimpleXMLElement JSON serialization
     *
     * @return null|string
     *
     * @link http://php.net/JsonSerializable.jsonSerialize
     * @see JsonSerializable::jsonSerialize
     */
    function jsonSerialize() {
        $array=[];
        //handle the child element(s)
        if (count($this)) {
            // serialize children if there are children
            foreach ($this as $tag => $child) {
                //if the key already exists, this is going to be an array
                if (array_key_exists($tag,$array)) {
                    //if it isn't already an array, make it one
                    if(!is_array($array[$tag])) $array[$tag]=[$array[$tag]];
                    //add the element to the array
                    $array[$tag][] = $child;
                } else {
                    //just a single value, so set it
                    $array[$tag] = $child;
                }
            }
        } else {
            $array["value"] = (string) $this;
        }
        // serialize attributes
        foreach ($this->attributes() as $name => $value) {
            $array["_$name"] = (string) $value;
        }
        return $array;
    }
}
//the data service appears to update sporadically, so check every 4 minutes until we've reached the end of our cron duration (30 minute cron time)
$count = 0;
$sleep=240;
$cron=1800;
$oldTime = time() - $cron;
do {
        $str = file_get_contents($stationURL);
        $xxx = new JsonSerializer($str);
        $json= json_encode($xxx, JSON_PRETTY_PRINT);
        $data = json_decode($json);
        foreach ($data->dateTime as $dt) {
                if ($dt->_zone == "UTC") continue;
                $ts = mktime($dt->hour->value,$dt->minute->value,0,$dt->month->value,$dt->day->value,$dt->year->value);
        }
        #we have a new reading
        if ($ts > $oldTime) break;
        $oldTime=$ts;
        $count++;
        if($count*$sleep > $cron) exit();
        sleep($sleep);
} while (true);

$cc = $data->currentConditions;
$temp = $cc->temperature->value;
$rh = $cc->relativeHumidity->value; #WHY THE FUCK IS THIS EMPTY?!? (wasted 2 hrs of my life...)
$pressure = $cc->pressure->value;
#Put the name here
$dataArr = [$sensorName,$temp,$rh,$pressure];
$dataArr[]='127.0.0.1';
$dataArr[]=$ts;
file_put_contents($pathToData,implode(",",$dataArr)."\n",FILE_APPEND|LOCK_EX);
