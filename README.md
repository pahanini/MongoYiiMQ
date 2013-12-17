#MongoYiiMQ
==========

MongoDB based message queuing for php Yii framework.
Requires to https://github.com/Sammaye/MongoYii Yii extension installed

## Setup

Add to you config components section:

	'mongoMQ' => array(			// Internal classes expects this name of component, you can not change it
		'class' => 'MonogMQ',
		'mongoID' => 'mongoID', // MongoYii.EMongoClient component ID
	),

And to import section:

	'application.extensions.MongoYiiMQ.*',

## Create and send message

Message is php command, sh command or valid callback, e.g. yii command or any sh script. If sender creates and sends to all command 'PHP yiic.php command action --param1'
then each recipient will execute '/usr/bin/php yiic.php command action --param1'

Example of create message:

    $message = Yii::app()->mongoMQ->createMessage();
    $message->body('PHP yiic.php command someaction --param1')      // add message body
        ->priority(100)                                             // set higher priority (default 1)
        ->send();                                                   // set to queue

### How to set recipient:

- send() - sends message to queue, any recipient can execute it.
- sendTo($name) - send message to specified recipient.
- sendToMe() - send message to self.
- sendAll() - send message to all registered recipients so message will be executed many times, one time by each recipient

### Types of message bodies:

    body('PHP anyPHPCommand.php')  // php script body - PHP will be changed to full path to php (/usr/bin/php)
    body('SH anySHCommand.sh')  //  SH will be changed to full path sh (/bin/sh)
    body(array('Utils', 'foo'))   // calls Utils::foo()

## Receive and execute message

To execute messages you need to run:

    Yii::app()->mongoMQ->run();       // Receive and execute all messages
    Yii::app()->mongoMQ->runOne();    // Receive and execute one message
    Yii::app()->mongoMQ->runOne(array('system', 'image')); // Receive and execute one message from categories system or image

The simplest way to run messages is use MongoMQCommand in crontab.

MongoMQCommand.workers describes how many processes will
be used to execute messages from queue. Each workers array element describes group of processes.
First array element sets number of processes will be use and the second one sets categories of process.

MongoMQCommand.senders property is array of valid callbacks to call before run() action will be executed. Generally
you need to add `./yiic mq run` call once per minute in crontab so senders will be called once per minute.

Add in your console application config:

		'commandMap' => array(
			'mq'=>array(
				'class'=>'ext.MongoYiiMQ.MongoMQCommand',
				'workers' => array(
					array(1, 'categories' => array('images')),  // 1 process for images messages
					array(5, 'categories' => array('parser')),  // 5 processes for for parsers
					array(1),   // 1 process for any category (including images and parsers)
				),
				'senders' => array(
					array('callback' => array('Utils', 'senderForParsers')),    // call Utils::sendersForParsers()  before each MongoMQCommand::run()
					array('callback' => array('Utils', 'senderForTests')),      // call Utils::sendersForTests()  before each MongoMQCommand::run()
				)
			),
		)

## View Messages and Recipients

MongoMQMessage and MongoMQRecipient extends EMongoDocument so you can use standart CGridView to display this objects.

## Notes
- You can not use this extension at windows systems
- About message queues http://en.wikipedia.org/wiki/Message_queue.
- This extension not tested at systems with thousands messages per second. For such hi-loaded tasks please use special software like RabbitMQ


