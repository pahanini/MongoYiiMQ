<?php
/**
 * MongoMQCommand class file
 *
 * @author 			Pavel E. Tetyaev <pahanini@gmail.com>
 * @version 		0.3
 */

/**
 * MongoMQCommand - console command to work with MQ.
 *
 * Add this command to command map to work with mongoMQ
 *
 */
class MongoMQCommand extends CConsoleCommand
{
	/**
	 * @var string MongoMQ component
	 */
	public $mongoMQID = 'mongoMQ';

	/**
	 * Returns MongoMQ appication component
	 */
	protected function getMongoMQComponent()
	{
		$component = Yii::app()->getComponent($this->mongoMQID);
		if (!$component instanceof MongoMQ)
			throw new CException(__METHOD__ . ' mongoMQID is invalid, please make sure it refers to the ID of a MongoMQ application component');
		return $component;
	}

	/**
	 * Clears messages
	 */
	public function clearMessagesAction()
	{
		$this->getMongoMQComponent()->clearMessages();
	}

	/**
	 * Clears recipients collection
	 */
	public function actionClearRecipients()
	{
		$this->getMongoMQComponent()->clearRecipients();
	}

	/**
	 * Recieves and executes one message
	 *
	 * @return mixed false if no messages in queue, or exit code
	 */
	public function actionRun()
	{
		$this->getMongoMQComponent()->runOne();
	}

	/**
	 * Runs all messages (limited by runLimit)
	 */
	public function actionRunMessages()
	{
		$this->getMongoMQComponent()->run();
	}
}
