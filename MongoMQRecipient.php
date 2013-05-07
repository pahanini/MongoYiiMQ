<?php
/**
 * MongoMQRecipient class file
 *
 * @author 			Pavel E. Tetyaev <pahanini@gmail.com>
 * @version 		0.3
 */

/**
 * MongoMQRecipient - recipient document for MongoMQ
 */
class MongoMQRecipient extends MongoMQDocument
{
	public $created;

	public function collectionName()
	{
		return 'MQRecipients';
	}

	public function getMongoId($value = null)
	{
		return (string)$value;
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
}