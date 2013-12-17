<?php
/**
 * MongoMQMessage class file
 *
 * @author 			Pavel E. Tetyaev <pahanini@gmail.com>
 */

/**
 * MongoMQMessage - message document for MongoMQ
 *
 * @property string $comment
 * @property string $hash
 * @property MongoData $completed
 * @property MongoData $received
 */
class MongoMQMessage extends MongoMQDocument
{
	const PHP_PLACEHOLDER = 'PHP';

	const SH_PLACEHOLDER = 'SH';

	const STATUS_ERROR = 'e';

	const STATUS_NEW = 'n';

	const STATUS_RECEIVED = 'r';

	const STATUS_SUCCESS = 's';

	public $body = '';

	public $category='';

	/**
	 * @var MongoDate
	 */
	public $created;

	public $exitCode;

	private $ifNotQueued=false;

	public $output;

	public $priority=1;

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
	 * @param $val
	 * @return string
	 */
	public function calcCacheId($val)
	{
		return __CLASS__ . $val;
	}

	/**
	 * @return string
	 */
	public function calcHash()
	{
		return md5(serialize($this->body) . serialize($this->params) . $this->category);
	}

	/**
	 * @param null $val
	 * @return MongoMQMessage
	 */
	public function category($val=null)
	{
		$this->category=$val;
		return $this;
	}

	/**
	 * @param null $val
	 * @return MongoMQMessage
	 */
	public function comment($val=null)
	{
		$this->comment=$val;
		return $this;
	}

	/**
	 * @return string|void name of collection
	 */
	public function collectionName()
	{
		return 'MQMessages';
	}

	/**
	 * Executes message body
	 */
	public function execute()
	{
		if ($this->status <> self::STATUS_RECEIVED)
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
			array('_id' => $this->_id, 'status' => self::STATUS_RECEIVED),
			array(
				'$set' => array(
					'exitCode' => $this->exitCode,
					'status' => $this->status,
					'output' => $this->output,
					'completed' => $this->completed,
				),
				'$unset' => array(
					'hash' => true,
				)
			),
			$this->getDbConnection()->getDefaultWriteConcern()
		);

		if ($this->getMongoMQ()->useCache && $this->hash)
			Yii::app()->cache->delete($this->calcCacheId($this->hash));


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
		{
			$this->exitCode = call_user_func_array($this->body, is_array($this->params) ? $this->params : array());
			$this->output = Yii::getLogger()->getLogs();
			return $this->exitCode == true;
		}
		else
		{
			$command = $this->getCommand();
			exec($command, $output, $exitCode);
			$this->output = $output;
			$this->exitCode = $exitCode;
			return $this->exitCode == 0;
		}
	}

	public function attributeLabels()
	{
		return array(
			'status' => 'Status&#160;',
			'category' => 'Category&#160;',
		);
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
	 * Make sure message does not exists in queue before send
	 * @param bool $val
	 * @return MongoMQMessage
	 */
	public function ifNotQueued($val=600)
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
		$recipientsNames = array_keys($this->getMongoMQ()->getRecipients());
		foreach($recipientsNames as $val)
			$this->sendToRecipient($val);
		return $this;
	}

	/**
	 * Sends message to self
	 *
	 * @return MongoMQMessage
	 */
	public function sendToMe()
	{
		$this->sendToRecipient($this->getMongoMQ()->getRecipientName());
		return $this;
	}


	/**
	 * @ignore
	 */
	private function sendToRecipient($recipient = '')
	{
		if ($recipient) $this->recipient = $recipient;
		$this->created = new MongoDate();
		if ($this->ifNotQueued)
		{
			$this->hash = $this->calcHash();
			if ($this->getMongoMQ()->useCache)
			{
				if (($this->ifNotQueued > 0) && ($cache=Yii::app()->getComponent('cache')))
				{
					$id=$this->calcCacheId($this->hash);
					if ($cache->get($id)) return false;
					if (!$cache->set($id, true, $this->ifNotQueued))
					{
						if ($cache instanceof CMemCache && $cache->useMemcached)
							$message=$cache->getMemCache()->getResultMessage();
						else
							$message='no additional info';
						throw new CException("Can not store value into cache ($message)");
					}
				}
			}
		}
		$w=$this->getDbConnection()->w;
		$this->getDbConnection()->w=1;
		if ($this->hash)
		{
			$result=$this->getCollection()->update(
				array('hash'=>$this->hash),
				array('$set'=>array('hash' => $this->hash)),
				array('upsert'=>true)
			);
			if (empty($result['updatedExisting']))
			{
				$this->getCollection()->update(
					array('hash'=>$this->hash),
					array('$set'=>$this->getAttributes())
				);
			}
		}
		else
			$result=$this->getCollection()->insert($this->getAttributes());
		if (!$result['ok']) throw new CException("Can not send message " . print_r($result));
		$this->getDbConnection()->w=$w;
	}


	/**
	 * @param $status
	 * @param $attribute
	 * @param $timeout
	 * @return MongoMQMessage
	 */
	public function withTimeout($status, $attribute, $timeout)
	{
		$this->mergeDbCriteria(array(
			'criteria' => array(
				'status'=>$status,
				$attribute => array('$lt' => new MongoDate(time()-$timeout))
			),
		));
		return $this;
	}

}