<?php
/**
 * MongoMQBaseCommand class file
 *
 * @author 			Pavel E. Tetyaev <pahanini@gmail.com>
 */

/**
 * MongoBaseMQCommand - console command to work with MQ.
 *
 * Extend this command to create you own
 */
class MongoMQBaseCommand extends CConsoleCommand
{
	/**
	 * @var string MongoMQ component
	 */
	public $mongoMQID = 'mongoMQ';


	/**
	 * @throws CException
	 * @return \MongoMQ application component
	 */
	protected function getMongoMQComponent()
	{
		$component = Yii::app()->getComponent($this->mongoMQID);
		if (!$component instanceof MongoMQ)
			throw new CException(__METHOD__ . ' mongoMQID is invalid, please make sure it refers to the ID of a MongoMQ application component');
		return $component;
	}
}
