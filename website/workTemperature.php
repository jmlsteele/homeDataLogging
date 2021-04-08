<?php
define("TYPE_TEMP","temperature");
define("TYPE_HUM","humidity");
define("TYPE_PRESSURE","pressure");
define("TYPE_WATER","water");
define("TYPE_GAS","gas");

$left=$_GET['left']?:TYPE_TEMP;
$right=$_GET['right']?:null;
$sensor=["workSensor","weather.gc531"];
if(array_key_exists('start',$_GET)) {
	$start=new DateTime($_GET['start']);
} else if (array_key_exists('startDiff',$_GET)) {
	$start=(new \DateTime())->modify($_GET['startDiff']);
} else {
	$start=(new \DateTime())->modify('-1 year');
}
$startTs = $start->format('U');
$numberOfPoints=array_key_exists('points',$_GET)?$_GET['points']:1000;
$minSec=max(1,(time()-$startTs)/$numberOfPoints);
//valid types: temperature,humidity,water,gas

$file = file("homeStats.data");
#$file = file("interleved.data");
$chartData=[];
$sensors=[
	'workSensor'=>[
		'jsOptions'=>[
			'label'=>"Office",
			'borderColor'=>'navy',
		],
		'types'=>[TYPE_TEMP,TYPE_HUM,TYPE_PRESSURE]
	],
	'weather.gc531'=>[
		'jsOptions'=>[
			'label'=>"YGK",
			'borderColor'=>'pink',
		],
		'types'=>[TYPE_TEMP,TYPE_HUM,TYPE_PRESSURE]
	],
];

$count=0;
$minSecCount=[];
$minSecSum=[];
$minSecLast=[];
foreach ($file as $line) {
	$line = trim($line);
	$row=explode(",",$line);
	//the last entry is always a timestamp
	//if it's before our startTS skip this row
	$ts = end($row);
	if($ts < $startTs) continue;
	//if this isn't a sensor we're looking for skip this row
	if($sensor && !in_array($row[0],$sensor)) continue;

	$leftOrRight = in_array($left,$sensors[$row[0]]['types'])?$left:(in_array($right,$sensors[$row[0]]['types'])?$right:null);
	if (array_key_exists($row[0],$sensors) && $leftOrRight) {
		$chartKey = "{$row[0]}_{$leftOrRight}";
		if (!array_key_exists($chartKey,$chartData)) {
			$chartData[$chartKey]=$sensors[$row[0]]['jsOptions'] + [
				'label'=>$row[0],
				'data'=>[],
				'fill'=>false,
				'yAxisID'=>$leftOrRight
			];
		}
		$value = null;
		switch($leftOrRight) {
		case TYPE_TEMP:
			$value = $row[1];
			break;
		case TYPE_HUM:
			$value = $row[2];
			break;
		case TYPE_PRESSURE:
			$value = $row[3];
			break;
		case TYPE_GAS:
			$value = $row[1]/100;
			break;
		case TYPE_WATER:
			$value = $row[1]/10;
			break;
		}
		if(!array_key_exists($chartKey,$minSecLast)) $minSecLast[$chartKey]=0;
		if ($ts < $minSecLast[$chartKey] + $minSec) {
			if (!array_key_exists($chartKey,$minSecCount)) {
				$minSecCount[$chartKey]=0;
				$minSecSum[$chartKey]=0;
			}
			$minSecCount[$chartKey]++;
			$minSecSum[$chartKey]+=$value;
			continue;
		}
                if ($ts > $minSecLast[$chartKey] + ($minSec*2)) {
		    $chartData[$chartKey]['data'][$ts-$minSec]=['t'=>date('c',$ts-$minSec),'y'=>'NaN'];
                }
		if (array_key_exists($chartKey,$minSecCount)) {
			$value = $minSecSum[$chartKey]/$minSecCount[$chartKey];
			unset($minSecCount[$chartKey]);
			unset($minSecSum[$chartKey]);
		}
		$minSecLast[$chartKey]=$ts;
		$point=['t'=>date('c',$ts),'y'=>round($value,2)];
		$count++;
		$chartData[$chartKey]['data'][$ts]=$point;
	}
}
foreach ($chartData as &$xxx) {
	ksort($xxx['data']);
	$xxx['data']=array_values($xxx['data']);
}
//var_dump(count($chartData['workSensor_temperature']['data']));
//die;
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
<script type="text/javascript" src="https://rawgit.com/chartjs/chartjs-plugin-annotation/master/chartjs-plugin-annotation.js"></script>
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
        annotation: {
            annotations: [
                {
                    type: 'line',
                    mode: 'horizontal',
                    scaleID: 'temperature',
                    value : 20,
                    borderColor: '#9EE4F9',
                    borderWidth: 4,
                    label: {
                        enabled: false,
                        content: '20 C'
                    }
                },
                {
                    type: 'line',
                    mode: 'horizontal',
                    scaleID: 'temperature',
                    value : 26,
                    borderColor: '#FF1313',
                    borderWidth: 4,
                    label: {
                        enabled: false,
                        content: '26 C'
                    }
                },
                {
                    type: 'line',
                    mode: 'vertical',
                    scaleID: 'x-axis-0',
                    value : '2020-01-21T14:20:00',
                    borderColor: 'red',
                    borderWidth: 4,
                    label: {
                        enabled: true,
                        content: 'Supply Fan Fixed'
                    }
                }
            ]
        },
        maintainAspectRatio: false,
        responsive: true,
        scales: {
            xAxes: [{
                type: 'time',
                time: {
                    unit: 'day',
                }
            }],
            yAxes: <?php echo $axesJson; ?>
        },
        spanGaps: false,
    },
});
var element = document.getElementById("chartAreaWrapper");
element.scrollLeft = element.scrollWidth;
</script>
</body>
</html>
