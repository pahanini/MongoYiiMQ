<?php
/**
 * MongoMQBaseCommand class file
 *
 * @author 			Pavel E. Tetyaev <pahanini@gmail.com>
 * @version 		0.3
 */

/**
 * MongoBaseMQCommand - console command to work with MQ.
 *
 * Extend this command tp create you own
 */
class MongoMQBaseCommand extends CConsoleCommand
{
	/**
	 * @throws CException
	 * @return \MongoMQ appication component
	 */
	protected function getMongoMQComponent()
	{
		$component = Yii::app()->getComponent($this->mongoMQID);
		if (!$component instanceof MongoMQ)
			throw new CException(__METHOD__ . ' mongoMQID is invalid, please make sure it refers to the ID of a MongoMQ application component');
		return $component;
	}
}
