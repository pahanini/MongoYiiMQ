<?php
$envFile=dirname(__FILE__).'/config/env.php';
if (file_exists($envFile))
	$env=require_once $envFile;
else
	$env=array();
if (!isset($env['config']))
	$env['config']=dirname(__FILE__).'/config/test.php';
if (!isset($env['yiit']))
	$env['yiit'] = '../../yii/framework/yiit.php';
if (!isset($env['mongodb']))
	$env['mongodb'] = '../../MongoYii';

require_once($env['yiit']);
Yii::setPathOfAlias('MongoYii', $env['mongodb']);
Yii::createWebApplication($env['config']);
