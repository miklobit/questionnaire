<?php
/***************************************************************
*  Copyright notice
*  
*  (c) 2004-2005 Drecomm/Miklobit
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is 
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
* 
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
* 
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/** 
 * Module 'Questionnaires' for the 'questionnaire' extension.
 *
 * @author	Milosz Klosowicz <typo3@miklobit.com>
 */



	// DEFAULT initialization of a module [BEGIN]
unset($MCONF);	
require ("conf.php");
require ($BACK_PATH."init.php");
require ($BACK_PATH."template.php");
$LANG->includeLLFile("EXT:questionnaire/mod1/locallang.php");
#include ("locallang.php");
require_once (PATH_t3lib."class.t3lib_scbase.php");
$BE_USER->modAccess($MCONF,1);	// This checks permissions and exits if the users has no permission for entry.



class tx_questionnaire_module1 extends t3lib_SCbase {
	var $pageinfo;
	var $CMD = array(); 		// command ( via GET vars )
	
	var $answerArray = array();
	var $questionnaire = array();	// questionnaire definition
	var $statistic = array();		// questionnaire statistic
	var $printIcon;					// does print icon should be displayed
	
	/**
	 * 
	 */
	function init()	{
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;
		
		parent::init();

		/*
		if (t3lib_div::_GP("clear_all_cache"))	{
			$this->include_once[]=PATH_t3lib."class.t3lib_tcemain.php";
		}
		*/
		    // setting GP vars
		t3lib_div::_GP('CMD');
		
		if( isset($this->CMD["inResult"]))  {
			if( !is_array($this->CMD["inResult"]))  {
				 $this->CMD["inResult"] = unserialize(urldecode($this->CMD["inResult"])) ;;				 	
			}
		}
		if( isset($this->CMD["inCondition"]))  {
			if( !is_array($this->CMD["inCondition"]))  {
				 $this->CMD["inCondition"] = unserialize(urldecode($this->CMD["inCondition"])) ;				 	
			}
		}	
		if( isset($this->CMD["query"]))  {
			if( !is_array($this->CMD["query"]))  {
				 $this->CMD["query"] = unserialize(urldecode($this->CMD["query"])) ;				 	
			}						
		}
		$this->printIcon = 1;
	}

	/**
	 * Adds items to the ->MOD_MENU array. Used for the function menu selector.
	 */
	function menuConfig()	{

//debug( $this->MOD_SETTINGS, "menuConfig: biezace wybory z menu" );		
		global $LANG,$BE_USER;
		$this->MOD_MENU = Array (
			'questionnaire' => array(
				'0' => '--- Select  ---',
			),
			"function" => Array (
			    "0" => $LANG->getLL("select"),
				"1" => $LANG->getLL("details"),				
				"2" => $LANG->getLL("reporting"),
				"3" => $LANG->getLL("export"),
			),
			'user' => array(
				's0' => $LANG->getLL("user_all"),			
				's1' => $LANG->getLL("user_fe"),
				's2' => $LANG->getLL("user_no"),
			)			
		);
		
        // debug( $this->cObj->enableFields("tx_questionnaire_questionnaires") );		
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*','tx_questionnaire_questionnaires', '' );   
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))		{
			// Access check!
			// The questionnaire  will show only if there is a valid page and if this page may be viewed by the user
			$this->pageinfo = t3lib_BEfunc::readPageAccess($row["pid"],$this->perms_clause);
			$access = is_array($this->pageinfo) ? 1 : 0;		
			if (($row["pid"] && $access) || $BE_USER->user["admin"])	{ 						
				$qid = sprintf("%03d", $row["uid"] );
				if( strlen($row["title"]) > 50 )  {  
					$title = substr($row["title"],0,50).'...';	
				}
				else  {
					$title = $row["title"];
				} 
				$this->MOD_MENU["questionnaire"][$row["uid"]] = $qid.' : '.$title;
			}	
		}
       // read users data
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*','tx_questionnaire_answers', 'fe_user_id <> 0 AND complete <> 0' );   
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))		{
			$fe_user_id = $row["fe_user_id"] ;
            $username = $this->getUserName( $fe_user_id );  
			if( strlen($username) > 50 )  {  
				$username = substr($username,0,50).'...';	
			}
			$this->MOD_MENU["user"]['u'.$fe_user_id] = $username;
		}

//debug( $this->MOD_MENU, "MOD_MENU");
		
//		parent::menuConfig();
		$this->MOD_SETTINGS = t3lib_BEfunc::getModuleData($this->MOD_MENU, t3lib_div::_GP("SET"), $this->MCONF["name"] );	
		
//debug( $this->MOD_SETTINGS, "menuConfig: biezace wybory z menu" );		

	}

	/**
	 * Main function of the module. Write the content to $this->content
	 */
	function main()	{
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;
		
				// Draw the header.
			$this->doc = t3lib_div::makeInstance("bigDoc");   // Alternative: smallDoc: 350px, mediumDoc: 470px, bigDoc: 740px
			$this->doc->backPath = $BACK_PATH;


			$this->doc->form='<form action="" method="POST">';

				// JavaScript
			$this->doc->JScode = '
				<script language="javascript" type="text/javascript">
					script_ended = 0;
					function jumpToUrl(URL)	{
						document.location = URL;
					}
				</script>
			';
			
			$this->doc->postCode='
				<script language="javascript" type="text/javascript">
					script_ended = 1;
					if (top.fsMod) top.fsMod.recentIds["web"] = '.intval($this->id).';
				</script>
			';
			
//debug( $this->MOD_SETTINGS, "main: biezace wybory z menu" );
		if ( intval($this->CMD['printable']))  {
			$this->doc->bodyTagAdditions = ' onload="javascript:this.window.print();" '; 
		}		   

			$this->content.=$this->doc->startPage($LANG->getLL("title"));
			
			$this->content.=$this->doc->header($LANG->getLL("title"));
			$this->content.=$this->doc->spacer(5);
	   if ( ! intval($this->CMD['printable']))  {	
			$menuQ = t3lib_BEfunc::getFuncMenu(0,'SET[questionnaire]',$this->MOD_SETTINGS['questionnaire'],$this->MOD_MENU['questionnaire']);			
			//$menuQn = $this->getFuncListBox(0,'SET[question][]',$this->MOD_SETTINGS['question'],$this->MOD_MENU['question']);				
			$menuF = t3lib_BEfunc::getFuncMenu(0,'SET[function]',$this->MOD_SETTINGS['function'],$this->MOD_MENU['function']);
			$menuU = t3lib_BEfunc::getFuncMenu(0,'SET[user]',$this->MOD_SETTINGS['user'],$this->MOD_MENU['user']);			
			
			$this->content.= $this->doc->section("",$this->doc->menuTable(
			    array(
				    array( $LANG->getLL("user").':&nbsp; ', $menuU ),			    
				    array( $LANG->getLL("questionnaire").':&nbsp; ', $menuQ )				    
				),
				array(
				    array( $LANG->getLL("function").':&nbsp; ', $menuF )
				)
			));
			$this->content.=$this->doc->divider(5);

	   }	
           else   {
                        $content_filter = '<table><tr><td><p>'.$LANG->getLL("questionnaire").':</p></td><td><p><b>'.$this->MOD_MENU["questionnaire"][$this->MOD_SETTINGS["questionnaire"]].'</b></p></td></tr>'; 
                        $content_filter .= '<tr><td><p>'.$LANG->getLL("user").':</p></td><td><p><b>'.$this->MOD_MENU["user"][$this->MOD_SETTINGS["user"]].'</b></p></td></tr></table>'; 
			$this->content .= $content_filter ;
           }
			// Render content:
			$this->moduleContent();

//debug( $this->CMD, "command" );			

			// ShortCut icon
			if ($BE_USER->mayMakeShortcut())	{
				$this->content.=$this->doc->spacer(20).$this->doc->section("",$this->doc->makeShortcutIcon("id",implode(",",array_keys($this->MOD_MENU)),$this->MCONF["name"]));
			}
			// close window icon
	        if ( intval($this->CMD['printable']))  {   
                        $this->content.= ' <a href="javascript:this.window.close();"><img src="close_icon.gif" width="14" height="14" title="'.$LANG->getLL("close_page").'" alt="" /></a>';
            }
            // print window icon
            else   {
            	if( $this->printIcon )  {
	        		$url = 'index.php'.$this->getCmdString(array("printable" => '1'));
					$this->content.= ' <a href="'.$url.'" target="_blank"><img src="print_icon.gif" width="20" height="14" title="'.$LANG->getLL("print_page").'" alt="" /></a>';   	                    
            	}
            }	
            // Go back icon
			if ( count($this->CMD) && ! intval($this->CMD['printable']))  {  
            	$this->content.= '&nbsp; <a href="index.php"><img src="goback_icon.gif" width="14" height="14" title="'.$LANG->getLL("go_back").'"  alt="" /></a>';			
			}
            $this->content.=$this->doc->spacer(10);

	}

	/**
	 * Prints out the module HTML
	 */
	function printContent()	{

		$this->content.=$this->doc->endPage();
		echo $this->content;
	}
	
	/**
	 * Generates the module content
	 */
	function moduleContent()	{
		global $LANG;
		$questionnaireUid = $this->MOD_SETTINGS["questionnaire"]; 
		switch((string)$this->MOD_SETTINGS["function"])	{
			case 1:
				if ( ! $this->CMD['setQuery'])  {
					$this->printIcon = 0;
					$content = $this->displayQuestionnaireQuery( $questionnaireUid );																																																																																																																																																		
					$this->content.=$this->doc->section($LANG->getLL("answer_query"),$content,0,1);

				}
				else {
					$content = $this->displayAnswerDetails( $questionnaireUid );																																																																																																																																																		
					$this->content.=$this->doc->section($LANG->getLL("answer_details"),$content,0,1);
				}
				break;
			case 2:
	    		if ( intval($this->CMD['openAnswers']))  {
		     		$content = $this->displayOpenAnswers( $questionnaireUid, intval($this->CMD['qid']) );	
	     		}				
				else  {
					$content = $this->displayQuestionnaireStatistic( $questionnaireUid );
				}	
				$this->content.=$this->doc->section($LANG->getLL("statistic"),$content,0,1);
				break;
			case 3:
				$content = $this->exportData( $questionnaireUid );
				$this->content.=$this->doc->section($LANG->getLL("export_data"),$content,0,1);
				break;
		} 
	}
	
	
	/**
	 * display query view for selected  questionnaire.
	 */	
	function displayQuestionnaireQuery( $uid = 0 )	 {
		global $LANG;
		if( ! $this->getQuestionnaire( $uid ) )  return ;
//debug( $this->questionnaire, "ankieta");
        $operText = array( "0" => "", "1" => $LANG->getLL("include_if") );
        $operText1 = array( "empty" => $LANG->getLL("is_empty"), "eq" => "=", "ne" => "&lt;&gt;", "gt" => "&gt;","ge" => "&gt;=", "lt" => "&lt;", "le" => "&lt;=" );
        $printable = intval($this->CMD['printable']);
	    if ( ! $printable )  {    // editable version
		        $style0 = '';
		        $style1 = 'background: #e0d0d0';
		        $style2 = '';
		        $style3 = '';		
		        $style4 = 'background: #f0f0f0';
		        $style5 = 'background: #e0e0e0'; 
		        $style6 = 'background: #e0d0d0';
		        $style7 = 'background: #e0d0d0';	
		       	$align1 = ' vertical-align: top; text-align: center; ';		
				$content = '';
				$content = '<table style="width: 100%; '.$style0.'"><tr style="'.$style1.'">';
				$content .= '<td style="width:10%; '.$align1.$style2.'"><b>'.$LANG->getLL("in_result").'</b></td>'; 
				$content .= '<td style="width:10%; '.$align1.$style2.'"><b>'.$LANG->getLL("in_condition").'</b></td>';
				$content .= '<td style="width:80%; '.$align1.$style2.'"><b>'.$LANG->getLL("question").'</b></td>';
				$content .= '</tr>';
				$counter = 0;		
				foreach ( $this->questionnaire["questions"] as $key => $query ) {
					if( $counter % 2 )  {
					    $rowStyle = 'style="'.$style4.'"';	
					    $rowStyle1 = 'style="'.$style6.'"';			    
					}
					else  {
						$rowStyle = 'style="'.$style5.'"';
						$rowStyle1 = 'style="'.$style7.'"';				
					}
					$content .= '<tr '.$rowStyle.'>';
					$content .= '<td style="'.$align1.$style3.'"><input type="checkbox" name="CMD[inResult]['.$query["uid"].']" value="1" checked></td>';
					$content .= '<td style="'.$align1.$style3.'"><input type="checkbox" name="CMD[inCondition]['.$query["uid"].']" value="1"></td>';
					$content .= '<td style="vertical-align: top; '.$style3.'">';
					$content .= '<table style="width: 100%; '.$style0.'">';	
					if( $query["type"] == "multi_multi" )  {
						$colspan = 3;
					}
					else  {
						$colspan = 2;
					} 
					$content .= '<tr><td colspan="'.$colspan.'" style="vertical-align: top; '.$style3.'">'.htmlspecialchars($query["id"].'. '.$query["contents"]).'</td></tr>';							
					switch( $query["type"] )	{
						case "open" : 	$content .= '<tr '.$rowStyle1.'><td style=""><input type="checkbox" name="CMD[query]['.$query["uid"].'][empty]" value="1"></td><td style="width:100%;">'.$LANG->getLL("answer_is").' <b>'.$LANG->getLL("empty").'</b></td></tr>';
										break ;
						case "yesno" : 	$content .= '<tr '.$rowStyle1.'><td><input type="checkbox" name="CMD[query]['.$query["uid"].'][empty]" value="1"></td><td style="width:100%;">'.$LANG->getLL("answer_is").' <b>'.$LANG->getLL("empty").'</b></td></tr>';
										$content .= '<tr '.$rowStyle1.'><td><input type="checkbox" name="CMD[query]['.$query["uid"].'][0]" value="1"></td><td style="width:100%;">'.$LANG->getLL("answer").' = <b>'.$query["labelno"].'</b></td></tr>';
										$content .= '<tr '.$rowStyle1.'><td><input type="checkbox" name="CMD[query]['.$query["uid"].'][1]" value="1"></td><td style="width:100%;">'.$LANG->getLL("answer").' = <b>'.$query["labelyes"].'</b></td></tr>';
										break ;			
						case "single" : 
						case "multi"  :
										$content .= '<tr '.$rowStyle1.'><td><input type="checkbox" name="CMD[query]['.$query["uid"].'][empty]" value="1"></td><td style="width:100%;">'.$LANG->getLL("answer_is").' <b>'.$LANG->getLL("empty").'</b></td></tr>';
										foreach( $query["choices"] as $key1 => $choice )   {
											$content .= '<tr '.$rowStyle1.'><td><input type="checkbox" name="CMD[query]['.$query["uid"].']['.$key1.']" value="1"></td><td style="width:100%;">'.$LANG->getLL("answer").' = <b>'.$choice.'</b></td></tr>';
										}
										break ;		
						case "multi_multi"  :
										foreach( $query["choices"] as $key1 => $choice )   {
											$content .= '<tr '.$rowStyle1.'><td><input type="checkbox" name="CMD[query]['.$query["uid"].']['.$key1.'][set]" value="1"></td>';
											$content .= '<td style="text-align: right; white-space: nowrap;"><b>'.$choice.'</b> : '.$LANG->getLL("rating").' </td>';
											$options ='<option value="empty"  selected="selected" >'.$LANG->getLL("empty").'</option>';
											$options .='<option value="eq" >=</option>';	
		                                    $options .='<option value="ne" >&lt;&gt;</option>';
											$options .='<option value="gt" >&gt;</option>';
											$options .='<option value="ge" >&gt;=</option>';
											$options .='<option value="lt" >&lt;</option>';	
											$options .='<option value="le" >&lt;=</option>';							
											$content .= '<td style="width: 100%;"><select  size="1" name="CMD[query]['.$query["uid"].']['.$key1.'][operator]">'.$options.'</select>';
											$options = '<option value="0" ></option>';
											for( $idx = 1; $idx <= $query["cols"]; $idx++ )  {
												$options .='<option value="'.$idx.'" >'.$idx.'</option>';	
											}
											$content .= '<select style="" size="1" name="CMD[query]['.$query["uid"].']['.$key1.'][value]">'.$options.'</select>';
											$content .= '</td></tr>';
											
										}
										break ;											
					}	
					$content .= '</table></td></tr>';
					$counter ++;
				}
				$content .= '</table>';
				$content .= '<input type="submit" name="CMD[setQuery]" value="'.$LANG->getLL("display_result").'">&nbsp;<input type="reset" name="reset" value="'.$LANG->getLL("reset_condition").'">';     		        		                
	    }
	    else  {  // printable version of filter conditions ( read only )
		        $style0 = 'margin: 0px; padding: 0px; empty-cells: show ; border: 0px ; cell-spacing: 0px; border-collapse: collapse;';		        
		        $style1 = 'background: #ffffff;';	
		        $style2 = 'border-bottom: 2px solid black;';	
		        $style3 = 'border: 1px solid black;';
		        $style4 = 'background: #ffffff';
		        $style5 = 'background: #ffffff';
		        $style6 = 'background: #ffffff';
		        $style7 = 'background: #ffffff';		        		        		        	                
		       	$align1 = ' vertical-align: top; text-align: center; ';
		       	$nowrap   = 'white-space: nowrap;';		
				$content = '';
				$content = '<table style="width: 100%; '.$style0.'"><tr style="'.$style1.'">';
				$content .= '<td style="width:10%; '.$align1.$style2.'"><b>'.$LANG->getLL("in_result").'</b></td>'; 
				$content .= '<td style="width:10%; '.$align1.$style2.'"><b>'.$LANG->getLL("in_condition").'</b></td>';
				$content .= '<td style="width:80%; '.$align1.$style2.'"><b>'.$LANG->getLL("question").'</b></td>';
				$content .= '</tr>';
				$counter = 0;		
				foreach ( $this->questionnaire["questions"] as $key => $query ) {
					if( $counter % 2 )  {
					    $rowStyle = 'style="'.$style4.'"';	
					    $rowStyle1 = 'style="'.$style6.'"';			    
					}
					else  {
						$rowStyle = 'style="'.$style5.'"';
						$rowStyle1 = 'style="'.$style7.'"';				
					}
					$content .= '<tr '.$rowStyle.'>';
					$checked = $this->CMD['inResult'][$key] ? 'checked' : '';
					$content .= '<td style="'.$align1.$style3.'"><input type="checkbox"   '.$checked.' disabled></td>';
					$checked = $this->CMD['inCondition'][$key] ? 'checked' : '';					
					$content .= '<td style="'.$align1.$style3.'"><input type="checkbox" '.$checked.' disabled></td>';
					$content .= '<td style="vertical-align: top; '.$style3.'">';
					$content .= '<table style="width: 100%; '.$style0.'">';	
					if( $query["type"] == "multi_multi" )  {
						$colspan = 3;
					}
					else  {
						$colspan = 2;
					} 

					$content .= '<tr><td colspan="'.$colspan.'" style="vertical-align: top; '.$style3.'">'.htmlspecialchars($query["id"].'. '.$query["contents"]).'</td></tr>';		
					if( $this->CMD['inCondition'][$key])  {					
						switch( $query["type"] )	{
							case "open" : 	$select = $operText[$this->CMD['query'][$query["uid"]]['empty']] ;
											if( $select )  {
												$content .= '<tr '.$rowStyle1.'><td style="'.$nowrap.'">'.$select.'</td><td style="width:100%;">'.$LANG->getLL("answer_is").' <b>'.$LANG->getLL("empty").'</b></td></tr>';
											}
											break ;
							case "yesno" : 	$select = $operText[$this->CMD['query'][$query["uid"]]['empty']] ;
											if( $select )  {  
												$content .= '<tr '.$rowStyle1.'><td style="'.$nowrap.'">'.$select.' '.$LANG->getLL("answer_is").' <b>'.$LANG->getLL("empty").'</b></td></tr>';
											}
											$select = $operText[$this->CMD['query'][$query["uid"]]['0']] ;
											if( $select )  {
												$content .= '<tr '.$rowStyle1.'><td style="'.$nowrap.'">'.$select.' '.$LANG->getLL("answer").' = <b>'.$query["labelno"].'</b></td></tr>';
											}
											$select = $operText[$this->CMD['query'][$query["uid"]]['1']] ;
											if( $select )  {
												$content .= '<tr '.$rowStyle1.'><td style="'.$nowrap.'">'.$select.' '.$LANG->getLL("answer").' = <b>'.$query["labelyes"].'</b></td></tr>';
											}
											break ;			
							case "single" : 
							case "multi"  : $select = $operText[$this->CMD['query'][$query["uid"]]['empty']] ;
											if( $select )  {
												$content .= '<tr '.$rowStyle1.'><td style="'.$nowrap.'">'.$select.'</td><td style="width:100%;">'.$LANG->getLL("answer_is").' <b>'.$LANG->getLL("empty").'</b></td></tr>';
											}
											foreach( $query["choices"] as $key1 => $choice )   {
												$select = $operText[$this->CMD['query'][$query["uid"]][$key1]['empty']] ;
												if( $select )  {
													$content .= '<tr '.$rowStyle1.'><td style="'.$nowrap.'">'.$select.'</td><td style="width:100%;">'.$LANG->getLL("answer").' = <b>'.$choice.'</b></td></tr>';
												}
											}
											break ;		
							case "multi_multi"  :
											foreach( $query["choices"] as $key1 => $choice )   {
												$select = $operText[$this->CMD['query'][$query["uid"]][$key1]['set']] ;
												if( $select )  {
													$content .= '<tr '.$rowStyle1.'><td style="'.$nowrap.'">'.$select.'</td>';
													$content .= '<td style="text-align: right; white-space: nowrap;"><b>'.$choice.'</b> : '.$LANG->getLL("rating").' </td>';
													$select1 = $operText1[$this->CMD['query'][$query["uid"]][$key1]['operator']] ;
													$select2 = $this->CMD['query'][$query["uid"]][$key1]['value'] ;
													$content .= '<td style="width: 100%;"><b>'.$select1.' '.$select2.'</b></td></tr>';;
												}
											}
											break ;											
						}	
					}
					$content .= '</table></td></tr>';
					$counter ++;
				}
				$content .= '</table>';

       	}	
		return $content;													
	}		


	
	/**
	 * display answer details.
	 */	
	function displayAnswerDetails( $uid = 0 )	 {
		global $LANG;
		if( ! $this->getQuestionnaire( $uid ) )  return ;
		if( $this->CMD["setQuery"] && ! isset($this->CMD["inResult"]))  return ;
		$this->getAnswers( $uid );		
//debug( $this->questionnaire, "ankieta");
//debug( $this->answerArray, "odpowiedzi" );
//debug( $this->CMD, "command" );
//debug( $this->CMD['inCondition'] , "incondition");
//debug( $this->CMD['query'] , "query");
		$content = '';
		$noAnswer = '<div align=center><strong style="color: red">'.$LANG->getLL("no_answer").'</strong></div><br><br>';
		if( intval($this->CMD['printable']) )  {
			$content .= $this->displayQuestionnaireQuery( $uid ) ;
			$content .= '<br><br>';
		}		
	    if( ! count($this->answerArray) )   {
		   $content = $noAnswer ;
	    }
	    else  {
			foreach ( $this->answerArray as $keyA => $valA ) {
				if( $this->CMD["setQuery"] && ! $this->answerInFilter($valA))  {					
					continue;
				}
				$content .= $this->displayUserAnswer( $valA );
			}
			if( $content == '' ) $content = $noAnswer ;
		}	
			return $content;	
		
	}		
	

	/**
	 * display answer details for single user.
	 */	
	function displayUserAnswer( $answer = array() )	 {
			global $LANG;	
	        if ( ! intval($this->CMD['printable']))  { 
		        $style0 = '';
		        $style1 = 'background: #e0d0d0';
		        $style2 = '';
		        $style3 = '';		
		        $style4 = 'background: #f0f0f0';
		        $style5 = 'background: #e0e0e0';		        		                
	        }
	        else  {
		        $style0 = 'margin: 0px; padding: 0px; empty-cells: show ; border: 0px ; cell-spacing: 0px; border-collapse: collapse;';		        
		        $style1 = 'background: #ffffff;';	
		        $style2 = 'border-bottom: 2px solid black;';	
		        $style3 = 'border: 1px solid black;';
		        $style4 = 'background: #ffffff';
		        $style5 = 'background: #ffffff';		        		        	                
        	}				
			$userName = $this->getUserName( $answer["fe_user_id"] );
			$space = 10;
			$style = "vertical-align: top";
			$firstQuestion = 1;
			$content = '<table style="width: 100%; '.$style0.'"><tr style="'.$style1.'">';
			$content .= '<td style="width:25%; vertical-align: top; '.$style2.'"><b>'.$LANG->getLL("user").'</b></td>'; 
			$content .= '<td style="width:5%; vertical-align: top; '.$style2.'"><b>'.$LANG->getLL("id").'</b></td>';
			$content .= '<td style="width:40%; vertical-align: top; '.$style2.'"><b>'.$LANG->getLL("question").'</b></td>';
			$content .= '<td style="width:30%; vertical-align: top; '.$style2.'"><b>'.$LANG->getLL("answer").'</b></td>';
			$content .= '</tr>';
			$counter = -1;
			foreach ( $this->questionnaire["questions"] as $keyQ => $valQ )	{
				$counter ++ ;
				if( $this->CMD["setQuery"] && ! isset($this->CMD["inResult"][$keyQ]))  continue ;				
				if( ! $firstQuestion )  {
				   	$userName = '';
				}
				else  
					$firstQuestion = 0;
				if( $counter % 2 )  {
				    $rowStyle = 'style="'.$style4.'"';	
				}
				else  {
					$rowStyle = 'style="'.$style5.'"';
				}		
				$content .= '<tr '.$rowStyle.'><td style="vertical-align: top; '.$style3.'">'.htmlspecialchars($userName).'</td>';
				$content .= '<td style="vertical-align: top; '.$style3.'">'.htmlspecialchars($valQ["id"]).'</td>';
				$content .= '<td style="vertical-align: top; '.$style3.'">'.htmlspecialchars($valQ["contents"]).'</td>';
				$answerContent = '';
				switch( $valQ["type"] )   {
				  case "yesno" :  if( isset($answer["content"][$keyQ]) )   {
					  				 $answerContent .= '<p>';
					                 $answerContent .= $answer["content"][$keyQ] ? htmlspecialchars($valQ["labelyes"]) : htmlspecialchars($valQ["labelno"]) ;
					                 $answerContent .= '</p>';
				  				  }
				  				  else  {
					  				 $answerContent = '<p style="color: red">'.$LANG->getLL("empty").'</p>';	  
				  				  } 
				  				  break;  
				  case "single" :  if( isset($answer["content"][$keyQ]) && ($answer["content"][$keyQ] != "") )   {
					  				 $answerContent .= '<p>';
					                 $answerContent .= htmlspecialchars($valQ["choices"][$answer["content"][$keyQ]]);
					                 $answerContent .= '</p>';
				  				  }
				  				  else  {
					  				 $answerContent = '<p style="color: red">'.$LANG->getLL("empty").'</p>';	  
				  				  } 
				  				  break; 	
				  case "multi" :  if( isset($answer["content"][$keyQ]) )   {
					  				 foreach( $answer["content"][$keyQ] as $keyC => $valC  )  {
					  				 	$answerContent .= '<p>';
					                 	$answerContent .= htmlspecialchars($valQ["choices"][$keyC]);
					                 	$answerContent .= '</p>';
				                 	}
				  				  }
				  				  else  {
					  				 $answerContent = '<p style="color: red">'.$LANG->getLL("empty").'</p>';	  
				  				  } 
				  				  break; 	
				  case "multi_multi" :  if( isset($answer["content"][$keyQ]) )   {
					  				 foreach( $valQ["choices"] as $keyC => $valC  )  {
						  				if(  isset( $answer["content"][$keyQ][$keyC] )   )    { 
					  				 		$answerContent .= '<p>';
					                 		$answerContent .= htmlspecialchars($valQ["choices"][$keyC]).':'.htmlspecialchars($answer["content"][$keyQ][$keyC]);
					                 		$answerContent .= '</p>';
				                 		}
				                 		else    {
					  				 		$answerContent .= '<p style="color: red">';
					                 		$answerContent .= $valQ["choices"][$keyC].': '.$LANG->getLL("empty");
					                 		$answerContent .= '</p>';					                 		
			                 			}
				                 	}
				  				  }
				  				  else  {
					  				 $answerContent = '<p style="color: red">'.$LANG->getLL("empty").'</p>';	  
				  				  } 
				  				  break; 				  				  			  				  			  				  
				  case "open" :  if( isset($answer["content"][$keyQ]) && ($answer["content"][$keyQ] != ""))   {
					  				 $answerContent .= '<p>';
					                 $answerContent .= htmlspecialchars($answer["content"][$keyQ]) ;
					                 $answerContent .= '</p>';
				  				  }
				  				  else  {
					  				 $answerContent = '<p style="color: red">'.$LANG->getLL("empty").'</p>';	  
				  				  } 
				  				  break; 				  				  
				
				}
				$content .= '<td style="vertical-align: top; '.$style3.'">'.$answerContent.'</td></tr>';				
			}
			$content .= '</table><br>';
			return $content;	
		
	}			
	

	/**
	 * display answer statistic + bar chart.
	 */	
	function displayQuestionnaireStatistic( $uid = 0 )	 { 
	    $this->calculateQuestionnaireStatistic( $uid );
		$content = '';
		foreach ( $this->statistic as $key => $val ) {
			$content .= $this->displayQuestionStatistic( $key );
		}
		return $content;	
	}	
	
	
	/**
	 * display statistic for one question.
	 */	
	function displayQuestionStatistic( $qid = 0 )	 {

		global $LANG;			
		$total = array();
		$image = "image.php?";
		$total["text"] = $LANG->getLL("total_answers");

		$total["value"] = $this->statistic[$qid]["total_answers"];	
		if( $this->statistic[$qid]["link_url"]  )   {
			$url = '<a href="'.$this->statistic[$qid]["link_url"].'">'.$LANG->getLL("view_details").'</a><br>';
		}
		else  {
			$url = '';	
		}		
		$lines = array();
				switch( $this->statistic[$qid]["type"]  )	{
			case "yesno" :  $lines[0]["text"] = $this->statistic[$qid]["labelyes"]["label"];
							$lines[0]["value"] = $this->statistic[$qid]["labelyes"]["num_answers"];
							$lines[0]["bar_value"] = $this->statistic[$qid]["labelyes"]["percentage_answers"].' %';
							$lines[0]["bar_width"] = 300 * intval($this->statistic[$qid]["labelyes"]["percentage_answers"]) / 100;
							if( ! $lines[0]["bar_width"] )  $lines[0]["bar_width"] = 1;
							$lines[0]["bar_color"] = "00ff00";
							$lines[0]["image"] = $image.'red=0&green=255&blue=0';
							$lines[1]["text"] = $this->statistic[$qid]["labelno"]["label"];
							$lines[1]["value"] = $this->statistic[$qid]["labelno"]["num_answers"];
							$lines[1]["bar_value"] = $this->statistic[$qid]["labelno"]["percentage_answers"].' %';
							$lines[1]["bar_width"] = 300 * intval($this->statistic[$qid]["labelno"]["percentage_answers"]) / 100;
							if( ! $lines[1]["bar_width"] )  $lines[1]["bar_width"] = 1;
							$lines[1]["bar_color"] = "ff0000";
							$lines[1]["image"] = $image.'red=255&green=0&blue=0';
//debug( $lines, "linie" );
							break;		
			case "single" : 
			case "multi":	foreach( $this->statistic[$qid]["choices"] as $key => $val )   {
								$lines[$key]["text"] = $val["label"];
								$lines[$key]["value"] = $val["num_answers"];
								$lines[$key]["bar_value"] = $val["percentage_answers"].' %';
								$lines[$key]["bar_width"] = 300 * intval($val["percentage_answers"]) / 100;
								if( ! $lines[$key]["bar_width"] )  $lines[$key]["bar_width"] = 1;
								$lines[$key]["bar_color"] = sprintf("%02x%02x%02x", rand(0,255), rand(0,255), rand(0,255));
								$lines[$key]["image"] = $image.'red='.rand(0,255).'&green='.rand(0,255).'&blue='.rand(0,255);
							}
							break;	

			case "multi_multi":	foreach( $this->statistic[$qid]["choices"] as $key => $val )   {
								$lines[$key]["text"] = $val["label"];
								$lines[$key]["value"] = $val["num_answers"];
								$lines[$key]["bar_value"] = $val["average_rate"].' avg.';
								$lines[$key]["bar_width"] = 300 * intval($val["average_rate"]) / $this->statistic[$qid]["cols"];
								if( ! $lines[$key]["bar_width"] )  $lines[$key]["bar_width"] = 1;
								$lines[$key]["bar_color"] = sprintf("%02x%02x%02x", rand(0,255), rand(0,255), rand(0,255));
								$lines[$key]["image"] = $image.'red='.rand(0,255).'&green='.rand(0,255).'&blue='.rand(0,255);
							}
							break;																												
		}
				
		$content = '<br>'.htmlspecialchars($this->statistic[$qid]["contents"] );
		if( $this->statistic[$qid]["compulsory"] )   {
			$content .= ' &nbsp; (<b style="color: #ff0000;">*</b>)';
		}
		
		$content .= '<br><br>';
		$content .= '<table  style="font-family: tahoma; font-size: 12px; margin-left: 20px;">';
		foreach( $lines as $key => $val )   {
			$content .= '<tr><td><p>'.$val["text"].'</p></td>';	
			$content .= '<td>&nbsp; '.$val["value"].' &nbsp;</td>';				
			$content .= '<td><table STYLE="font-family: tahoma; font-size: 12px"><tr><td>';
			$content .= '<img src="'.$val["image"].'" width="'.$val["bar_width"].'" height="10"></td><td>&nbsp; ' ;
			$content .=	$val["bar_value"].' &nbsp;</td></tr>';
			$content .= '</table></td></tr>'; 				
		}
		$content .= '<tr><td><p><b>'.$total["text"].'</b></p></td>';	
		$content .= '<td><b>&nbsp; '.$total["value"].' &nbsp;</b></td>';				
		$content .= '<td></td></tr>';
		$content .= '</table><br>'; 
	    if ( ! intval($this->CMD['printable'])  && $total["value"])  {		
			$content .= $url;
		}
 		$content .= '<hr>';
		return $content;	
		
	}	
	
	/**
	 * calculate  statistic for questionnaire.
	 */	
	function calculateQuestionnaireStatistic( $uid = 0 )	 {

		if( ! $this->getQuestionnaire( $uid ) )  return ;
		$this->getAnswers( $uid );		
//debug( $this->questionnaire, "ankieta");
//debug( $this->answerArray, "odpowiedzi" );
		$this->statistic = array();
		foreach ( $this->questionnaire["questions"] as $key => $val )	{
			$this->statistic[$key] = array();
			$this->statistic[$key]["type"] = $val["type"] ;
			 
			if( $val["compulsory"] ) {  
				$this->statistic[$key]["compulsory"] = 1; 
			}
			else  {
				$this->statistic[$key]["compulsory"] = 0;
			}						
			$this->statistic[$key]["contents"] = $val["id"].'. '.$val["contents"] ; 
			$this->statistic[$key]["total_answers"] = 0 ;
			switch( $val["type"] )	{
				case "yesno" :  $this->statistic[$key]["labelyes"] = array();
								$this->statistic[$key]["labelyes"]["label"] = $val["labelyes"];
								$this->statistic[$key]["labelyes"]["num_answers"] = 0;
								$this->statistic[$key]["labelyes"]["percentage_answers"] = 0;
								$this->statistic[$key]["labelno"] = array();
								$this->statistic[$key]["labelno"]["label"] = $val["labelno"];
								$this->statistic[$key]["labelno"]["num_answers"] = 0;

								$this->statistic[$key]["labelno"]["percentage_answers"] = 0;						
								break;
				case "single" : 


				case "multi":
								$this->statistic[$key]["choices"] = array();
								foreach( $val["choices"] as $key1 => $val1 )  {
									$this->statistic[$key]["choices"][$key1]["label"] = $val1;
									$this->statistic[$key]["choices"][$key1]["num_answers"] = 0;
									$this->statistic[$key]["choices"][$key1]["percentage_answers"] = 0;
								}
								break ;
				case "multi_multi":	$this->statistic[$key]["cols"] = $val["cols"] ;
									$this->statistic[$key]["choices"] = array();
									foreach( $val["choices"] as $key1 => $val1 )  {
										$this->statistic[$key]["choices"][$key1]["label"] = $val1;
										$this->statistic[$key]["choices"][$key1]["num_answers"] = 0;										
										$this->statistic[$key]["choices"][$key1]["total_rate"] = 0;

										$this->statistic[$key]["choices"][$key1]["average_rate"] = 0;
									}
										
									break ;
				case "open" : 	$this->statistic[$key]["link_url"] = htmlspecialchars('index.php?CMD[openAnswers]=1&CMD[qid]='.$key);
								break;	
				
			}	
				
		}
		
		foreach ( $this->answerArray as $key => $val )	{
			foreach ( $val["content"] as $key1 => $val1 )   {
				$this->statistic[$key1]["total_answers"] += 1;
				switch( $this->statistic[$key1]["type"] ) {
					case "yesno" : 	if(  $val1 == 1 )  {
										$this->statistic[$key1]["labelyes"]["num_answers"] += 1;	
									}
								   	else  {
										$this->statistic[$key1]["labelno"]["num_answers"] += 1;																																		   
									} 
									break;  
					case "single":	$this->statistic[$key1]["choices"][$val1]["num_answers"] += 1;
									break ;
					case "multi":	
									foreach ( $val1 as $key2 => $val2 )  {
										$this->statistic[$key1]["choices"][$key2]["num_answers"] += 1;						
									}	
									break ;
					case "multi_multi":	
										foreach ( $val1 as $key2 => $val2 )  {
										$this->statistic[$key1]["choices"][$key2]["num_answers"] += 1;						
										$this->statistic[$key1]["choices"][$key2]["total_rate"] += $val2;						
									}	
									break ;														 
					
				}	
			}
		}

		foreach ( $this->statistic as $key => $val ) {
			if ( intval($val["total_answers"]) )  {
				switch( $val["type"] ) {
					case "yesno" :	$percentage = 100 * intval($val["labelyes"]["num_answers"]) / intval($val["total_answers"]) ;
									$this->statistic[$key]["labelyes"]["percentage_answers"] = sprintf("%01.2f", $percentage ) ;	
									$percentage = 100 * intval($val["labelno"]["num_answers"]) / intval($val["total_answers"]) ;		
									$this->statistic[$key]["labelno"]["percentage_answers"] = sprintf("%01.2f", $percentage ) ;	
									break ;
					case "single":	
					case "multi":	foreach( $val["choices"] as $key1 => $val1 )  {
										$percentage = 100 * intval($val["choices"][$key1]["num_answers"]) / intval($val["total_answers"]) ;
										$this->statistic[$key]["choices"][$key1]["percentage_answers"] = sprintf("%01.2f", $percentage ) ;
									}
									break ;	
					case "multi_multi":	foreach( $val["choices"] as $key1 => $val1 )  {
										if( intval($val["choices"][$key1]["num_answers"])  )   {
											$average = intval($val["choices"][$key1]["total_rate"]) / intval($val["choices"][$key1]["num_answers"]) ;
											$this->statistic[$key]["choices"][$key1]["average_rate"] = sprintf("%01.2f", $average ) ;
										}
									}
									break ;										
											
				}
			}
		}	
//debug( $this->statistic, "statystyka" );		
	}			
	
	
	/**
	 * display answer details to open question.
	 */	
	function displayOpenAnswers( $uid = 0, $qid = 0 )	 {
		global $LANG;		
	    $this->calculateQuestionnaireStatistic( $uid );			    
	    $content = '';    	
	    $content .= '<p>'.$LANG->getLL("detailed_answers").'</p>';
		$content .= '<p><b>'.$this->statistic[$qid]["contents"].'</b></p><br><hr>';
		foreach( $this->answerArray as $keyA => $valA )   {
			foreach( $valA["content"] as $keyC => $valC )   {
				if ( $keyC == $qid )  {
					switch( $this->statistic[$keyC]["type"] ) {
						case "open": $lines  = explode ( chr(10),  $valC  );
									$content .= '<p>';
									foreach( $lines as $num => $line )   {
										$content .= htmlspecialchars( $line ).'<br>';
									} 
									$content .= '</p><hr>';
									break ; 
					}
				}
			}                  
		}     		                                  
		return $content;
	}	
	
		

	/**
	 * export questionnaire answer data in csv format.
	 */
	function exportData( $uid = 0 )	{
		global $LANG;		
	    $content = "";
		$this->getQuestionnaire( $uid );
		$this->getAnswers( $uid );	    
	    if ( intval($this->CMD['doExport']))  {


//debug( $this->questionnaire, "ankieta");
//debug( $this->answerArray, "odpowiedzi" );


			$filename = 'questionnaire_'.$uid.'_data.csv';
			$mimeType = 'application/octet-stream';
			Header('Content-Type: '.$mimeType);
			Header('Content-Disposition: attachment; filename='.$filename);

			$decodedArray = array();
//			$content .= '<pre> ';
			$content .= 'answer uid,user id,questionnaire uid,question uid,question id,question type,question label,choice id,choice label,choice value'.chr(10);
//			$content .= ' </pre><br>';
			foreach( $this->answerArray as $keyA => $valA )   {
				$record = array();
				$record["user_id"] = $valA["fe_user_id"];
				$record["answer_id"] = $keyA;				

		   		$record["questionnaire_id"] = $valA["qid"];
				
				foreach( $valA["content"] as $keyC => $valC )   {
					$questionUid = $keyC ;
					$answerValue = $valC ;
					$record["question_uid"] = $keyC ;
					$record["question_id"] = $this->questionnaire["questions"][$record["question_uid"]]["id"];
					
					$record["question_label"] = htmlspecialchars( $this->questionnaire["questions"][$record["question_uid"]]["contents"]) ;					
					$record["question_type"] = $this->questionnaire["questions"][$record["question_uid"]]["type"] ; 
//debug( $questionUid, "id");
//debug( $answerValue, "value" );
					switch ( $this->questionnaire["questions"][$record["question_uid"]]["type"] )  {		
		    			case "yesno" :  $record["choice_id"] = 0 ;
		    							if ( $record["choice_id"] == 1 )  { $record["choice_label"] = $this->questionnaire["questions"][$record["question_uid"]]["labelyes"]; }
		    							else { $record["choice_label"] = $this->questionnaire["questions"][$record["question_uid"]]["labelno"]; }

		    							$record["choice_value"] = $valC ;
		    							$content .= $this->writeRecord( $record );
		    							break ;
		    			case "single" : $record["choice_id"] = $valC ;
		    							$record["choice_label"] = $this->questionnaire["questions"][$record["question_uid"]]["choices"][$valC];
		    							$record["choice_value"] = 1 ;
		    							$content .= $this->writeRecord( $record );
		    							break ;
		    			case "open" : 	$record["choice_id"] = 0 ;

		    							$record["choice_label"] = "";
		    							$record["choice_value"] = $valC ;
		    							$content .= $this->writeRecord( $record );
		    							break ;
		    			case "multi_multi": foreach( $valC as $keyD => $valD )  {
			    			 					$record["choice_id"] = $keyD ;
			    			 					$record["choice_label"] = $this->questionnaire["questions"][$record["question_uid"]]["choices"][$keyD];
			    			 					$record["choice_value"] = $valD ;
												$content .= $this->writeRecord( $record );			    			 				
		    							}
	    								break ;			
	    				case "multi": foreach( $valC as $keyD => $valD )  {
			    			 					$record["choice_id"] = $keyD ;
			    			 					$record["choice_label"] = $this->questionnaire["questions"][$record["question_uid"]]["choices"][$keyD];
			    			 					$record["choice_value"] = 1 ;
												$content .= $this->writeRecord( $record );			    			 				
		    							}
	    								break ;																																																																																																																																																																								    								
		    								    							   		
		    							
					}
				
				}
			}

			echo $content;
			exit ;
		
	    }
	    else {
	    	if( ! count($this->answerArray) )   {
		   		$content = '<div align=center><strong style="color: red">'.$LANG->getLL("no_answer").'</strong></div><br><br>';
	    	}		    
		    else  {
				$content = '<div align=center><strong>'.$LANG->getLL("export_data").'</strong></div><br><br>';
				$content .= '<div align=center><a href="'.htmlspecialchars('index.php?CMD[doExport]=1').'">'.$LANG->getLL("download_data").'</a></div><br><br>';
			}	
			return $content ;
	    }
	    	
	
	}


	/**
	 * write one line of data in csv format.
	 */
	function writeRecord( $record = array() )	{
//		$content = '<pre> ';
		$content = $record["answer_id"] . ',';
		$content .= $record["user_id"] . ',';	
		$content .= $record["questionnaire_id"] . ',';
		$content .= $record["question_uid"] . ',';
		$content .= '"'.$record["question_id"] . '",';
		$content .= '"'. $record["question_type"]. '",';
		$content .= '"'. $record["question_label"] .'",';	
		$content .= $record["choice_id"] . ',';
		$content .= '"'. $record["choice_label"] .'",';
		$content .= '"'. $record["choice_value"] .'"'.chr(10);
//		$content .= ' </pre><br>';
		return $content;	
	}
	

	/**
	 * get questionnaire definition from DB.
	 */
	function getQuestionnaire( $qid = 0 )	{
	    $this->questionnaire = array();
	    if ( intval($qid) != 0 )  {


		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*','tx_questionnaire_questionnaires', 'uid = "'.$qid.'"');   
		if($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))		{
			$this->questionnaire["uid"] = $row["uid"] ;
			$this->questionnaire["pid"] = $row["pid"] ;
			$this->questionnaire["tstamp"] = $row["tstamp"] ;
			$this->questionnaire["crdate"] = $row["crdate"] ;
			$this->questionnaire["cruser_id"] = $row["cruser_id"] ;
			$this->questionnaire["sorting"] = $row["sorting"] ;
			$this->questionnaire["deleted"] = $row["deleted"] ;
			$this->questionnaire["hidden"] = $row["hidden"] ;
			$this->questionnaire["starttime"] = $row["starttime"] ;
			$this->questionnaire["endtime"] = $row["endtime"] ;
			$this->questionnaire["fe_group"] = $row["fe_group"] ;
			$this->questionnaire["title"] = $row["title"] ;
			$this->questionnaire["questions"] = array();
			$questionsTmp = t3lib_div::xml2array( $row["questions"] );
			if( is_array($questionsTmp) )   {
				foreach( $questionsTmp as $key => $val )  {
			    	$this->questionnaire["questions"][$val["uid"]] = $val ;
			   	 if( $val["type"] == "single" || $val["type"] == "multi" || $val["type"] == "multi_multi" ) {
					$choices = explode( chr(10), $val["choices"]) ;
					if( ! is_array ($choices) )  {
				   	$choices = array();
					}
					$this->questionnaire["questions"][$val["uid"]]["choices"] = $choices ;
			    	}
				}
			}
			else return 0;
//debug( $this->questionnaire , "questionnaire");	
			return 1;
		}
		else return 0;	
		
	    }	 
	    else   return 0;
	}



	/**
	 * get questionnaire answers from DB.
	 */
	function getAnswers( $qid = 0 )	{
	    $this->answerArray = array();
	    if ( intval($qid) != 0 )  {

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*','tx_questionnaire_answers', 'qid = "'.$qid.'" AND complete > 0');   
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))		{
			if( $this->userInFilter( $row["fe_user_id"] ))   {
				$this->answerArray[$row["uid"]] = $row ;
				$this->answerArray[$row["uid"]]["content"] = t3lib_div::xml2array( $row["content"] );
			}	
		}
//debug( $this->answerArray , "answer array");		
		if( count($this->answerArray) )  {
		    return 1;
		}
		else {
		    return 0;
		}    
		  
		
		
	    }	 
	    else   return 0;
	}
	

	/**
	 * get username from DB.
	 */
	function getUserName( $uid = 0 )	{	
		global $LANG;	
		if( ! $uid )  {
			return '' ;
		}
		else   {	
			$username = $LANG->getLL("user_nf");

        	$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*','fe_users', 'uid = '.$uid );
            if( $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))   {
            	$username =  $row["username"] ;
            }
            return $username;
    	}    
	 }                        
	

	/**
	 * Check if question answer match filter selection
	 */
	function answerInFilter( $answer =array() )	{
		$filter = $this->CMD["query"];
		if( ! is_array($filter) )
			return 1;
		$InCondition = is_array($this->CMD["inCondition"]) ? $this->CMD["inCondition"] : array();
		$answerContent = $answer["content"];
		$result = 1; // default filter result
		// check each filter condition for each selected question
		foreach( $InCondition as $qid => $flag )  {
			$question = $this->questionnaire["questions"][$qid];
			$condition = $filter[$qid];				
			if( ! isset( $condition ) )  {		
				continue;
			}	
			switch ( $question["type"] )   { 
		  		case "open" :   if( $condition["empty"] ) {
									if( isset($answerContent[$qid]) && ($answerContent[$qid] != '')) {
		  											return 0;
		  							}
		  						}		  		
		  						break ;
		  		case "yesno" :  if( $condition["empty"] ) {
									if( isset($answerContent[$qid])) 
		  								return 0;
		  						}	
								if( $condition["0"] ) {
									if( $answerContent[$qid] != 0 ) 
		  											return 0;
		  									   	break ;
		  						}		 		  							  		
								if( $condition["1"] ) {
									if( $answerContent[$qid] == 0 ) 
		  											return 0;
		  									   	break ;
		  						}		 		  			
		  						break ;
		  		case "single" : $test = 0;
		  						foreach( $condition as $key => $val )  {
		  							if( strval( $key ) == "empty" )   {
										if( ! isset($answerContent)) 
		  									$test = 1;	
		  							}
		  							else {
										if( $answerContent[$qid] == $key ) 
		  									$test = 1;			  								
		  							}
		  						}						
		  						if( ! $test )
		  							return 0;  		
		  						break ;		
		  		case "multi" :  
								$test = 0;
		  						foreach( $condition as $key => $val )  {
		  							if( strval( $key ) == "empty" )   {
										if( ! isset($answerContent[$qid])) 
		  									$test = 1;	
		  							}
		  							else {
		  								foreach( $question["choices"] as $key1 => $val1 )  {			  									
											if( isset( $answerContent[$qid][$key1] ) && ( $key == $key1 ) ) 
		  										$test = 1;			  							
		  								}		
		  							}
		  						}	
		  						if( ! $test )
		  							return 0;  		
		  						break ;	
				case "multi_multi" : 
								$test = 0;  $empty = 1;
		  						foreach( $condition as $key => $val )  {
		  							if( ! isset($val["set"]))
		  								continue;
		  							$empty = 0;
		  							switch( strval( $val["operator"] ))   {		
		  								case "empty" :	if( ! isset($answerContent[$qid][$key])) 
		  													$test = 1;
		  												break ;
		  								case "eq" :		if( intval( $answerContent[$qid][$key] ) == intval($val["value"]))	
		  													$test = 1;		  													
		  												break ;		
		  								case "ne" :		if( intval( $answerContent[$qid][$key] ) != intval($val["value"]))	
		  													$test = 1;		  													
		  												break ;	
		  								case "gt" :		if( intval( $answerContent[$qid][$key] ) > intval($val["value"]))	
		  													$test = 1;		  													
		  												break ;	
		  								case "ge" :		if( intval( $answerContent[$qid][$key] ) >= intval($val["value"]))	
		  													$test = 1;		  													
		  												break ;	
		  								case "lt" :		if( intval( $answerContent[$qid][$key] ) < intval($val["value"]))	
		  													$test = 1;		  													
		  												break ;	
		  								case "le" :		if( intval( $answerContent[$qid][$key] ) <= intval($val["value"]))	
		  													$test = 1;		  													
		  												break ;		  														  														  														  														  														
		  							}
		  						}		  								  						
		  						if( !$test && !$empty )
		  							return 0;  										
									break ;		  							  			  						  						
		  		default: return 0;
			}
		}	
		return $result;
	}	

	
		
	/**
	 * Check if fe_user_id match filter selection
	 */
	function userInFilter( $uid = 0 )	{
		$filter = $this->MOD_SETTINGS["user"];
		$type = substr($filter,0,1);
		$val = intval(substr($filter,1));
		switch ( $type )   {
		  // compare single user_id	
		  case 'u':  return  $uid == $val ? 1 : 0 ;
		  			 break;
		  case 's':  switch( $val )  {
			  			case 0:  return 1;    				// user doesn't matter'
			  					 break ;
			  			case 1:  return  $uid ? 1 : 0 ;		// only if frontend user id exist
			  			case 2:  return  $uid ? 0 : 1 ;		// if no frontend user
			     		default: return 1;
		  	  		 }  	
		  	  		 break;		 	
		  default: return 1;
		}
		
		return 1;
	}	

	
	/**
	 * build get parameters string from CMD table.
	 */
	function getCmdString( $addCmd = array() )	{	
		global $LANG;	
		$getString = '';
		// actual command  
		if( is_array($this->CMD)  )   {
			foreach( $this->CMD as $par => $val )   {
				if( $getString == '' )  {
					$separate = '?';

				}
				else  {
					$separate = '&';	
				}
				if( is_array($val))  {
					$getString .= $separate.'CMD['.$par.']='.urlencode(serialize($val)) ;
				}
				else  {
					$getString .= $separate.'CMD['.$par.']='.$val;	
				}
			}
		}
		// additional command
		if( is_array($addCmd)  )   {
			foreach( $addCmd as $par => $val )   {
				if( $getString == '' )  {
					$separate = '?';
				}
				else  {
					$separate = '&';	
				}
				if( is_array($val))  {
					$getString .= $separate.'CMD['.$par.']='.urlencode(serialize($val)) ;
				}
				else  {
					$getString .= $separate.'CMD['.$par.']='.$val;	
				}
			}
		}		
		return $getString;
	 }         	
	

}

    



if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/questionnaire/mod1/index.php"])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/questionnaire/mod1/index.php"]);
}




// Make instance:
$SOBE = t3lib_div::makeInstance("tx_questionnaire_module1");
$SOBE->init();

// Include files?
foreach($SOBE->include_once as $INC_FILE)	include_once($INC_FILE);

$SOBE->main();
$SOBE->printContent();

?>