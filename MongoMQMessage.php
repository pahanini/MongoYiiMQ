<?php
/**
 * MongoMQMessage class file
 *
 * @author 			Pavel E. Tetyaev <pahanini@gmail.com>
 * @version 		0.1
 */

/**
 * MongoMQMessage - message object for MonogoMQ
 *
 * @author Pavel E. Tetyaev <pahanini@gmail.com>
 */
class MongoMQMessage
{
	const PHP_PLACEHOLDER = 'PHP';

	const SH_PLACEHOLDER = 'SH';

	const STATUS_ERROR = 4;

	const STATUS_NEW = 1;

	const STATUS_RECIEVED = 2;

	const STATUS_SUCCESS = 3;

	private $_body = '';

	private $_completed;

	private $_exitCode = false;

	private $_priority = 1;

	private $_queue;

	private $_recivied;

	private $_status = self::STATUS_NEW;

	public function __construct(MongoMQ $queue, $attributes = null)
	{
		$this->_queue = $queue;
		if ($attributes)
		{
			$this->_id = isset($attributes['_id']) ? $attributes['_id'] : null;
			$this->_body = isset($attributes['body']) ? $attributes['body'] : '';
			$this->_completed = isset($attributes['completed']) ? $attributes['completed'] : 0;
			$this->_exitCode = isset($attributes['exitCode']) ? $attributes['exitCode'] : false;
			$this->_priority = isset($attributes['priority']) ? $attributes['priority'] : 1;
			$this->_recivied = isset($attributes['recivied']) ? $attributes['recivied'] : 0;
			$this->_status = isset($attributes['status']) ? $attributes['status'] : self::STATUS_NEW;
		}
	}

	/**
	 * Returns message attributes as array (limit)
	 *
	 * @return array
	 */
	public function asArray()
	{
		$result = array(

		);
		return $result;
	}

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
		$this->_body = $val;
		return $this;
	}

	/**
	 * Executes message body
	 */
	public function execute()
	{
		if ($this->_status <> self::STATUS_RECIEVED)
			throw new CException(__METHOD__ . ' can not execute command with status ' . $this->_status);
		$command = $this->getCommand();
		$oldDir = getcwd();
		chdir($this->_queue->dir);
		exec($command, $output, $exitCode);
		chdir($oldDir);
		$this->_completed = new MongoDate();
		$this->_output = $output;
		$this->_exitCode = $exitCode;
		$isOk = $this->_exitCode == 0;
		$this->_status = $isOk ? self::STATUS_SUCCESS : self::STATUS_ERROR;
		$result = $this->_queue->getQueueCollection()->update(
			array('_id' => $this->_id, 'status' => self::STATUS_RECIEVED),
			array('$set' => array(
				'exitCode' => $this->_exitCode,
				'status' => $this->_status,
				'completed' => $this->_completed,
			)),
			array(
				'safe' => true,
			)
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
			array($this->_queue->phpPath, $this->_queue->shPath),
			$this->_body
		);
	}

	/**
	 * Returns message handler exit code
	 *
	 * @return int
	 */
	public function getExitCode()
	{
		return $this->_exitCode;
	}

	/**
	 * Returns message handler output
	 *
	 * @return int
	 */
	public function getOutput()
	{
		return $this->_output;
	}

	/**
	 * Sets message priority (messages with higher priority executes first)
	 *
	 * @param int $val
	 * @return MongoMQMessage
	 */
	public function priority($val = 1)
	{
		$this->_priority = $val;
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
		$result = $this->_queue->getQueueCollection()->insert(array(
			'_id' => new MongoId(),
			'priority' => $this->_priority,
			'recipient' => $recipient,
			'body' => $this->_body,
			'status' => self::STATUS_NEW,
			'created' => new MongoDate(),
		), array(
			'safe' => true,
		));
		if (!$result['ok'])
			throw new CException(__METHOD__ . ' can not send message ' . $result['errmsg']);
	}
}