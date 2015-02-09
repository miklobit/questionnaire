<?php
if (!defined ("TYPO3_MODE")) 	die ("Access denied.");

if (TYPO3_MODE=="BE")	{
		
	t3lib_extMgm::addModule("user","txquestionnaireM1","",t3lib_extMgm::extPath($_EXTKEY)."mod1/");
}



t3lib_div::loadTCA("tt_content");
$TCA["tt_content"]["types"]["list"]["subtypes_excludelist"][$_EXTKEY."_pi1"]="layout,recursive,select_key,pages";
$TCA["tt_content"]["types"]["list"]["subtypes_addlist"][$_EXTKEY."_pi1"]="pi_flexform";
t3lib_extMgm::addPiFlexFormValue($_EXTKEY.'_pi1', 'FILE:EXT:questionnaire/flexform_ds_pi1.xml');




t3lib_extMgm::allowTableOnStandardPages("tx_questionnaire_questionnaires");
t3lib_extMgm::addToInsertRecords("tx_questionnaire_questionnaires");

$TCA["tx_questionnaire_questionnaires"] = Array (
	"ctrl" => Array (
		"title" => "LLL:EXT:questionnaire/locallang_db.php:tx_questionnaire_questionnaires",		
		"label" => "title",	
		"tstamp" => "tstamp",
		"crdate" => "crdate",
		"cruser_id" => "cruser_id",
		"sortby" => "sorting",	
		"delete" => "deleted",	
		"enablecolumns" => Array (		
			"disabled" => "hidden",	
			"starttime" => "starttime",	
			"endtime" => "endtime",	
			"fe_group" => "fe_group",
		),
		"dynamicConfigFile" => t3lib_extMgm::extPath($_EXTKEY)."tca.php",
		"iconfile" => t3lib_extMgm::extRelPath($_EXTKEY)."icon_tx_questionnaire_questionnaires.gif",
	),
	"feInterface" => Array (
		"fe_admin_fieldList" => "hidden, starttime, endtime, fe_group, title, header, questions, per_page,intro, overview, link",
	)
);


t3lib_extMgm::addPlugin(Array("LLL:EXT:questionnaire/locallang_db.php:tt_content.list_type_pi1", $_EXTKEY."_pi1"),"list_type");
t3lib_extMgm::addStaticFile($_EXTKEY,"pi1/static/","Questionnaire");						


if (TYPO3_MODE=="BE")	$TBE_MODULES_EXT["xMOD_db_new_content_el"]["addElClasses"]["tx_questionnaire_pi1_wizicon"] = t3lib_extMgm::extPath($_EXTKEY)."pi1/class.tx_questionnaire_pi1_wizicon.php";
?>