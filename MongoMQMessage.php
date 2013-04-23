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

	const STATUS_ERROR = 'e';

	const STATUS_NEW = 'n';

	const STATUS_RECIEVED = 'r';

	const STATUS_SUCCESS = 's';

	public $body = '';

	public $exitCode;

	public $hash;

	private $ifNotQueued=false;

	public $output;

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
	 * @param string|array $val
	 * @return MongoMQMessage
	 */
	public function body($val)
	{
		$this->body = $val;
		return $this;
	}

	/**
	 * @return string|void name of collection
	 */
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

		try
		{
			$this->status = $this->executeBody() ? self::STATUS_SUCCESS : self::STATUS_ERROR;
		}
		catch (Exception $e)
		{
			$this->status = self::STATUS_ERROR;
		}

		$this->completed = new MongoDate();

		// Change write concern
		$w = Yii::app()->mongodb->w;
		Yii::app()->mongodb->w = 1;

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

		// Restore
		Yii::app()->mongodb->w = $w;

		if (!$result['ok']) throw new CException(__METHOD__ . " can not update message. Error: " . $result['err']);

		return $this->status == self::STATUS_SUCCESS;
	}

	/**
	 * Executes message body but don't change statuses
	 */
	public function executeBody()
	{
		if (is_array($this->body))
			return call_user_func_array($this->body, is_array($this->params) ? $this->params : array());

		$command = $this->getCommand();
		exec($command, $output, $exitCode);
		$this->output = $output;
		$this->exitCode = $exitCode;
		return $this->exitCode == 0;
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
	 * @return string
	 */
	public function getHash()
	{
		return md5(serialize($this->body) . serialize($this->params));
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
	 * Make sure message does not exists in queue before send
	 * @param bool $val
	 * @return MongoMQMessage
	 */
	public function ifNotQueued($val=true)
	{
		$this->ifNotQueued = $val;
		return $this;
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
	 * @param null $val
	 * @return MongoMQMessage
	 */
	public function params($val=null)
	{
		$this->params=$val;
		return $this;
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
		if ($recipient) $this->recipient = $recipient;
		$this->created = new MongoDate();
		$this->hash = $this->getHash();
		if ($this->ifNotQueued)
		{
			if (($this->ifNotQueued > 0) && ($cache=Yii::app()->getComponent('cache')))
			{
				$id = __CLASS__ . $this->hash;
				if ($cache->get($id))
					return false;

				$cache->set($id, true, time() + $this->ifNotQueued);
			}

			$count = $this->find(array(
				'hash' => $this->hash,
				'status' => array('$in' => array(self::STATUS_NEW, self::STATUS_RECIEVED))
			))->count();

			if ($count)
				return false;
		}
		if (!$this->save())
			throw new CException("Can not send message");
	}

}