<?php
/**
 * MongoMQ class file
 *
 * @author 			Pavel E. Tetyaev <pahanini@gmail.com>
 * @version 		0.1
 */

/**
 * MongoMQ - simple message queueing with mongo DB
 *
 * @param string $recipientName
 */
class MongoMQ extends CApplicationComponent
{
	public $_db;
	public $_connection;
	private $_queueCollection;
	private $_recipientsCollection;
	private $_recipientName;

	public $dir;

	/**
	 * @var string name of database
	 */
	public $db;

	/**
	 * @var array MongoClient options
	 */
	public $options = array();

	/**
	 * @var string path to php
	 */
	public $phpPath;

	/**
	 * @var string name of queue collection
	 */
	public $queueCollectionName = 'messageQueue';

	/**
	 * @var string name of recipients collection
	 */
	public $recipientsCollectionName = 'messageRecipients';

	/**
	 * @var int Limits starting of message with LA
	 */
	public $runLimit = 0;

	/**
	 * @var MongoDb connection string
	 */
	public $server = 'mongodb';

	/**
	 * @var path to sh bin
	 */
	public $shPath;

	/**
	 * Clears messages
	 */
	public function clearMessages()
	{
		$this->getQueueCollection()->remove(array());
	}

	/**
	 * Clears recipients collection
	 */
	public function clearRecipients()
	{
		$this->getRecipientsCollection()->remove(array());
	}

	/**
	 * Creates new MongoMQMessage
	 *
	 * @return MongoMQMessage
	 */
	public function createMessage()
	{
		return new MongoMQMessage($this);
	}

	/**
	 * @ignore
	 */
	private function getConnection()
	{
		if (!$this->_connection)
		{
			if(version_compare(phpversion('mongo'), '1.3.0', '<'))
			{
				$this->_connection = new Mongo($this->server, $this->options);
				$this->_connection->connect();
			}
			else
				$this->_connection = new MongoClient($this->server, $this->options);
		}
		return $this->_connection;
	}

	/**
	 * Gets the database
	 * @return MongoDB
	 */
	public function getDB()
	{
		if(empty($this->_db))
			$this->setDB($this->db);
		return $this->_db;
	}

	/**
	 * @return MongoCollection
	 */
	public  function getQueueCollection()
	{
		if (!$this->_queueCollection)
			$this->_queueCollection = $this->getDb()
				->selectCollection($this->queueCollectionName);
		return $this->_queueCollection;
	}

	/**
	 * @return array Array of recipients (keys are recipients names)
	 */
	public function getRecipients()
	{
		$cursor = $this->getRecipientsCollection()->find();
		$result = array();
		foreach ($cursor as $row)
			$result[$row['_id']] = (object)$row;
		return $result;
	}

	/**
	 * @return MongoCollection
	 */
	public function getRecipientsCollection()
	{
		if (!$this->_recipientsCollection)
			$this->_recipientsCollection = $this->getDb()
				->selectCollection($this->recipientsCollectionName);
		return $this->_recipientsCollection;
	}

	/**
	 * Returns current recipient name
	 *
	 * @return void
	 */
	public function getRecipientName()
	{
		return $this->_recipientName;
	}

	/**
	 * Init component
	 */
	public function init()
	{
		if (!$this->dir)
			$this->dir = Yii::app()->basePath;
		if (!$this->recipientName)
			$this->recipientName = Yii::app()->name;
		if (!$this->phpPath)
			$this->phpPath = exec('which php');
		if (!$this->shPath)
			$this->shPath = exec('which php');
		parent::init();
	}

	/**
	 * Recieves message from queue
	 * @throws CException
	 * @return MongoMQMessage|null
	 */
	public function receiveMessage()
	{
		$query = array(
			'status' => MongoMQMessage::STATUS_NEW,
			'$or' => array(
				array('recipient' => ''),
				array('recipient' => $this->recipientName),
			)
		);
		$update = array('$set' => array(
			'status' => MongoMQMessage::STATUS_RECIEVED,
			"recivied" => new MongoDate(),
			"recipient" => $this->recipientName,
		));
		$sort = array('priority' => -1, 'id' => 1);

		// We use dbcommand here for older versions of mongo driver (before 1.3.0)
		$res = $this->getDb()->command(
			array(
				'findandmodify' => $this->queueCollectionName,
				'query' => $query,
				'update' => $update,
				'new' => true,
				'sort' => $sort,
			)
		);
		if ($res['value'])
			return new MongoMQMessage($this, $res['value']);
		return null;
	}

	/**
	 * Recieves and executes one message
	 *
	 * @return mixed null if no messages in queue, or exit code
	 */
	public function runOne()
	{
		if ($message = $this->receiveMessage())
			return $message->execute();
		return null;
	}

	/**
	 * Runs all messages (limited by runLimit param)
	 * @param int $count
	 * @return void
	 */
	public function run($count=0)
	{
		$c = 0;
		$max = max($count, $this->runLimit);
		while (!$max || $c < $max)
		{
			if ($this->runOne() === null)
				break;
			$c++;
		}
	}

	/**
	 * Sets the database
	 * @param $name
	 */
	public function setDB($name)
	{
		$this->_db = $this->getConnection()->selectDb($name);
	}


	/**
	 * Sets recipient name
	 *
	 * @param string $name Name of recipient
	 * @throws CException
	 * @return void
	 */
	public function setRecipientName($name)
	{
		if ($this->_recipientName != $name)
		{
			$this->getRecipientsCollection()->update(
				array('_id' => $name),
				array('$set' => array('created' => new MongoDate())),
				array('upsert' => true));
			$this->_recipientName = $name;
		}
	}

}
