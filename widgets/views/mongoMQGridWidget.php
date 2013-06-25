<? Yii::app()->clientScript->registerScript('mongoMQ-modal','$("*[data-text]").click(function(){var m=$("#mongoMQGrid-modal");$("pre",m).text($(this).data("text"));m.modal("show"); return false;})', CClientScript::POS_LOAD)?>

<?php $this->beginWidget('bootstrap.widgets.TbModal', array('id'=>'mongoMQGrid-modal', 'options' =>array('backdrop' => false))); ?>

<div class="modal-header">
	Result<a class="close" data-dismiss="modal">&times;</a>
</div>

<div class="modal-body">
	<pre></pre>
</div>

<?php $this->endWidget(); ?>


<?php /** @var BootActiveForm $form */
$form = $this->beginWidget('bootstrap.widgets.TbActiveForm', array(
	'id'=>'searchForm',
	'type'=>'search',
	'method' => 'get',
	'action' => $this->getController()->createUrl(''),
	'htmlOptions'=>array('class'=>'well'),
)); ?>

<?php echo $form->textFieldRow($model, 'search', array('class'=>'input-medium', 'prepend'=>'<i class="icon-search"></i>')); ?>

<?php echo $form->dropDownListRow($model, 'status', array(
	0 => Yii::t("MongoMQ", 'All'),
	MongoMQMessage::STATUS_NEW => Yii::t("MongoMQ", 'New'),
	MongoMQMessage::STATUS_ERROR => Yii::t("MongoMQ", 'Error'),
	MongoMQMessage::STATUS_SUCCESS =>Yii::t("MongoMQ", 'Success'),
	MongoMQMessage::STATUS_RECEIVED	=> Yii::t("MongoMQ", 'Received'),
)); ?>

<?php
	$vals = MongoMQMessage::model()->getCollection()->distinct("category");
	$ucVals = array();
	foreach($vals as $val) $ucVals[]=ucfirst($val);
	echo $form->dropDownListRow($model, 'category', array_merge(
	array(0 => Yii::t("MongoMQ", 'All')),
	array_combine($vals, $ucVals)
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
		'buttonType'=>'ajaxButton',
		'url' => $this->getController()->createUrl('', array_merge($_GET, array('operation' => 'delete'))),
		'ajaxOptions' => array(
			'success' => 'window.location.reload()'
		),
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
		array('name'=>'status',
			'header'=>'S',
			'type'=>'raw',
			'value' => '"<span class=\'label label-" . MongoMQGridWidget::getLabelClass($data) . "\'>".Yii::t("MongoMQ", $data->status)."</span>"'),
		array('name'=>'created', 'header'=>'Created', 'value'=>'date("d-m-Y H:i:s", $data->created->sec)'),
		array('name'=>'received', 'header'=>'Received', 'value'=>'isset($data->received) ? date("H:i:s", $data->received->sec) : ""'),
		array('name'=>'completed', 'header'=>'Completed', 'value'=>'isset($data->completed) ? date("H:i:s", $data->completed->sec) : ""'),
		array('name'=>'comment', 'header'=>'Message'),
		array('name'=>'details', 'header'=>'', 'type' => 'raw',
			'value' => "'
				<a href=\"#\" data-text=\"' . htmlspecialchars(print_r(\$data->params?\$data->params:'No params', true)) . '\">Params</a>,
				<a href=\"#\" data-text=\"' . htmlspecialchars(print_r(\$data->output?\$data->output:'Empty output', true)) . '\">Output</a>
			'"
		),
		array('name'=>'category', 'header'=>'Category'),
		array('name'=>'recipient', 'header'=>'recipient'),
		array('name'=>'priority', 'header'=>'Pr'),
	),
));

