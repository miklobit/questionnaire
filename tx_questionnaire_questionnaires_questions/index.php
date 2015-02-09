<?php
/***************************************************************
*  Copyright notice
*
*  (c) 1999-2004 Kasper Skaarhoj (kasper@typo3.com)
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
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * Wizard to help make tx_questionnaire_questionnaires record. 
 *
 * $Id: wizard_forms.php,v 1.14 2004/04/19 20:19:44 typo3 Exp $
 * Revised for TYPO3 3.6 November/2003 by Kasper Skaarhoj
 * XHTML compliant
 *
 * @author	Kasper Skaarhoj <kasper@typo3.com>
 * @author  Milosz Klosowicz (www.miklobit.com) <typo3@miklobit.com> 
 */




unset($MCONF);	
require ("conf.php");
require ($BACK_PATH."init.php");
require ($BACK_PATH."template.php");
$LANG->includeLLFile("EXT:questionnaire/tx_questionnaire_questionnaires_questions/locallang.php");
require_once (PATH_t3lib."class.t3lib_scbase.php"); 



/**
 * Script Class for rendering the Form Wizard
 *
 * @author	Kasper Skaarhoj <kasper@typo3.com>
 * @author	Milosz Klosowicz <typo3@miklobit.com> 
 */
class tx_questionnaire_tx_questionnaire_questionnaires_questionswiz extends t3lib_SCbase {	

		// Internal, dynamic:
	var $doc;					// Document template object
	var $content;				// Content accumulation for the module.
	var $include_once=array();	// List of files to include.
	var $attachmentCounter = 0;	// Used to numerate attachments automatically.


		// Internal, static: GPvars
	var $P;						// Wizard parameters, coming from TCEforms linking to the wizard.
	var $FORMCFG;				// The array which is constantly submitted by the multidimensional form of this wizard.
	var $special;				// Indicates if the form is of a dedicated type, like "formtype_mail" (for tt_content element "Form")






	/**
	 * Initialization the class
	 *
	 * @return	void
	 */
	function init()	{
		global $BACK_PATH,$HTTP_POST_VARS;

			// GPvars:
		$this->P = t3lib_div::_GP('P');
		$this->special = t3lib_div::_GP('special');
		$this->FORMCFG = t3lib_div::_GP('FORMCFG');


		
			// Document template object:
		$this->doc = t3lib_div::makeInstance('mediumDoc');
		$this->doc->docType = 'xhtml_trans';
		$this->doc->backPath = $BACK_PATH;
		$this->doc->JScode=$this->doc->wrapScriptTags('
			function jumpToUrl(URL,formEl)	{	//
				document.location = URL;
			}
		');

			// Setting form tag:
		list($rUri) = explode('#',t3lib_div::getIndpEnv('REQUEST_URI'));
		$this->doc->form ='<form action="'.htmlspecialchars($rUri).'" method="post" name="wizardQuestionnaire">';		

			// Start page:
		$this->content=$this->doc->startPage('Questionnaire Wizard');		

			// If save command found, include tcemain:
		if ($HTTP_POST_VARS['savedok_x'] || $HTTP_POST_VARS['saveandclosedok_x'])	{
			$this->include_once[]=PATH_t3lib.'class.t3lib_tcemain.php';
		}
	}

	/**
	 * Main function for rendering the questionnaire wizard HTML
	 *
	 * @return	void
	 */
	function main()	{
		global $LANG;

		if ($this->P['table'] && $this->P['field'] && $this->P['uid'])	{
			$this->content.=$this->doc->section($LANG->getLL('quest_title'),$this->questionnaireWizard(),0,1);			
		} else {
			$this->content.=$this->doc->section($LANG->getLL('quest_title'),'<span class="typo3-red">'.$LANG->getLL('table_noData',1).'</span>',0,1);			
		}
		$this->content.=$this->doc->endPage();
	}

	/**
	 * Outputting the accumulated content to screen
	 *
	 * @return	void
	 */
	function printContent()	{
		echo $this->content;
	}

	/**
	 * Draws the questionnaire wizard content
	 *
	 * @return	string		HTML content for the form.
	 */
	function questionnaireWizard()	{

			// First, check the references by selecting the record:
		$row=t3lib_BEfunc::getRecord($this->P['table'],$this->P['uid']);
		if (!is_array($row))	{
			t3lib_BEfunc::typo3PrintError ('Wizard Error','No reference to record',0);
			exit;
		}

			// This will get the content of the form configuration code field to us - possibly cleaned up, saved to database etc. if the form has been submitted in the meantime.
		$formCfgArray = $this->getConfigCode($row);

			// Generation of the Form Wizards HTML code:
		$content = $this->getFormHTML($formCfgArray,$row);

			// Return content:
		return $content;
	}











	/****************************
	 *
	 * Helper functions
	 *
	 ***************************/

	/**
	 * Will get and return the configuration code string
	 * Will also save (and possibly redirect/exit) the content if a save button has been pressed
	 *
	 * @param	array		Current parent record row (passed by value!)
	 * @return	array		Configuration Array
	 * @access private
	 */
	function getConfigCode(&$row)	{
		global $HTTP_POST_VARS;

		
			// If some data has been submitted, then construct
		if (isset($this->FORMCFG['c']))	{

				// Process incoming:
			$this->changeFunc();



				// Convert the input array to XML:
			$bodyText = t3lib_div::array2xml($this->FORMCFG['c'],'',0,'T3QuestionnaireWizard');				

					// Setting cfgArr directly from the input:
			$cfgArr = $this->FORMCFG['c'];
				

				// If a save button has been pressed, then save the new field content:
			if ($HTTP_POST_VARS['savedok_x'] || $HTTP_POST_VARS['saveandclosedok_x'])	{

					// Make TCEmain object:
				$tce = t3lib_div::makeInstance('t3lib_TCEmain');
				$tce->stripslashes_values=0;

					// Put content into the data array:
				$data=array();
				$data[$this->P['table']][$this->P['uid']][$this->P['field']]=$bodyText;

					// Perform the update:
				$tce->start($data,array());
				$tce->process_datamap();

					// Re-load the record content:
				$row = t3lib_BEfunc::getRecord($this->P['table'],$this->P['uid']);

					// If the save/close button was pressed, then redirect the screen:
				if ($HTTP_POST_VARS['saveandclosedok_x'])	{
					header('Location: '.t3lib_div::locationHeaderUrl($this->P['returnUrl']));
					exit;
				}
			}
		} else {	// If nothing has been submitted, load the $bodyText variable from the selected database row:
			$cfgArr = t3lib_div::xml2array($row[$this->P['field']]);
			$cfgArr = is_array($cfgArr) ? $cfgArr : array( 0 => array( "uid" => 1, "id" => "", "contents" => "", "type" => "", "required" => "" ));
		}

			// Return configuration code:
		
		return $cfgArr;
	}

	/**
	 * Creates the HTML for the Form Wizard:
	 *
	 * @param	string		Form config array
	 * @param	array		Current parent record array
	 * @return	string		HTML for the form wizard
	 * @access private
	 */
	function getFormHTML($formCfgArray,$row)	{
		global $LANG;

			// Initialize variables:
		$specParts=array();
		$hiddenFields=array();
		$tRows=array();

			// Set header row:
		$cells=array($LANG->getLL('quest_preview',1).':',
						$LANG->getLL('quest_element',1).':',
						$LANG->getLL('quest_config',1).':',
		);
		$tRows[]='
			<tr class="bgColor2" id="typo3-formWizardHeader">
				<td>&nbsp;</td>
				<td>'.implode('</td>
				<td>',$cells).'</td>
			</tr>';

//debug($formCfgArray);			
			// Traverse the number of form elements:
		$k=0;
		foreach($formCfgArray as $confDataKey => $confData)	{
				// Initialize:
			$cells=array();

				// If there is a configuration line which is active, then render it:
			if (!isset($confData['comment'])) {

			

						// Render title/field preview COLUMN
					$cells[]='<strong>'.htmlspecialchars($confData['id']).'. '.htmlspecialchars($confData['contents']).'</strong>';

						// Render general type/title COLUMN:
					$temp_cells=array();

					
						//MKL Render field id	
					$temp_cells[$LANG->getLL('quest_id')]='<input type="text"'.$this->doc->formWidth(5).' name="FORMCFG[c]['.(($k+1)*2).'][id]" value="'.htmlspecialchars($confData['id']).'" />';						
					$hiddenFields[]='<input type="hidden" name="FORMCFG[c]['.(($k+1)*2).'][uid]" value="'.htmlspecialchars($confData['uid']).'" />';						

																		
						// Field type selector:
					$opt=array();
					$opt[]='<option value=""></option>';
					$types = explode(',','yesno,single,multi,multi_multi,open');
					foreach($types as $t)	{	
						$opt[]='<option value="'.$t.'"'.($confData['type']==$t?' selected="selected"':'').'>'.$LANG->getLL('quest_type_'.$t,1).'</option>';									
					}
					$temp_cells[$LANG->getLL('quest_type')]='					
							<select name="FORMCFG[c]['.(($k+1)*2).'][type]">
								'.implode('
								',$opt).'
							</select>';

						// Title field:
						$temp_cells[$LANG->getLL('quest_contents')]='<input type="text"'.$this->doc->formWidth(15).' name="FORMCFG[c]['.(($k+1)*2).'][contents]" value="'.htmlspecialchars($confData['contents']).'" />';


						// Required checkbox:
						$temp_cells[$LANG->getLL('quest_required')]='<input type="checkbox" name="FORMCFG[c]['.(($k+1)*2).'][compulsory]" value="1"'.($confData['compulsory']?' checked="checked"':'').' title="'.$LANG->getLL('quest_required',1).'" />';
						

						//MKL Dependant checkbox
						$temp_cells[$LANG->getLL('quest_dependant')]='<input type="checkbox" name="FORMCFG[c]['.(($k+1)*2).'][dependant]" value="1"'.($confData['dependant']?' checked="checked"':'').' title="'.$LANG->getLL('quest_dependant',1).'" />';

												
						// Put sub-items together into table cell:
					$cells[]=$this->formatCells($temp_cells);


						// Render specific field configuration COLUMN:
					$temp_cells=array();

						// Fieldname

					
						// Field configuration depending on the fields type:
					switch((string)$confData['type'])	{
						case 'yesno':
							$temp_cells[$LANG->getLL('quest_label_yes')]='<input type="text"'.$this->doc->formWidth(8).' name="FORMCFG[c]['.(($k+1)*2).'][labelyes]" value="'.htmlspecialchars($confData['labelyes']).'" title="'.$LANG->getLL('quest_label_yes',1).'" />';
							$temp_cells[$LANG->getLL('quest_label_no')]='<input type="text"'.$this->doc->formWidth(8).' name="FORMCFG[c]['.(($k+1)*2).'][labelno]" value="'.htmlspecialchars($confData['labelno']).'" title="'.$LANG->getLL('quest_label_no',1).'" />';
						break;	
						case 'open':
							$temp_cells[$LANG->getLL('quest_cols')]='<input type="text"'.$this->doc->formWidth(5).' name="FORMCFG[c]['.(($k+1)*2).'][cols]" value="'.htmlspecialchars($confData['cols']).'" title="'.$LANG->getLL('quest_cols',1).'" />';
							$temp_cells[$LANG->getLL('quest_rows')]='<input type="text"'.$this->doc->formWidth(5).' name="FORMCFG[c]['.(($k+1)*2).'][rows]" value="'.htmlspecialchars($confData['rows']).'" title="'.$LANG->getLL('quest_rows',1).'" />';
							$temp_cells[$LANG->getLL('quest_extra')]='<input type="checkbox" name="FORMCFG[c]['.(($k+1)*2).'][extra]" value="OFF"'.($confData['extra']=='OFF'?' checked="checked"':'').' title="'.$LANG->getLL('quest_extra',1).'" />';
						break;
						case 'multi_multi':
							$temp_cells[$LANG->getLL('quest_cols')]='<input type="text"'.$this->doc->formWidth(5).' name="FORMCFG[c]['.(($k+1)*2).'][cols]" value="'.htmlspecialchars($confData['cols']).'" title="'.$LANG->getLL('quest_cols',1).'" />';
						break;	
										
					}

						// Field configuration depending on the fields type:
					if ($confData['type']=='single' || $confData['type']=='multi' || $confData['type']=='multi_multi')	{
						$temp_cells[$LANG->getLL('quest_choices')]='<textarea '.$this->doc->formWidthText(15).' rows="4" name="FORMCFG[c]['.(($k+1)*2).'][choices]" title="'.$LANG->getLL('quest_choices',1).'">'.t3lib_div::formatForTextarea($confData['choices']).'</textarea>';
					} 
					
						//MKL  Dependant on ( parent id)
						
					if ($confData['dependant']=='1')	{
							// Field type selector:
						$opt=array();
						$opt[]='<option value=""></option>';
						$ids=array();
						foreach($formCfgArray as $key => $elem)	{
							if( isset($elem["uid"]) && ( $key < $confDataKey ) ) {
								if( ($elem["uid"] != $confData['uid']) && ( $elem["type"] == "yesno" || $elem["type"] == "single" )) {
									$ids[] = $elem ;
								}		
							}	
						}
							
						foreach($ids as $t)	{	
							$opt[]='<option value="'.$t["uid"].'"'.($confData['dependant_id']==$t["uid"]?' selected="selected"':'').'>'.$t["id"].'</option>';									
						}
						$temp_cells[$LANG->getLL('quest_dependant_id')]='					
							<select name="FORMCFG[c]['.(($k+1)*2).'][dependant_id]">
								'.implode('
								',$opt).'
							</select>';						
						

						//MKL  Dependant on (parent choice)
						
						if ( isset( $confData['dependant_id']) && ( $confData['dependant_id'] != '' ))	{
							$opt=array();
							$opt[]='<option value=""></option>';
							$ids=array();
							foreach($formCfgArray as $elem)	{
								if( isset($elem["uid"]) ) {
									if( $elem["uid"] == $confData['dependant_id'] ) {
										switch( $elem["type"] ) {
										  case "yesno": $ids[] = $elem["labelno"];
										   		$ids[] = $elem["labelyes"];
										  				break;
										  case "single":
										  case "multi": 
												$ids = explode( chr(10),$elem["choices"] );
										  			break;
										
										}
									}		
								}	
							}	
	
							foreach($ids as $key => $val )	{									
								if( ($confData['dependant_choice']) != '' && ($confData['dependant_choice']==$key) ) { $selected = 1; } else { $selected = 0; }
								$opt[]='<option value="'.$key.'"'.($selected?' selected="selected"':'').'>'.$val.'</option>';									
							}
							$temp_cells[$LANG->getLL('quest_dependant_choice')]='					
								<select name="FORMCFG[c]['.(($k+1)*2).'][dependant_choice]">
									'.implode('
									',$opt).'
								</select>';															
							
						}
					} 						
										
					
					
					$cells[]=$confData['type']?$this->formatCells($temp_cells):'';

						// CTRL panel for an item (move up/down/around):
					$ctrl='';
					$onClick="document.wizardForm.action+='#ANC_".(($k+1)*2-2)."';";
					$onClick=' onclick="'.htmlspecialchars($onClick).'"';

					$brTag=$inputStyle?'':'<br />';
					if ($k!=0)	{
						$ctrl.='<input type="image" name="FORMCFG[row_up]['.(($k+1)*2).']"'.t3lib_iconWorks::skinImg($this->doc->backPath,'gfx/pil2up.gif','').$onClick.' title="'.$LANG->getLL('table_up',1).'" />'.$brTag;
					} else {
						$ctrl.='<input type="image" name="FORMCFG[row_bottom]['.(($k+1)*2).']"'.t3lib_iconWorks::skinImg($this->doc->backPath,'gfx/turn_up.gif','').$onClick.' title="'.$LANG->getLL('table_bottom',1).'" />'.$brTag;
					}
					$ctrl.='<input type="image" name="FORMCFG[row_remove]['.(($k+1)*2).']"'.t3lib_iconWorks::skinImg($this->doc->backPath,'gfx/garbage.gif','').$onClick.' title="'.$LANG->getLL('table_removeRow',1).'" />'.$brTag;
					if (($k+1)!=count($tLines))	{
						$ctrl.='<input type="image" name="FORMCFG[row_down]['.(($k+1)*2).']"'.t3lib_iconWorks::skinImg($this->doc->backPath,'gfx/pil2down.gif','').$onClick.' title="'.$LANG->getLL('table_down',1).'" />'.$brTag;
					} else {
						$ctrl.='<input type="image" name="FORMCFG[row_top]['.(($k+1)*2).']"'.t3lib_iconWorks::skinImg($this->doc->backPath,'gfx/turn_down.gif','').$onClick.' title="'.$LANG->getLL('table_top',1).'" />'.$brTag;
					}
					$ctrl.='<input type="image" name="FORMCFG[row_add]['.(($k+1)*2).']"'.t3lib_iconWorks::skinImg($this->doc->backPath,'gfx/add.gif','').$onClick.' title="'.$LANG->getLL('table_addRow',1).'" />'.$brTag;

					$ctrl='<span class="c-wizButtonsV">'.$ctrl.'</span>';

						// Finally, put together the full row from the generated content above:
					$bgC = $confData['type']?' class="bgColor5"':'';
					$tRows[]='
						<tr'.$bgC.'>
							<td><a name="ANC_'.(($k+1)*2).'"></a>'.$ctrl.'</td>
							<td class="bgColor4">'.implode('</td>
							<td valign="top">',$cells).'</td>
						</tr>';
				
			} else {
				$hiddenFields[]='<input type="hidden" name="FORMCFG[c]['.(($k+1)*2).'][comment]" value="'.htmlspecialchars($confData['comment']).'" />';
			}

				// Increment counter:
			$k++;
		}


			// Implode all table rows into a string, wrapped in table tags.
		$content = '

			<!--
				Questions wizard
			-->
			<table border="0" cellpadding="1" cellspacing="1" id="typo3-formwizard">
				'.implode('',$tRows).'
			</table>';

			// Add saving buttons in the bottom:
		$content.= '

			<!--
				Save buttons:
			-->
			<div id="c-saveButtonPanel">';
		$content.= '<input type="image" class="c-inputButton" name="savedok"'.t3lib_iconWorks::skinImg($this->doc->backPath,'gfx/savedok.gif','').' title="'.$LANG->sL('LLL:EXT:lang/locallang_core.php:rm.saveDoc',1).'" />';
		$content.= '<input type="image" class="c-inputButton" name="saveandclosedok"'.t3lib_iconWorks::skinImg($this->doc->backPath,'gfx/saveandclosedok.gif','').' title="'.$LANG->sL('LLL:EXT:lang/locallang_core.php:rm.saveCloseDoc',1).'" />';
		$content.= '<a href="#" onclick="'.htmlspecialchars('jumpToUrl(unescape(\''.rawurlencode($this->P['returnUrl']).'\')); return false;').'">'.
					'<img'.t3lib_iconWorks::skinImg($this->doc->backPath,'gfx/closedok.gif','width="21" height="16"').' class="c-inputButton" title="'.$LANG->sL('LLL:EXT:lang/locallang_core.php:rm.closeDoc',1).'" alt="" />'.
					'</a>';
		$content.= '<input type="image" class="c-inputButton" name="_refresh"'.t3lib_iconWorks::skinImg($this->doc->backPath,'gfx/refresh_n.gif','').' title="'.$LANG->getLL('quest_refresh',1).'" />
			</div>
		';

			// Add hidden fields:
		$content.= implode('',$hiddenFields);

			// Return content:
		return $content;
	}

	/**
	 * Detects if a control button (up/down/around/delete) has been pressed for an item and accordingly it will manipulate the internal FORMCFG array
	 *
	 * @return	void
	 * @access private
	 */
	function changeFunc()	{
		
		if ($this->FORMCFG['row_remove'])	{
			$kk = key($this->FORMCFG['row_remove']);
			$cmd='row_remove';
		} elseif ($this->FORMCFG['row_add'])	{
			$kk = key($this->FORMCFG['row_add']);
			$cmd='row_add';
		} elseif ($this->FORMCFG['row_top'])	{
			$kk = key($this->FORMCFG['row_top']);
			$cmd='row_top';
		} elseif ($this->FORMCFG['row_bottom'])	{
			$kk = key($this->FORMCFG['row_bottom']);
			$cmd='row_bottom';
		} elseif ($this->FORMCFG['row_up'])	{
			$kk = key($this->FORMCFG['row_up']);
			$cmd='row_up';
		} elseif ($this->FORMCFG['row_down'])	{
			$kk = key($this->FORMCFG['row_down']);
			$cmd='row_down';
		}

		if ($cmd && t3lib_div::testInt($kk)) {
			if (substr($cmd,0,4)=='row_')	{
				switch($cmd)	{
					case 'row_remove':
						unset($this->FORMCFG['c'][$kk]);
					break;
					case 'row_add':
						//MKL autoincrement uid field
						$this->FORMCFG['c'][$kk+1]=array();
						$maxuid = 0;
						foreach( $this->FORMCFG['c'] as $item ) {
						   if( $item[uid] > $maxuid ) $maxuid = $item[uid] ;	
						}
						$maxuid += 1;	
						$this->FORMCFG['c'][$kk+1][uid]=$maxuid;
					break;
					case 'row_top':
						$this->FORMCFG['c'][1]=$this->FORMCFG['c'][$kk];
						unset($this->FORMCFG['c'][$kk]);
					break;
					case 'row_bottom':
						$this->FORMCFG['c'][1000000]=$this->FORMCFG['c'][$kk];
						unset($this->FORMCFG['c'][$kk]);
					break;
					case 'row_up':
						$this->FORMCFG['c'][$kk-3]=$this->FORMCFG['c'][$kk];
						unset($this->FORMCFG['c'][$kk]);
					break;
					case 'row_down':
						$this->FORMCFG['c'][$kk+3]=$this->FORMCFG['c'][$kk];
						unset($this->FORMCFG['c'][$kk]);
					break;
				}
				ksort($this->FORMCFG['c']);
			}
		}
	}


	/**
	 * Wraps items in $fArr in table cells/rows, displaying them vertically.
	 *
	 * @param	array		Array of label/HTML pairs.
	 * @return	string		HTML table
	 * @access private
	 */
	function formatCells($fArr)	{

			// Traverse the elements in $fArr and wrap them in table cells:
		$lines=array();
		foreach($fArr as $l => $c)	{
			$lines[]='
				<tr>
					<td nowrap="nowrap">'.htmlspecialchars($l.':').'&nbsp;</td>
					<td>'.$c.'</td>
				</tr>';
		}

			// Add a cell which will set a minimum width:
		$lines[]='
			<tr>
				<td nowrap="nowrap"><img src="clear.gif" width="70" height="1" alt="" /></td>
				<td></td>
			</tr>';

			// Wrap in table and return:
		return '
			<table border="0" cellpadding="0" cellspacing="0">
				'.implode('',$lines).'
			</table>';
	}
}

if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/questionnaire/tx_questionnaire_questionnaires_questions/index.php"])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/questionnaire/tx_questionnaire_questionnaires_questions/index.php"]);
}












// Make instance:
$SOBE = t3lib_div::makeInstance("tx_questionnaire_tx_questionnaire_questionnaires_questionswiz");
$SOBE->init();

// Include files?
foreach($SOBE->include_once as $INC_FILE)	include_once($INC_FILE);

$SOBE->main();
$SOBE->printContent();
?>
