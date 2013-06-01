<?php
class MongoMQGridWidget extends CWidget
{
	public static function getLabelClass($data)
	{
		if ($data->status==MongoMQMessage::STATUS_ERROR)
			return 'important';
		if ($data->status==MongoMQMessage::STATUS_NEW)
			return 'info';
		if ($data->status==MongoMQMessage::STATUS_RECEIVED)
			return 'warning';
		if ($data->status==MongoMQMessage::STATUS_SUCCESS)
			return 'success';
	}

	public static function getRowClass($data)
	{
		if ($data->status==MongoMQMessage::STATUS_ERROR)
			return 'error';
		if ($data->status==MongoMQMessage::STATUS_NEW)
			return 'info';
		if ($data->status==MongoMQMessage::STATUS_RECEIVED)
			return 'warning';
		if ($data->status==MongoMQMessage::STATUS_SUCCESS)
			return 'success';
	}

	public function run()
	{
		$model=MongoMQMessage::model();
		$model->status=0;
		if (isset($_POST['MongoMQMessage']))
		{
			foreach($_POST['MongoMQMessage'] as $key=>$val)
			{
				if ($key=='status')
				{
					$model->$key=$val;
					if ($val)
						$model->mergeDbCriteria(array(
							'condition' => array(
								'status'=>$val,
							),
						));
				}
				if ($key=='search')
				{
					$model->$key=$val;
					if ($val)
						$model->mergeDbCriteria(array(
							'condition' => array(
								'comment'=>new MongoRegex('/'.$val.'/i'),
							),
						));
				}
			}
			if (isset($_POST['delete']))
			{
				$criteria=$model->getDbCriteria();
				$model->deleteAll(isset($criteria['condition']) ? $criteria['condition'] : array());
			}

		}
		$this->render('mongoMQGridWidget', array('model'=>$model));
	}

}