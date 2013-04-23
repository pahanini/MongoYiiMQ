<?php
/**
 * MongoMQDocument class file
 *
 * @author 			Pavel E. Tetyaev <pahanini@gmail.com>
 * @version 		0.3
 */

/**
 * MongoMQDocument
 */
class MongoMQDocument extends EMongoDocument
{
	private $_mongoMQ;

	/**
	 * @throws CException
	 * @return MongoMQ
	 */
	public function getMongoMQ()
	{
		if (!$this->_mongoMQ)
		{
			$this->_mongoMQ = Yii::app()->getComponent('mongoMQ');
			if (!$this->_mongoMQ instanceof MongoMQ)
				throw new CException("Application mongoMQ component expected to be instance of MongoMQ");

		}
		return $this->_mongoMQ;
	}
}