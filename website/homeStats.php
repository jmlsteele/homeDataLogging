<?php
$data = file_get_contents('php://input');
if(!$data) {
	http_response_code(400);
	die();
}
$echo = $data;
trim($data);
$dataArr = explode(",",$data);
$dataArr[]=$_SERVER['REMOTE_ADDR'];
$dataArr[]=time();
file_put_contents("homeStats.data",implode(",",$dataArr)."\n",FILE_APPEND|LOCK_EX);
print $echo;
