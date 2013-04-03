<?php
/**
 * MongoMQMessage class file
 *
 * @author 			Pavel E. Tetyaev <pahanini@gmail.com>
 * @version 		0.2
 */

/**
 * MongoMQMessage - message document for MongoMQ
 */
class MongoMQMessage extends MongoMQDocument
{
	const PHP_PLACEHOLDER = 'PHP';

	const SH_PLACEHOLDER = 'SH';

	const STATUS_ERROR = 4;

	const STATUS_NEW = 1;

	const STATUS_RECIEVED = 2;

	const STATUS_SUCCESS = 3;

	public $body = '';

	public $recipient='';

	public $status = self::STATUS_NEW;

	/**
	 * Sets message body.
	 *
	 * Message body should starts with PHP or SH keyword
	 *
	 * Examples:
	 *
	 * SH deploy_script.sh
	 * PHP run_some_task.php --param1 --param2
	 *
	 * @param int $val
	 * @return MongoMQMessage
	 */
	public function body($val)
	{
		$this->body = $val;
		return $this;
	}

	public function collectionName()
	{
		return Yii::app()->mongoMQ->messagesCollectionName;
	}

	/**
	 * Executes message body
	 */
	public function execute()
	{
		if ($this->status <> self::STATUS_RECIEVED)
			throw new CException(__METHOD__ . ' can not execute command with status ' . $this->status);
		$command = $this->getCommand();
		exec($command, $output, $exitCode);
		$this->completed = new MongoDate();
		$this->output = $output;
		$this->exitCode = $exitCode;
		$isOk = $this->exitCode == 0;
		$this->status = $isOk ? self::STATUS_SUCCESS : self::STATUS_ERROR;
		$result = $this->getMongoMQ()->getQueueCollection()->update(
			array('_id' => $this->_id, 'status' => self::STATUS_RECIEVED),
			array('$set' => array(
				'exitCode' => $this->exitCode,
				'status' => $this->status,
				'output' => $this->output,
				'completed' => $this->completed,
			)),
			$this->getDbConnection()->getDefaultWriteConcern()
		);
		if (!$result['ok'])
			throw new CException(__METHOD__ . " can not update message. Error: " . $result['err']);
		return (bool)$isOk;
	}

	/**
	 * Returns command to execute
	 *
	 * @return mixed
	 */
	public function getCommand()
	{
		return str_replace(
			array(self::PHP_PLACEHOLDER, self::SH_PLACEHOLDER),
			array($this->getMongoMQ()->phpPath, $this->getMongoMQ()->shPath),
			$this->body
		);
	}

	/**
	 * Returns message handler exit code
	 *
	 * @return int
	 */
	public function getExitCode()
	{
		return $this->exitCode;
	}

	/**
	 * Returns message handler output
	 *
	 * @return int
	 */
	public function getOutput()
	{
		return $this->output;
	}

	/**
	 * @static
	 * @param string $class
	 * @return \MongoMQMessage|\EMongoDocument
	 */
	public static function model($class = __CLASS__)
	{
		return parent::model($class);
	}

	/**
	 * Sets message priority (messages with higher priority executes first)
	 *
	 * @param int $val
	 * @return MongoMQMessage
	 */
	public function priority($val = 1)
	{
		$this->priority = $val;
		return $this;
	}

	/**
	 * Sends message to queue (any recipient can execute this message, message will be executed just once)
	 *
	 * @return MongoMQMessage
	 */
	public function send()
	{
		$this->sendToRecipient();
		return $this;
	}

	/**
	 * Sends message recipients (only this recipient executes message)
	 *
	 * @param string $recipientName
	 * @return MongoMQMessage
	 */
	public function sendTo($recipientName)
	{
		if (!is_array($recipientName))
			$recipientName = array($recipientName);
		foreach ($recipientName as $val)
			$this->sendToRecipient($val);
		return $this;
	}

	/**
	 * Sends message to all recipients (each recipient executes message once)
	 *
	 * @return MongoMQMessage
	 */
	public function sendToAll()
	{
		$recipientsNames = array_keys($this->_queue->getRecipients());
		foreach($recipientsNames as $val)
			$this->sendToRecipient($val);
		return $this;
	}

	/**
	 * @ignore
	 */
	private function sendToRecipient($recipient = '')
	{
		if ($recipient)
			$this->recipient = $recipient;
		$this->created = new MongoDate();
		if (!$this->save())
			throw new CException("Can not send message");
	}

}