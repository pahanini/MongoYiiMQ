<?php Yii::app()->clientScript->registerCss(__CLASS__, '.tooltip-inner { white-space:pre-wrap; text-align:left} ");') ?>

<?php /** @var BootActiveForm $form */
$form = $this->beginWidget('bootstrap.widgets.TbActiveForm', array(
	'id'=>'searchForm',
	'type'=>'search',
	'method' => 'post',
	'htmlOptions'=>array('class'=>'well'),
)); ?>

<?php echo $form->dropDownListRow($model, 'status', array(
	0 => Yii::t("MongoMQ", 'all'),
	MongoMQMessage::STATUS_NEW => Yii::t("MongoMQ", 'new'),
	MongoMQMessage::STATUS_ERROR => Yii::t("MongoMQ", 'error'),
	MongoMQMessage::STATUS_SUCCESS =>Yii::t("MongoMQ", 'success'),
	MongoMQMessage::STATUS_RECEIVED	=> Yii::t("MongoMQ", 'received'),
)); ?>

<?
$dataProvider = new EMongoDataProvider($model, array(
	'criteria' => $model->getDbCriteria(),
	'pagination' => array(
		'pageSize' => 100,
	),
))?>

&#160;
<?php $this->widget('bootstrap.widgets.TbButton', array('buttonType'=>'submit', 'label'=>Yii::t("MongoMQ", 'Ok'))); ?>

<div class='pull-right'>
	<?php $this->widget('bootstrap.widgets.TbButton', array(
		'htmlOptions'=>array('name'=>'delete'),
		'buttonType'=>'submit',
		'label'=>Yii::t("MongoMQ", 'Delete {$n} messages', array('{$n}' => $dataProvider->getTotalItemCount()))));
	?>
</div>

<?php $this->endWidget(); ?>

<?php $this->widget('bootstrap.widgets.TbGridView', array(
	'type'=>'striped bordered condensed',
	'ajaxUpdate'=>false,
	'dataProvider'=>$dataProvider,
	'template'=>"{summary}{items}{pager}",
	'rowCssClassExpression' => 'MongoMQGridWidget::getRowClass($data)',
	'columns'=>array(
		array('name'=>'created', 'header'=>'Created', 'value'=>'date("d-m-Y H:i:s", $data->created->sec)'),
		array('name'=>'received', 'header'=>'Received', 'value'=>'isset($data->received) ? date("H:i:s", $data->received->sec) : ""'),
		array('name'=>'completed', 'header'=>'Completed', 'value'=>'isset($data->completed) ? date("H:i:s", $data->completed->sec) : ""'),
		array('name'=>'comment', 'header'=>'Message'),
		array('name'=>'details', 'header'=>'', 'type' => 'raw',
			'value' => "'
				<a href=\"#\" rel=\"tooltip\" title=\"' . print_r(\$data->params, true) . '\">Params</a>,
				<a href=\"#\" rel=\"tooltip\" title=\"' . print_r(\$data->output, true) . '\">Output</a>
			'"
		),
		array('name'=>'status',
			'header'=>'S',
			'type'=>'raw',
			'value' => '"<span class=\'label label-" . MongoMQGridWidget::getLabelClass($data) . "\'>".Yii::t("MongoMQ", $data->status)."</span>"'),
		array('name'=>'category', 'header'=>'Category'),
	),
));

