<#1>
<?php
$fields = 
	array(
		'id' => array(
			'type' => 'integer',
			'length' => 4,
			'notnull' => true
		)
	);
	
if(!$ilDB->tableExists("rep_robj_ova")) {
	$ilDB->createTable("rep_robj_ova", $fields);
	$ilDB->addPrimaryKey("rep_robj_ova", array("id"));
}
?>

<#2>
<?php
	if(!$ilDB->tableColumnExists("rep_robj_ova", "selected_study_prg")) {
		$ilDB->addTableColumn('rep_robj_ova', 'selected_study_prg', array(
						'type' => 'text',
						'length' => 255,
						'notnull' => false
						));
	}
?>