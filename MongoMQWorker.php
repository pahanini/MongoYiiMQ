<?php
/**
 * MongoMQWorker class file
 *
 * @author 			Pavel E. Tetyaev <pahanini@gmail.com>
 */
class MongoMQWorker
{
	private $_fp;

	/**
	 * @var number of completed messages
	 */
	public $_messagesCompleted;

	/**
	 * @var int current sleep time
	 */
	public $_sleep;

	/**
	 * @var int internal worker number
	 */
	public $n;

	/**
	 * @var true|array list of available categories
	 */
	public $categories;

	/**
	 * @var int maximum sleep time in seconds
	 */
	public $maxSleep;

	/**
	 * @var int maximum messages for worker, if limit will be reached then worker will be restarted
	 */
	public $messageLimit;


	public function __construct($n, $categories=true, $messageLimit=100, $maxSleep=15)
	{
		$this->n = $n;
		$this->categories=$categories;
		$this->maxSleep=$maxSleep;
		$this->messageLimit=$messageLimit;
		$this->_messagesCompleted = 0;
		$this->_sleep = 1;
	}

	public function getLock()
	{
		$file=$this->getLockFileName();
		if (!$this->_fp = fopen($file, 'c'))
		{
			Yii::log("Can not open file $file for writing");
			return false;
		}
		if ($result = flock($this->_fp, LOCK_EX | LOCK_NB))
		{
			fputs($this->_fp, getmypid() . '-' .time());
		}
		return $result;
	}

	public function getLockFileName()
	{
		return Yii::app()->runtimePath.'/MongoYiiMQWorker'.$this->n.'.lock';
	}

	public function incMessageCounter()
	{
		$this->_sleep=1;
		$this->_messagesCompleted++;
	}

	public function isRestartRequired()
	{
		return $this->_messagesCompleted >= $this->messageLimit;
	}

	public function sleep()
	{
		sleep($this->_sleep);
		$this->_sleep = min($this->_sleep * 2, $this->maxSleep);
	}
}

