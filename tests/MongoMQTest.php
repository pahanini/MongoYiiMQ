<?
require_once 'bootstrap.php';

class MongoMQTest extends CTestCase
{
	public static function foo()
	{
		return 'bar';
	}

	public function testMain()
	{
		// Create mq and clear all messages
		/** @var $mq MongoMQ   */
		$mq = Yii::app()->mongoMQ;
		$mq->clearRecipients();
		$mq->clearMessages();

		// Check set recipient name
		$mq->recipientName = 'test1';
		$mq->recipientName = 'test2';
		$c = $mq->getRecipientsCollection()->count();
		$this->assertEquals(2, $c);

		// Send message to random recipient
		$message = $mq->createMessage();
		$message->body('echo 1')
			->priority(1)
			->send();

		// Send message to random recipient
		$message = $mq->createMessage();
		$message->body('echo 2')
			->priority(1)
			->send();

		// Set message to random recipient with higher priority
		$message = $mq->createMessage();
		$message->body('ERRORCOMMAND 3')
			->priority(100)
			->send();

		// Test send all
		$message = $mq->createMessage();
		$message->body('echo 3')
			->priority(1)
			->sendToAll();

		// Ensure 3 messages in collection
		$this->assertEquals(5, MongoMQMessage::model()->find()->count());

		// Receive message with higher priority first
		$message = $mq->receiveMessage();
		$this->assertTrue($message instanceof MongoMQMessage);
		$this->assertEquals('ERRORCOMMAND 3', $message->getCommand());
		$this->assertFalse($message->execute());
		$this->assertEquals(127, $message->getExitCode());

		// Receive message
		$message = $mq->receiveMessage();
		$this->assertTrue($message instanceof MongoMQMessage);
		$this->assertEquals('echo 1', $message->getCommand());
		$this->assertTrue($message->execute());
		$this->assertEquals(0, $message->getExitCode());

		// Receive message
		$message = $mq->receiveMessage();
		$this->assertTrue($message instanceof MongoMQMessage);
		$this->assertEquals('echo 2', $message->getCommand());
		$this->assertTrue($message->execute());
		$this->assertEquals(0, $message->getExitCode());

		// Receive message
		$message = $mq->receiveMessage();
		$this->assertTrue($message instanceof MongoMQMessage);
		$this->assertEquals('echo 3', $message->getCommand());
		$this->assertTrue($message->execute());
		$this->assertEquals(0, $message->getExitCode());

		// No more messages for this recipient
		$message = $mq->receiveMessage();
		$this->assertNull($message);

		// Check statuses in database
		$this->assertEquals(3, $mq->getQueueCollection()
				->find(array('status' => MongoMQMessage::STATUS_SUCCESS))->count());
		$this->assertEquals(1, $mq->getQueueCollection()
			->find(array('status' => MongoMQMessage::STATUS_ERROR))->count());

		// Test user function as body
		$message = $mq->createMessage();
		$message->body(array('MongoMQTest', 'foo'))->send();
		$message = $mq->receiveMessage();
		$this->assertInstanceOf('MongoMQMessage', $message);
		$this->assertEquals(true, $message->executeBody());
		$this->assertTrue($message->execute());

		// Check ifNotQueued and category
		MongoMQMessage::model()->getCollection()->drop();
		$mq->useCache=false;
		$message = $mq->createMessage();
		$message->body(array('MongoMQTest', 'foo'))->category('test')->ifNotQueued(1)->send();
		$message = $mq->createMessage();
		$message->body(array('MongoMQTest', 'foo'))->category('test')->ifNotQueued(1)->send();
		$this->assertEquals(1, $mq->getQueueCollection()->find(array('status' => MongoMQMessage::STATUS_NEW))->count());
		$this->assertEquals(1, $mq->getQueueCollection()->find(array('category' => 'test'))->count());

		// Test cache usage
		MongoMQMessage::model()->getCollection()->drop();
		$mq->useCache=true;
		$message = $mq->createMessage();
		$message->body(array('MongoMQTest', 'foo'))->category('test')->ifNotQueued(1)->send();
		MongoMQMessage::model()->getCollection()->drop();
		$message = $mq->createMessage();
		$message->body(array('MongoMQTest', 'foo'))->category('test')->ifNotQueued(1)->send();	// Cache flag stops inserting here
		$this->assertEquals(0, $mq->getQueueCollection()->find()->count());
	}


	public function testRun()
	{
		// Create mq and clear all messages
		/** @var $mq MongoMQ   */
		$mq = Yii::app()->mongoMQ;
		$mq->clearMessages();
		$mq->clearRecipients();

		for ($i=1; $i<=4; $i++)
		{
			$message = $mq->createMessage();
			$message->body('echo ' . $i)
				->priority($i)
				->send();
		}

		$mq->runLimit = 3;
		$mq->run(); // Run 3 messages
		// Check statuses in database
		$this->assertEquals(3, $mq->getQueueCollection()
			->find(array('status' => MongoMQMessage::STATUS_SUCCESS))->count());
		$this->assertEquals(1, $mq->getQueueCollection()
			->find(array('status' => MongoMQMessage::STATUS_NEW))->count());
	}

	public function testLock()
	{
		Yii::app()->setRuntimePath(dirname(__FILE__));
		$command1=new MongoMQCommand('c1', null);
		$command2=new MongoMQCommand('c2', null);
		$this->assertTrue($command1->getLock());
		$this->assertFalse($command2->getLock());
		$this->assertTrue(file_exists($command1->getLockFileName()));
		unlink($command1->getLockFileName());
	}

	public function testTimeouts()
	{
		// Create mq and clear all messages
		/** @var $mq MongoMQ   */
		$mq = Yii::app()->mongoMQ;
		$mq->clearMessages();
		$mq->clearRecipients();

		$message=$mq->createMessage();
		$message->body("echo 1");
		$message->send();

		$message=$mq->receiveMessage();
		$message->received=new MongoDate(time()-10);
		$this->assertTrue($message->save());

		$mq->receivedTimeout=1;
		$this->assertEquals(1, $message->withTimeout(MongoMQMessage::STATUS_RECEIVED, 'received', 1)->find()->count());


		//$mq->handleTimeouts();
		//$this->assertEquals(0, MongoMQMessage::model()->count());
	}

}