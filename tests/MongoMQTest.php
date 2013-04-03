<?
require_once 'bootstrap.php';

class MongoMQTest extends CTestCase
{
	public function testMain()
	{
		// Create mq and clear all messages
		/** @var $mq MessageMQ   */
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

		// Ensure 3 messages in collection
		$this->assertEquals(3, MongoMQMessage::model()->find()->count());

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

		// No more messages
		$message = $mq->receiveMessage();
		$this->assertNull($message);

		// Check statuses in database
		$this->assertEquals(2, $mq->getQueueCollection()
				->find(array('status' => MongoMQMessage::STATUS_SUCCESS))->count());
		$this->assertEquals(1, $mq->getQueueCollection()
			->find(array('status' => MongoMQMessage::STATUS_ERROR))->count());

	}


	public function testRun()
	{
		// Create mq and clear all messages
		/** @var $mq MessageMQ   */
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
}