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
	private $_senders=array();

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
		$this->runSenders();
		$this->getMongoMQComponent()->runOne();
	}

	/**
	 * Runs all messages (limited by runLimit)
	 */
	public function actionRun()
	{
		$this->runSenders();
		$this->getMongoMQComponent()->run();
	}

	public function beforeAction($action, $params)
	{
		if (!$this->getLock()) return false;
		MongoMQMessage::model()->getCollection()->ensureIndex(array('hash'=>1), array('sparce'=>true, 'dropDups'=>true));
		return parent::beforeAction($action, $params);
	}

	/**
	 * Starts all senders
	 * @throws CException
	 */
	public function runSenders()
	{
		foreach($this->_senders as $sender)
		{
			if (isset($sender['ID'], $sender['method']))
			{
				if (!$component=Yii::app()->getComponent($sender['ID']))
					throw new CException("Sender component with ID = {$sender['ID']} not found");
				if (!isset($sender['params'])) $sender['params']=array();
				call_user_func_array(array($component, $sender['method']), $sender['params']);
			}
			else
				throw new CException('Sender configuration must be an array containing  "ID" and "method" elements');
		}
	}

	public function setSenders(array $values)
	{
		$this->_senders=$values;
	}
}
