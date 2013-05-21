<?php
/**
 * MongoMQCommand class file
 *
 * @author 			Pavel E. Tetyaev <pahanini@gmail.com>
 * @version 		0.3
 */

/**
 * MongoMQCommand - console command to run messages.
 *
 * Add this command to command map to work with mongoMQ
 *
 */
class MongoMQCommand extends MongoMQBaseCommand
{
	private $_fp;

	public function getLock()
	{
		$file=$this->getLockFileName();
		if (!$this->_fp = fopen($file, 'w+'))
		{
			Yii::log("Can not open file $file for writing");
			return false;
		}
		return  flock($this->_fp, LOCK_EX | LOCK_NB);
	}

	public function getLockFileName()
	{
		return Yii::app()->runtimePath.'/'.get_class($this).'.lock';
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
	public function actionRunOne()
	{
		$this->getMongoMQComponent()->runOne();
	}

	/**
	 * Runs all messages (limited by runLimit)
	 */
	public function actionRun()
	{
		$this->getMongoMQComponent()->run();
	}

	public function beforeAction($action, $params)
	{
		if (!$this->getLock())
			return false;
		return parent::beforeAction($action, $params);
	}
}
