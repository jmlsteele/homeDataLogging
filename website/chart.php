<?php
define("TYPE_TEMP","temperature");
define("TYPE_HUM","humidity");
define("TYPE_ABSHUM","abshum");
define("TYPE_PRESSURE","pressure");
define("TYPE_WATER","water");
define("TYPE_GAS","gas");

$application=$_GET['application']?:null;
if ($application) {
    switch($application) {
        case 'furnace':
            $left=TYPE_TEMP;
            $leftSensor=['houseInside','houseFurnaceRoom','weather.gc531'];
            $right=TYPE_GAS;
            $rightSensor['gas'];
            break;
        case 'freezer':
            $left=TYPE_TEMP;
            $leftSensor=['freezer','freezer_back','freezer_food'];
            break;
        case 'work':
            $left=TYPE_TEMP;
            $leftSensor=['workSensor','weather.gc531'];
            break;
        case 'home':
            $left=TYPE_TEMP;
            $leftSensor=['houseInside','garage','weather.gc531'];
            break;
        case 'utilities':
            $left=TYPE_GAS;
            $leftSensor['gas'];
            $right=TYPE_WATER;
            $rightSensor['water'];
            break;
        default:
            $application = null;
    }
}

if (!$application) {
    $left=$_GET['left']?:TYPE_TEMP;
    $right=$_GET['right']?:null;
    $leftSensor=$_GET['leftSensor']?:null;
    $rightSensor=$_GET['rightSensor']?:null;
    $sensor=$_GET['sensor']?:null;
}

$start=array_key_exists('start',$_GET)?new DateTime($_GET['start']):(new \DateTime())->modify('-24 hours');
$startTs = $start->format('U');

$end=array_key_exists('end',$_GET)?new DateTime($_GET['end']):(new \DateTime());
$endTs = $end->format('U');

if ($end < $start) {
    $start=new \DateTime($end);
    $start->modify('-24 hours');
    $startTs = $start->format('U');
}

$minSec=max(1,($endTs-$startTs)/500);

//valid types: temperature,humidity,water,gas

$file = file("homeStats.data");

$chartData=[];
$sensors=[
        'houseInside'=>[
                'jsOptions'=>[
                        'label'=>"House",
                        'borderColor'=>'orange',
                ],
                'default' => true,
		'types'=>[TYPE_TEMP,TYPE_HUM,TYPE_PRESSURE,TYPE_ABSHUM],
		'frequency'=>600,
        ],
        'houseFurnaceRoom'=>[
                'jsOptions'=>[
                        'label'=>"Furnace room",
                        'borderColor'=>'red',
                ],
                'default'=> false,
                'types'=>[TYPE_TEMP,TYPE_HUM,TYPE_PRESSURE,TYPE_ABSHUM],
		'frequency'=>600,
        ],
        'garage'=>[
                'jsOptions'=>[
                        'label'=>"Garage",
                        'borderColor'=>'green',
                ],
                'default'=> true,
                'types'=>[TYPE_TEMP,TYPE_HUM,TYPE_PRESSURE,TYPE_ABSHUM],
		'frequency'=>600,
        ],
        'garageOutside'=>[
                'jsOptions'=>[
                        'label'=>"Outside",
                        'borderColor'=>'yellow',
                ],
                'default'=> false,
                'types'=>[TYPE_TEMP,TYPE_HUM,TYPE_PRESSURE,TYPE_ABSHUM],
		'frequency'=>600,
        ],
        'freezer_back'=>[
                'jsOptions'=>[
                        'label'=>"Freezer (back)",
                        'borderColor'=>'blue',
                ],
                'default'=> false,
                'types'=>[TYPE_TEMP,TYPE_HUM,TYPE_PRESSURE,TYPE_ABSHUM],
		'frequency'=>600,
        ],
        'freezer_food'=>[
                'jsOptions'=>[
                        'label'=>"Freezer (food)",
                        'borderColor'=>'black',
                ],
                'default'=> false,
                'types'=>[TYPE_TEMP,TYPE_HUM,TYPE_PRESSURE,TYPE_ABSHUM],
		'frequency'=>600,
        ],
        'gas'=>[
                'jsOptions'=>[
                        'label'=>'Natural Gas Usage(m^3)',
                        'borderColor'=>'black',
                ],
                'default'=> true,
                'types'=>[TYPE_GAS],
		'frequency'=>600,
        ],
        'water'=>[
                'jsOptions'=>[
                        'label'=>'Water Usage(m^3)',
                        'borderColor'=>'blue',
                ],
                'default'=> true,
                'types'=>[TYPE_WATER],
		'frequency'=>600,
        ],
        'workSensor'=>[
                'jsOptions'=>[
                        'label'=>"Office",
                        'borderColor'=>'navy',
                ],
                'default'=> true,
                'types'=>[TYPE_TEMP,TYPE_HUM,TYPE_PRESSURE,TYPE_ABSHUM],
		'frequency'=>600,
        ],
        'computer_exhaust'=>[
                'jsOptions'=>[
                        'label'=>"Computer Exhaust",
                        'borderColor'=>'pink',
                ],
                'default'=> false,
                'types'=>[TYPE_TEMP,TYPE_HUM,TYPE_PRESSURE,TYPE_ABSHUM],
		'frequency'=>600,
        ],
        'weather.gc531'=>[
                'jsOptions'=>[
                        'label'=>"YGK Weather Station",
                        'borderColor'=>'pink',
                ],
                'default'=> true,
                'types'=>[TYPE_TEMP,TYPE_HUM,TYPE_PRESSURE,TYPE_ABSHUM],
		'frequency'=>1800,
        ],
];

$count=0;
$minSecLast=[];
$minSecObjs=[];

if (!$sensor) {
        $sensor=[];
        foreach ($sensors as $s=>$d) {
                if ($d['default']) $sensor[]=$s;
        }
}

if(!$leftSensor) $leftSensor=$sensor;
if(!$rightSensor) $rightSensor=$sensor;

function parseRow($leftOrRight, $ts, $row) {
        global $count,$chartData,$sensors, $minSec, $minSecLast;
        $chartKey = "{$row[0]}_{$leftOrRight}";
        if (!array_key_exists($chartKey,$chartData)) {
                $chartData[$chartKey]=$sensors[$row[0]]['jsOptions'] + [
                        'label'=>$row[0],
                        'data'=>[],
                        'fill'=>false,
                        'yAxisID'=>$leftOrRight
                ];
        }
        switch($leftOrRight) {
        case TYPE_TEMP:
                $value=$row[1];
                break;
        case TYPE_HUM:
                $value=$row[2];
                break;
        case TYPE_ABSHUM:
                $t=$row[1];
                $rh=$row[2];
                $value = (6.112 * exp((17.67*$t)/($t+243.5)) * $rh * 2.1674) / (273.15+$t);
                break;
        case TYPE_PRESSURE:
                $value=$row[3];
                break;
        case TYPE_GAS:
                $value=$row[1]/100;
                break;
        case TYPE_WATER:
                $value=$row[1]/10;
                break;
        }
        if(!array_key_exists($chartKey,$minSecLast)) $minSecLast[$chartKey]=0;
        if ($ts < $minSecLast[$chartKey] + $minSec) {
                if (!array_key_exists($chartKey,$minSecObjs)) {
                    $minSecObjs[$chartKey]=[];
                }
                $obj = new stdClass();
                $obj->value=$value;
                $obj->ts=$ts;
                $minSecObjs[$chartKey][]=$obj;
                return;
        }
        //
        $yyy = max($minSec*10,$sensors[$row[0]]['frequency']*3);
        if ($ts > $minSecLast[$chartKey] + $yyy) {
            $chartData[$chartKey]['data'][$ts-$minSec]=['t'=>date('c',$ts-$minSec),'y'=>'NaN'];
        }
        if (array_key_exists($chartKey,$minSecObjs)) {
                $yyy_sum=0;
                $yyy_count=0;
                foreach ($minSecObjs[$chartKey] as $obj) {
                    if($obj->ts < $ts - $yyy) continue;
                    $yyy_sum+=$obj->value;
                    $yyy_count++;
                }
                unset($minSecObjs[$chartKey]);
                $value = $yyy_sum/$yyy_count;
        }
        $minSecLast[$chartKey]=$ts;
        $point=['t'=>date('c',$ts),'y'=>$value];
        $count++;
        $chartData[$chartKey]['data'][$ts]=$point;
}


foreach ($file as $line) {
        $line = trim($line);
        $row=explode(",",$line);
        //the last entry is always a timestamp
        //if it's before our startTS skip this row
        $ts = end($row);
        if($ts < $startTs) continue;
        if($ts > $endTs) continue;
        //if this isn't a sensor we're looking for skip this row
        if(in_array($row[0],$leftSensor) && in_array($left,$sensors[$row[0]]['types'])) {
                parseRow($left,$ts,$row);
        }
        if(in_array($row[0],$rightSensor) && in_array($right,$sensors[$row[0]]['types'])){
                parseRow($right,$ts,$row);
        }

}

foreach ($chartData as &$xxx) {
        ksort($xxx['data']);
        $xxx['data']=array_values($xxx['data']);
}
$datasets = json_encode(array_values($chartData));
$axes=[[
        'id'=>$left,
        'type'=>'linear',
        'position'=>'left',
        'scaleLabel'=>[
                'display'=>true,
                'labelString'=>$left
        ]
]];
if($right) {
        $axes[]=[
                'id'=>$right,
                'type'=>'linear',
                'position'=>'right',
                'scaleLabel'=>[
                        'display'=>true,
                        'labelString'=>$right
                ]
        ];
}
$axesJson = json_encode($axes);

?>
<html>
<head>
<style>
.chartWrapper {
  position: relative;
  background: white;
}

.chartWrapper > canvas {
  position: absolute;
  left: 0;
  top: 0;
  pointer-events: none;
}

#chartAreaWrapper {
  width: 100%;
  overflow-x: scroll;
}

.chartAreaWrapper2{
  width: <?php echo $count*5;?>px;
  min-width: 100%;
}
</style>
</head>
<body>
<div class="chartWrapper">
        <div id="chartAreaWrapper">
                <div class="chartAreaWrapper2">
                        <canvas id="myChart" height="600"></canvas>
                </div>
        </div>
        <canvas id="axis-test" height="600" width="0"></canvas>
</div>

<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/moment@2.24.0/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/chart.js@2.9.1/dist/Chart.min.js"></script>
<script>
var rectangleSet = false;
var myCanvas = document.getElementById('myChart').getContext('2d');
//var ctx = document.getElementById('myChart').getContext('2d');
var myChart = new Chart(myCanvas, {
    type: 'line',
    data: {
        datasets: <?php echo $datasets; ?>,
    },
    options: {
        maintainAspectRatio: false,
        responsive: true,
        scales: {
            xAxes: [{
                type: 'time',
            }],
            yAxes: <?php echo $axesJson; ?>
        },
        spanGaps: false,
    },
});
var element = document.getElementById("chartAreaWrapper");
element.scrollLeft = element.scrollWidth;
</script>
<!--
<ul>
<li><a href="?start=-24+hours">Day</a></li>
<li><a href="?start=-7+days">Week</a></li>
<li><a href="?start=-1+month">Month</a></li>
</ul>
-->
<ul>
<!--
<li><a href="?application=home">Home</a></li>
<li><a href="?application=furnace">Furnace</a></li>
<li><a href="?application=utilities">Utilities</a></li>
<li><a href="?application=freezer&amp;start=2020-03-22+00:00:00&amp;end=2020-03-22+04:10:00">Freezer Cycle test</a></li>
-->
</ul>
</body>
</html>

