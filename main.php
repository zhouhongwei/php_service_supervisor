<?php 
require("ProcSupervise.php");
require("Test.php");
$d = new ProcSupervise();
$d->setLogDir("/home/xdata/");
$task_list = array(
	array('class_name'=>'test', 'method_name'=>'service1'),
	array('class_name'=>'test', 'method_name'=>'service2'),
	array('class_name'=>'test', 'method_name'=>'service3', 'params'=>array("my param")),
);
$d->setTaskList($task_list);
$d->run();
