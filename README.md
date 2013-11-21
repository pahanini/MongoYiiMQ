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

Message is php command or sh command, e.g. yii command or any sh script. If sender creates and sends to all command 'PHP yiic.php command action --param1'
then each recipient will execute '/usr/bin/php yiic.php command action --param1'

Example of create message:

    $message = Yii::app()->mongoMQ->createMessage();
    $message->body('PHP yiic.php command someaction --param1')      // add message body
        ->priority(100)                                             // set higher priority (default 1)
        ->send();                                                   // set to queue

### How to set recipient:

- send() - sends message to queue, first free recipient executes it.
- sendTo($name) - send message to specified recipient.
- sendAll() - send message to all registered recipients.

### Types of message bodies:

- php script 'PHP anyPHPCommand.php' - PHP will be changed to full path to php (/usr/bin/php)
- php script 'SH anySHCommand.sh' - SH will be changed to full path sh (/bin/sh)


## Receive and execute message

In application use MongoMQ::run(), MongoMQ::runOne() methods to receive and execute messages

      Yii::app()->mongoMQ->run();       // Receive and execute all messages
      Yii::app()->mongoMQ->runOne();    // Receive and execute one message

Indeed you need to run this command as frequently as you need.

Or you can use MongoMQCommand to run messages from console or crontab

       ./yiic mq run  // starts message queue workers, add this command to crontab

You can configure number of workers using MongoMQCommand::setWorkers() method


## View Messages and Recipients

MongoMQMessage and MongoMQRecipient extends EMongoDocument so you can use standart CGridView to display this objects.

## Notes
- You can not use this extension at windows systems
- About message queues http://en.wikipedia.org/wiki/Message_queue.
- This extension not tested at systems with thousands messages per second. For such hi-loaded tasks please use special software like RabbitMQ


