<?php
return array(
	'basePath' => dirname(__FILE__) . '/../..',
	'import' => array(
		'application.*'
	),
	'components'=>array(
		'mongoMQ' => array(
			'class' => 'MongoMQ',
			'server' => 'mongodb://localhost:27017',
			'db' => 'MongoMQ-test'
		),
	),
);
