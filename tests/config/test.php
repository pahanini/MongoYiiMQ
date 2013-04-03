<?php
return array(
	'basePath' => dirname(__FILE__) . '/../..',
	'import' => array(
		'application.*',
		'MongoYii.*',
		'MongoYii.behaviors.*',
		'MongoYii.validators.*',
	),
	'components'=>array(
		'mongodb' => array(
			'class' => 'MongoYii.EMongoClient',
			'server' => 'mongodb://localhost:27017',
			'db' => 'MongoMQ-test'
		),
		'mongoMQ' => array(
			'class' => 'MongoMQ',
			'mongoID' => 'mongodb',
		),
	),
);
