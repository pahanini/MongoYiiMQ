<?php
/**
 * MongoMQCommand class file
 *
 * @author 			Pavel E. Tetyaev <pahanini@gmail.com>
 */

/**
 * MongoMQCommand - console command to run messages.
 *
 * Add this command to command map to work with mongoMQ
 */
class MongoMQCommand extends MongoMQBaseCommand
{
	private $_initSignalHandlersFlag;
	private $_workers;
	private $_senders;
	private $_stop;

	/**
	 * @var int Ten means call handleTimeouts with 10% prob, 100 - call every minute (defaults 10)
	 */
	public $handleTimeoutsProb = 10;

	/**
	 * Clears messages
	 */
	public function actionClear()
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
	 * Runs all messages
	 */
	public function actionRun()
	{
		if (mt_rand(0, 100) < $this->handleTimeoutsProb) {
			$this->getMongoMQComponent()->handleTimeouts();
		}

		$this->initSignalHandlers();
		$this->actionRunSenders();

		start:

		/** @var MongoMQWorker $worker */
		$worker=null;
		foreach ($this->_workers as $val)
		{
			if ($val->getLock())
			{
				$worker=$val;
				break;
			}
		}

		// All workers are running
		if ($worker === null)
			return;

		// Ok, we lock file, no we can fork
		$pid = pcntl_fork();
		if ($pid == -1)
			throw new CException("Can not fork");
		elseif (!$pid)
			goto start;	// if you hate goto operator please will create pull request ;-)

		do
		{
			$result = $this->getMongoMQComponent()->runOne($worker->categories);
			if ($result === null)
				$worker->sleep();
			else
				$worker->incMessageCounter();

			pcntl_wait($status, WNOHANG);
			pcntl_signal_dispatch();

		}
		while (!$worker->isRestartRequired() && !$this->_stop);
	}

	/**
	 * Receives and executes one message
	 *
	 * @return mixed false if no messages in queue, or exit code
	 */
	public function actionRunOne()
	{
		$this->actionRunSenders();
		$this->getMongoMQComponent()->runOne();
	}

	/**
	 * Starts all senders
	 *
	 * @throws CException
	 */
	public function actionRunSenders($useCache=true)
	{
		if (!$this->_senders)
			return;
		if (!$useCache)
			$this->getMongoMQComponent()->useCache=false;
		foreach($this->_senders as $sender)
		{
			if (!$sender)
				continue;
			if (!isset($sender['params'])) $sender['params']=array();
			if (isset($sender['ID'], $sender['method']))
			{
				if (!$component=Yii::app()->getComponent($sender['ID']))
					throw new CException("Sender component with ID = {$sender['ID']} not found");
				call_user_func_array(array($component, $sender['method']), $sender['params']);
			}
			if (isset($sender['callback']))
				call_user_func_array($sender['callback'], $sender['params']);
			else
				throw new CException('Sender configuration must be an array containing  "ID" and "method" elements or "callback" element');
		}
	}

	public function actionShow($n=10)
	{
		$cursor = MongoMQMessage::model()->find()->sort(array('created' => -1))->limit($n);
		foreach ($cursor as $message)
		{
			echo date('d-m-Y H:i:s', $message->created->sec) . "\t" . $message->status . "\t" . $message->comment . "\n";
		}
	}

	public function actionWList()
	{
		echo "№\tPid\tStarted\t\t\t\tCategories\n";
		foreach ($this->_workers as $worker)
		{
			$pid = '-';
			$started = "-\t";
			$filename = $worker->getLockFileName();
			if (file_exists($filename))
			{
				if ($fp = fopen($filename, 'r')) {
					if (!$result = flock($fp, LOCK_EX | LOCK_NB)) {
						$tmp = fgets($fp);
						$tmp = explode('-', $tmp);
						if (count($tmp) == 2) {
							$pid = $tmp[0];
							$started = date('Y-m-d H:i:s', $tmp[1]);
						}
					}
					fclose($fp);
				}
			}
			echo "$worker->n\t$pid\t$started\t\t";
			echo is_array($worker->categories) ? join(', ', $worker->categories) : "all";
			echo "\n";
		}
	}


	public function beforeAction($action, $params)
	{
		MongoMQMessage::model()->getCollection()->ensureIndex(array('hash'=>1), array('sparce'=>true, 'dropDups'=>true));
		return parent::beforeAction($action, $params);
	}

	public function init()
	{
		if (!$this->_workers)
			$this->setWorkers(1);
	}

	public function initSignalHandlers()
	{
		if ($this->_initSignalHandlersFlag)
			return;
		pcntl_signal(SIGTERM, array($this, "signalHandler"));
		$this->_initSignalHandlersFlag=true;
	}

	/**
	 * @return int number of maximum workers
	 */
	public function getWorkerCount()
	{
		return count($this->_workers);
	}

	public function signalHandler($sigNo)
	{
		if ($sigNo == SIGTERM)
		{
			$this->_stop=true;
			ob_flush();
		}
	}

	public function setSenders($values)
	{
		$this->_senders=$values;
	}

	/**
	 * Examples:
	 *
	 * <code>
	 * setWorkers(1); // default (1 worker for all commands, restart after 100 messages completed)
	 * setWorkers(6); // default (6 workers for all commands, restart after 100 messages completed)
	 * setWorkers(array(
	 *     array(2, 'categories' => array('image', 'logs')), // two workers for image and logs (restart after 100 messages completed)
	 *   array(5),    // five workers for all commands (restart after 100 messages completed)
	 *   array(1, categories => array('video'), 'messagesLimit' => 6),  // 1 worker for video (restart after 6 messages completed)
	 * )); //
	 * </code>
	 *
	 * @param array|int $val workers config, int or array
	 * @throws CException
	 */
	public function setWorkers($val)
	{
		if (!is_array($val))
			$val = array(array((int)$val));
		$this->_workers=array();
		foreach($val as $tmp)
		{
			if (!is_array($tmp) || !is_numeric($tmp[0]))
				throw new CException("Invalid workers config format");

			for ($i = 1; $i<=$tmp[0]; $i++)
			{
				$this->_workers[]=new MongoMQWorker(
					count($this->_workers)+1,
					isset($tmp['categories']) && is_array($tmp['categories']) ? $tmp['categories'] : true,
					isset($tmp['messageLimit']) ? (int)$tmp['messageLimit'] : 100,
					isset($tmp['maxSleep']) ? (int)$tmp['maxSleep'] : 15
				);
			}
		}
	}


}
