<?php
/**
 * MongoMQ class file
 *
 * @author 			Pavel E. Tetyaev <pahanini@gmail.com>
 * @version 		0.3
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
	private $_recipientName;

	/**
	 * @var string name of database
	 */
	public $db;

	/**
	 * @var int error messages remove from queue timeout (default 30 days)
	 */
	public $completedTimeout = 300;

	/**
	 * @var int error messages remove from queue timeout (default 30 days)
	 */
	public $errorTimeout = 2592000;

	/**
	 * @var int
	 */
	public $ifNotQueuedTimeout=0;

	/**
	 * @var string name of message class
	 */
	public $messagesClass = 'MongoMQMessage';

	/**
	 * @var name of EMongoClient component
	 */
	public $mongoID;

	/**
	 * @var int new messages remove from queue timeout (default 5 min)
	 */
	public $newTimeout = 0;

	/**
	 * @var array MongoClient options
	 */
	public $options = array();

	/**
	 * @var string path to php
	 */
	public $phpPath;

	/**
	 * @var string name of recipients class
	 */
	public $recipientsClass = 'MongoMQRecipient';

	/**
	 * @var int Limits starting of message with LA
	 */
	public $runLimit = 0;

	/**
	 * @var int received messages remove from queue timeout (default 1 hour)
	 */
	public $receivedTimeout = 3600;

	/**
	 * @var MongoDb connection string
	 */
	public $server = 'mongodb';

	/**
	 * @var path to sh bin
	 */
	public $shPath;

	/**
	 * @var bool wheather use cache for ifNotQueued checks
	 */
	public $useCache = true;

	/**
	 * Clears messages
	 */
	public function clearMessages()
	{
		$name = $this->messagesClass;
		$name::model()->deleteAll();
	}

	/**
	 * Clears recipients collection
	 */
	public function clearRecipients()
	{
		$name = $this->recipientsClass;
		$name::model()->deleteAll();
	}

	/**
	 * Creates new MongoMQMessage
	 *
	 * @return MongoMQMessage
	 */
	public function createMessage()
	{
		$name = $this->messagesClass;
		return new $name;
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
		$name = $this->messagesClass;
		return $name::model()->collection;
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
		$name = $this->recipientsClass;
		return $name::model()->collection;
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
	 * @return MongoMQMessage
	 */
	public function receiveMessage()
	{
		$name=$this->messagesClass;
		$messagesCollectionName = $name::model()->collectionName();
		$query = array(
			'status' => MongoMQMessage::STATUS_NEW,
			'$or' => array(
				array('recipient' => ''),
				array('recipient' => $this->recipientName),
			)
		);
		$update = array('$set' => array(
			'status' => MongoMQMessage::STATUS_RECEIVED,
			"received" => new MongoDate(),
			"recipient" => $this->recipientName,
		));
		$sort = array('priority' => -1, 'id' => 1);

		// We use dbcommand here for older versions of mongo driver (before 1.3.0)
		$res = $this->getDb()->command(
			array(
				'findandmodify' => $messagesCollectionName,
				'query' => $query,
				'update' => $update,
				'new' => true,
				'sort' => $sort,
			)
		);

		return $res['value'] ? $name::model()->populateRecord($res['value']) : null;
	}

	/**
	 * Removes old messages from queue
	 */
	public function handleTimeouts()
	{
		if ($this->newTimeout)
			MongoMQMessage::model()->withTimeout(MongoMQMessage::STATUS_NEW, 'completed', $this->newTimeout)->deleteAll();
		if ($this->receivedTimeout)
			MongoMQMessage::model()->withTimeout(MongoMQMessage::STATUS_NEW, 'received', $this->receivedTimeout)->deleteAll();
		if ($this->errorTimeout)
			MongoMQMessage::model()->withTimeout(MongoMQMessage::STATUS_NEW, 'completed', $this->errorTimeout)->deleteAll();
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
	 * Sets the database
	 *
	 * @param $name
	 * @throws CException
	 * @return void
	 */
	public function setDB($name)
	{
		if ($this->mongoID)
		{
			$mongodb = Yii::app()->getComponent($this->mongoID);
			if (!$mongodb instanceof EMongoClient)
				throw new CException("Application component {$this->mongoID} must be instance of EMongoClient");
			$this->_db = $mongodb->getDb($name);
		}
		return $this->_db;
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
