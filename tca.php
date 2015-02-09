<?php
if (!defined ("TYPO3_MODE")) 	die ("Access denied.");


$TCA["tx_questionnaire_questionnaires"] = Array (
	"ctrl" => $TCA["tx_questionnaire_questionnaires"]["ctrl"],
	"interface" => Array (
		"showRecordFieldList" => "hidden,starttime,endtime,fe_group,title,header,questions,per_page,overview,link"
	),
	"feInterface" => $TCA["tx_questionnaire_questionnaires"]["feInterface"],
	"columns" => Array (
		"hidden" => Array (		
			"exclude" => 1,	
			"label" => "LLL:EXT:lang/locallang_general.php:LGL.hidden",
			"config" => Array (
				"type" => "check",
				"default" => "0"
			)
		),
		"starttime" => Array (		
			"exclude" => 1,	
			"label" => "LLL:EXT:lang/locallang_general.php:LGL.starttime",
			"config" => Array (
				"type" => "input",
				"size" => "8",
				"max" => "20",
				"eval" => "date",
				"default" => "0",
				"checkbox" => "0"
			)
		),
		"endtime" => Array (		
			"exclude" => 1,	
			"label" => "LLL:EXT:lang/locallang_general.php:LGL.endtime",
			"config" => Array (
				"type" => "input",
				"size" => "8",
				"max" => "20",
				"eval" => "date",
				"checkbox" => "0",
				"default" => "0",
				"range" => Array (
					"upper" => mktime(0,0,0,12,31,2020),
					"lower" => mktime(0,0,0,date("m")-1,date("d"),date("Y"))
				)
			)
		),
		"fe_group" => Array (		
			"exclude" => 1,	
			"label" => "LLL:EXT:lang/locallang_general.php:LGL.fe_group",
			"config" => Array (
				"type" => "select",	
				"items" => Array (
					Array("", 0),
					Array("LLL:EXT:lang/locallang_general.php:LGL.hide_at_login", -1),
					Array("LLL:EXT:lang/locallang_general.php:LGL.any_login", -2),
					Array("LLL:EXT:lang/locallang_general.php:LGL.usergroups", "--div--")
				),
				"foreign_table" => "fe_groups"
			)
		),
		"title" => Array (		
			"exclude" => 0,		
			"label" => "LLL:EXT:questionnaire/locallang_db.php:tx_questionnaire_questionnaires.title",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",
			)
		),
		"header" => Array (		
			"exclude" => 0,		
			"label" => "LLL:EXT:questionnaire/locallang_db.php:tx_questionnaire_questionnaires.header",		
			"config" => Array (
				"type" => "text",
				"cols" => "48",	
				"rows" => "4",
			)
		),
		"questions" => Array (		
			"exclude" => 0,		
			"label" => "LLL:EXT:questionnaire/locallang_db.php:tx_questionnaire_questionnaires.questions",		
			"config" => Array (
				"type" => "text",
				"cols" => "48",	
				"rows" => "15",	
				"wizards" => Array(
					"_PADDING" => 2,
					"example" => Array(
						"title" => "Questionnaire Wizard",
						"type" => "script",
						"notNewRecords" => 1,
						"icon" => t3lib_extMgm::extRelPath("questionnaire")."tx_questionnaire_questionnaires_questions/wizard_icon.gif",
						"script" => t3lib_extMgm::extRelPath("questionnaire")."tx_questionnaire_questionnaires_questions/index.php",
					),
				),
			)
		),
		"per_page" => Array (		
			"exclude" => 0,		
			"label" => "LLL:EXT:questionnaire/locallang_db.php:tx_questionnaire_questionnaires.per_page",		
			"config" => Array (
				"type" => "input",	
				"size" => "4",
				"max" => "4",
				"eval" => "int",
				"checkbox" => "0",
				"range" => Array (
					"upper" => "100",
					"lower" => "1"
				),
				"default" => 10
			)
		),
		"intro" => Array (		
			"exclude" => 0,		
			"label" => "LLL:EXT:questionnaire/locallang_db.php:tx_questionnaire_questionnaires.intro",		
			"config" => Array (
				"type" => "check",
				"default" => 0,
			)
		),
		"overview" => Array (		
			"exclude" => 0,		
			"label" => "LLL:EXT:questionnaire/locallang_db.php:tx_questionnaire_questionnaires.overview",		
			"config" => Array (
				"type" => "check",
				"default" => 1,
			)
		),
        "link" => Array (        
            "exclude" => 0,        
            "label" => "LLL:EXT:questionnaire/locallang_db.php:tx_questionnaire_questionnaires.link",        
            "config" => Array (
                "type" => "input",        
                "size" => "15",
                "max" => "255",
                "checkbox" => "",
                "eval" => "trim",
/*                
                "wizards" => Array(
                    "_PADDING" => 2,
                    "link" => Array(
                        "type" => "popup",
                        "title" => "Link",
                        "icon" => "link_popup.gif",
                        "script" => "browse_links.php?mode=wizard",
                        "JSopenParams" => "height=300,width=500,status=0,menubar=0,scrollbars=1"
                    )
                )
*/                
            )
        ),		
	),

    "types" => Array (
        "0" => Array("showitem" => "hidden;;1;;1-1-1, title;;;;2-2-2, header;;;richtext[*]:rte_transform[mode=ts_css|imgpath=uploads/tx_questionnaire/rte/];3-3-3, questions, per_page,intro, overview, link")
    ),
    "palettes" => Array (
        "1" => Array("showitem" => "starttime, endtime, fe_group")
    )	
	
);
?>