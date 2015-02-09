<?php
/***************************************************************
*  Copyright notice
*  
*  (c) 2004-2005 Drecomm/Miklobit
*
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
 * Plugin 'Questionnaire' for the 'questionnaire' extension.
 *
 * @author  milosz klosowicz (www.miklobit.com) <typo3@miklobit.com> for Drecomm
 */


require_once(PATH_tslib."class.tslib_pibase.php");

class tx_questionnaire_pi1 extends tslib_pibase {
	var $prefixId = "tx_questionnaire_pi1";		// Same as class name
	var $scriptRelPath = "pi1/class.tx_questionnaire_pi1.php";	// Path to this script relative to the extension dir.
	var $extKey = "questionnaire";	// The extension key.

	// variable for  questionnaire form
	var $questionnaire=array();		// questionnaire object from current tt_content record (set by getQuestionnaire() ) 	
	var $questionArray=array();	    // List of questions from questionnaire ( set by getQuestionnaire() )	
	var $questionAttr=array();	    // List of question runtime atrributes	
	var $requiredItems=array();	    // required items on current page
	var $answers=array();			// answers for current questionnaire ( set by getAnswers() or by user input )	
 	var $answersData=array();		// decoded answers from DB                                                                                                                    $answers    	
	var $formData=array();			// data posted by questionnaire form
	var $formUrl="";				// url to handle  form data
	var $pageType="";			// intro/questionnaire/overview
	var $action="";					// user action on the form.		
	var $validationOk = 1 ;			// validation result 0=error

	var $paginalView=0;				// is questionnaire display paged ?
	var $questionPerPage=0;         // question number per page ( paginalView != 0 )
	var $questionStart=0;			// question number the page start with
	var $questionCnt=0;				// total questions per questionnaire
		
	var $config=array(); // local config	
	var $conf=array();  // Setting the TypoScript passed in $conf
        var $displayMode = 1;  // Display form or statistic page

	var $globalMarkerArray=array();	
	var $globalSubpartArray=array();			
	var $templateCode=""; // Content of template file
	
	var $contentId=0;		// tt_content record id
	var $answerVar=""; 		// POST variable with answer data
	var $questionnaireId=0; // questionnaire id from tt_content record 
	
	var $userIdentity="";    // "userId" , "userIp" or  "cookie" 
	var $loginUserId=0;  	// Frontend user logged in
	var $loginUserName="";  // User name
	var $userIp="";  	// User ip    
	var $cookieValue = "";

         // variable for statistic calculation

	var $answerArray = array();          // decoded answer array
	var $statistic = array();		// questionnaire statistic array

		
	/**
	 * Handle questionnaire content record
	 */
	function main($content,$conf)	{
				
		// getting configuration values:
		$this->conf=$conf;

		// Converting flexform data into array:
		$this->pi_initPIflexForm();
//debug( $this->cObj->data["pi_flexform"]  , "flexform" );

		if (strstr($this->cObj->currentRecord,"tt_content"))	{
			// (old) $this->questionnaireId = $this->cObj->data["tx_questionnaire_questionnaire"];
                        $this->questionnaireId = $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'questionnaire');
                        if (  ! isset( $this->questionnaireId ) )   {
                             $this->questionnaireId = 0;
                         }
                        $this->displayMode = $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'display_mode');
                        if (  ! isset( $this->displayMode ) )   {
                             $this->displayMode = 1;
                         }
			$this->contentId = $this->cObj->data["uid"];
//debug( $this->cObj->data["uid"], "tt_content id");
		}					
				
		////debug($this->config,"this->config");//MKL
		
		// template file is fetched. The whole template file from which the various subpart are extracted.
		$this->templateCode = $this->cObj->fileResource($this->conf["templateFile"]);

		
		$this->userIdentity= $this->conf["userIdentity"];
		if( !isset( $this->userIdentity ) )  {
			$this->userIdentity = "userId" ;	
		} 
//debug( $this->userIdentity, "userIdentity" );		
		if( $this->userIdentity == "userId" )   {
			// fe_user 
			if ( $GLOBALS["TSFE"]->loginUser )  {
				if ( $GLOBALS["TSFE"]->fe_user->user )   {
			    	//debug( $GLOBALS["TSFE"]->fe_user->user );
					$this->loginUserId = $GLOBALS["TSFE"]->fe_user->user["uid"];
					$this->loginUserName = $GLOBALS["TSFE"]->fe_user->user["username"];				
				} 				
			}
			if( ! isset($this->loginUserId) )  {
				$content = '<p '.$this->pi_classParam("error").'>Questionnaire: no frontend user logged in</p>';
				return $content ;	
			}				
		}			
//debug( $this->loginUserId, "loginUserId" );	

                // cookie value
		$cookieBaseName = 't3_questionnaire';		
		$cookieFullName = $cookieBaseName.'['.$this->questionnaireId.']';
		if (! isset($GLOBALS["HTTP_COOKIE_VARS"][$cookieBaseName][$this->questionnaireId]))   {		   
		    $this->cookieValue = $GLOBALS['TSFE']->uniqueHash();
		    $cookieExpire = time()+(3600*24*30);   // 30 days since today
		    setcookie($cookieFullName,$this->cookieValue,$cookieExpire);						
		}
		else {
		    $this->cookieValue = $GLOBALS["HTTP_COOKIE_VARS"][$cookieBaseName][$this->questionnaireId]	;
		}

//debug( $this->cookieValue, "cookie value (from header or new)" );
//debug( $this->userIdentity, "identity check method" );
				
		// user ip		
 		$this->userIp = t3lib_div::getIndpEnv('REMOTE_ADDR'); 	
 		//debug( $this->userIp, "userIp" );	

		// extract data from questionnaire form

		$this->answerVar = "answer_".$this->contentId;
		$this->formData = t3lib_div::_GP($this->answerVar);		
		if( is_array( $this->formData[$this->questionnaireId] ))   {
		    $this->formData = $this->formData[$this->questionnaireId] ;
		    //debug( $this->formData,"formData" );
		} 	
		else $this->formData = array(); 
//debug( $this->formData,"form data" );						

		// get questionaire from tt_content		
		$this->getQuestionnaire( $this->questionnaireId );			

		// check if display paged
		$this->questionPerPage = intval($this->questionnaire["per_page"]);
	
		$this->questionCnt = intval(count($this->questionArray));
		if( ($this->questionCnt > $this->questionPerPage) && $this->questionPerPage )   {
			$this->paginalView = 1;
		}
		else {
			$this->paginalView = 0;			
		}		
		$this->questionStart = t3lib_div::_GP("question_start_".$this->contentId);
		if( ! isset( $this->questionStart ))   {
			$this->questionStart = 0;
		} 			
			
		$this->pageType = t3lib_div::_GP("page_type_".$this->contentId);
		if( ! isset( $this->pageType ))   {
			$this->pageType = "";
		} 					
		
		
		$this->formUrl = $this->getLinkUrl($GLOBALS["TSFE"]->id,"");	
		////debug($this->formUrl,"formUrl");//MKL

		
		// javascript to validate required form fields and display/hide dependant questions
		$GLOBALS['TSFE']->additionalHeaderData['JSQuestionnaireValidate'] = '<script type="text/javascript" src="'.t3lib_extMgm::siteRelPath('questionnaire').'pi1/validateform.js"></script>';					
		
			
       
		
		// globally substituted markers
		$this->globalMarkerArray=array();
		$this->globalMarkerArray["###FEUSERNAME###"] =  $this->loginUserName ;
		$this->globalMarkerArray["###FEUSERIP###"] =  $this->userIp ;		
		$this->globalMarkerArray["###QUESTIONNAIRE_UID###"] = $this->questionnaireId ;
		$this->globalMarkerArray["###QUESTIONNAIRE_TITLE###"] = $this->questionnaire["title"] ;
		$this->globalMarkerArray["###QUESTIONNAIRE_HEADER###"] = $this->pi_RTEcssText($this->questionnaire["header"]);  // transform TRE field content
		$this->globalMarkerArray["###FINAL_URL###"] = $this->questionnaire["link"] ;		
		$this->globalMarkerArray["###COMPULSORY###"] = $this->conf["compulsory"];		
		
		
		// Substitute Global Marker Array
		$this->templateCode= $this->cObj->substituteMarkerArrayCached($this->templateCode, $this->globalMarkerArray);


		// global subparts

		$this->globalSubpartArray["###TEMPLATE_INTRO###"] = $this->cObj->getSubpart($this->templateCode, "###TEMPLATE_INTRO###");		
		$this->globalSubpartArray["###TEMPLATE_EDIT###"] = $this->cObj->getSubpart($this->templateCode, "###TEMPLATE_EDIT###");
		$this->globalSubpartArray["###TEMPLATE_OVERVIEW###"] = $this->cObj->getSubpart($this->templateCode, "###TEMPLATE_OVERVIEW###");	
		$this->globalSubpartArray["###TEMPLATE_FINALIZE###"] = $this->cObj->getSubpart($this->templateCode, "###TEMPLATE_FINALIZE###");	


		$this->globalSubpartArray["###TEMPLATE_FILLED###"] = $this->cObj->getSubpart($this->templateCode, "###TEMPLATE_FILLED###");		
		$this->globalSubpartArray["###TEMPLATE_STATISTIC###"] = $this->cObj->getSubpart($this->templateCode, "###TEMPLATE_STATISTIC###");					
		
		$this->globalSubpartArray["###CHOICES_YESNO_EDIT###"] = $this->cObj->getSubpart($this->templateCode, "###CHOICES_YESNO_EDIT###");
		$this->globalSubpartArray["###CHOICES_SINGLE_EDIT###"] = $this->cObj->getSubpart($this->templateCode, "###CHOICES_SINGLE_EDIT###");
		$this->globalSubpartArray["###CHOICES_MULTI_EDIT###"] = $this->cObj->getSubpart($this->templateCode, "###CHOICES_MULTI_EDIT###");
		$this->globalSubpartArray["###CHOICES_MULTI_MULTI_EDIT###"] = $this->cObj->getSubpart($this->templateCode, "###CHOICES_MULTI_MULTI_EDIT###");
		$this->globalSubpartArray["###CHOICES_OPEN_EDIT###"] = $this->cObj->getSubpart($this->templateCode, "###CHOICES_OPEN_EDIT###");
		
		$this->globalSubpartArray["###CHOICES_YESNO_VIEW###"] = $this->cObj->getSubpart($this->templateCode, "###CHOICES_YESNO_VIEW###");
		$this->globalSubpartArray["###CHOICES_SINGLE_VIEW###"] = $this->cObj->getSubpart($this->templateCode, "###CHOICES_SINGLE_VIEW###");
		$this->globalSubpartArray["###CHOICES_MULTI_VIEW###"] = $this->cObj->getSubpart($this->templateCode, "###CHOICES_MULTI_VIEW###");
		$this->globalSubpartArray["###CHOICES_MULTI_MULTI_VIEW###"] = $this->cObj->getSubpart($this->templateCode, "###CHOICES_MULTI_MULTI_VIEW###");
		$this->globalSubpartArray["###CHOICES_OPEN_VIEW###"] = $this->cObj->getSubpart($this->templateCode, "###CHOICES_OPEN_VIEW###");

		

//debug($this->globalMarkerArray,"markerArray");
//debug($this->globalSubpartArray,"subpartArray");

                $answerComplete = 0;

    		// check if user answer  for  this questionnaire exist in DB
		$this->getAnswersFromDb( $this->questionnaireId );
		//debug( $this->answers, "this->answers" );
		if( isset( $this->answers["uid"] ) )  {
		   if( $this->answers["complete"] )  {
                       $answerComplete = 1;
		   }
		} 

                switch( $this->displayMode )    {
                   // Form only   
                   case 1:   if(  $answerComplete )   {   
                                    return $this->pi_wrapInBaseClass($this->alreadyFilled($content,$conf));
                                 }
                                  else   {
                                        return $this->pi_wrapInBaseClass($this->displayQuestionnaireForm($content,$conf));
                                 }
                                 break ;
                    // Statistic only
                   case 2:    return $this->pi_wrapInBaseClass($this->displayQuestionnaireStatistic());
                                  break;
                    // Statistic if questionnaire filled
                   case 3:    if(  $answerComplete )   {
                                      return $this->pi_wrapInBaseClass($this->displayQuestionnaireStatistic());
                                 }
                                 else   {
                                        return $this->pi_wrapInBaseClass($this->displayQuestionnaireForm($content,$conf));
                                  }
                                  break ;
					default:  return "bad display mode !";          

                }
				
	}

	
	/**
	 * Display questionnaire forms 
	 */
	function displayQuestionnaireForm($content,$conf)	{
		
		$this->action = ""; 
		if( t3lib_div::_GP("start") )  {
		   $this->action = "start" ;
		}
                else if( t3lib_div::_GP("save") )  {
		   $this->action = "save" ;
		}
		else if( t3lib_div::_GP("page_up")) {
		  $this->action = "page_up" ;
		}
		else if( t3lib_div::_GP("page_down")) {
		  $this->action = "page_down" ;
		}
		else if( t3lib_div::_GP("overview")) {
		  $this->action = "overview" ;
		}
		else if( t3lib_div::_GP("back")) {
		  $this->action = "back" ;
		}		
			
				
		$this->validationOk = 1 ;
		unset($this->questionAttr);		
				
		if( $this->action != "" && $this->action != "start")   {
		    $this->updateAnswersFromForm();
		    if(  $this->pageType == "questionnaire" )  {			    
		       if( ! $this->validateQuestionnaire() )  {
                           if(  ! $this->questionnaire["intro"]  )   {
                                 $this->action = "";
                            }
                            else  {
		                $this->action = "start";
                            }
		       }	    
		    }   
		} 
		
		switch( $this->action )    {
		  case "overview" : return $this->displayOverview($content,$conf);
		            	    break;
		  case "save" : return $this->saveQuestionnaireToDb($content,$conf);
		                break;
		  case "page_up" : 	return $this->pageUpForm($content,$conf);	 
                            break;
		  case "page_down" : return $this->pageDownForm($content,$conf);
		  					 break;
		  case "back": 	return $this->displayForm($content,$conf);
		                break; 		
		  case "start" : return $this->displayForm($content,$conf);
		                break; 				  					 
		  default :   if(  ! $this->questionnaire["intro"]  )   {	
                                       return $this->displayForm($content,$conf);
                                 }
                                 else {
                                       return $this->displayIntro($content,$conf);
                                 }
		                break; 		
		}		
		
	}	


	/**
	 * Display intro page
	 */
	function displayIntro($content,$conf)	{
		
		$content= "";
		
		$questionnaireItem=array();
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_USER_INT_obj=1;	// Configuring so caching is not expected. This value means that no cHash params are ever set. We do this, because it's a USER_INT object!
		
	    
		// This sets the title of the page for use in indexed search results:
		if ($this->internal["currentRow"]["title"])	$GLOBALS["TSFE"]->indexedDocTitle=$this->internal["currentRow"]["title"];
					
		
		$t = array();
		$t["main"] = $this->globalSubpartArray["###TEMPLATE_INTRO###"] ;
		
		$t["questionnaire"] = $this->cObj->getSubpart( $t["main"], "###QUESTIONNAIRE###");		
		$t["questionnaire_missing"] = $this->cObj->getSubpart( $t["main"], "###QUESTIONNAIRE_MISSING###");	
		$t["button_start"] = $this->cObj->getSubpart( $t["questionnaire"], "###BUTTON_START###");
					
		$main = $t["main"];			
		$questionnaire = "";
		$questionnaire_missing = "";
		
											
		$questionnaireItem = $this->questionnaire; 
				
		if( isset( $questionnaireItem["uid"] ) )  { 
			
		    $questionnaire = $t["questionnaire"];							  	
		    $questionnaire = $this->cObj->substituteMarker( $questionnaire, "###FORM_NAME###", $GLOBALS['TSFE']->uniqueHash() );	
		    $questionnaire = $this->cObj->substituteMarker( $questionnaire, "###FORM_URL###", $this->formUrl );	  
																
			$hidden =	'<input type="hidden" name="page_type_'.$this->contentId.'" value="intro" >';
			$questionnaire = $this->cObj->substituteMarker( $questionnaire, "###HIDDEN_FIELDS###", $hidden );	

			$button_start = "";
						
			$button_start = $t["button_start"] ;
			$button_start = $this->cObj->substituteMarker( $button_start, "###BUTTON_START_NAME###", "start" );				   	
					  	
			$questionnaire = $this->cObj->substituteSubpart( $questionnaire, "###BUTTON_START###", $button_start ); 	
									  
	
		}
		else  {
		    $questionnaire_missing = $t["questionnaire_missing"];
		}
		$main = $this->cObj->substituteSubpart( $main, "###QUESTIONNAIRE###", $questionnaire );
		$main = $this->cObj->substituteSubpart( $main, "###QUESTIONNAIRE_MISSING###", $questionnaire_missing );	
		$content = $main ;	
			
		$this->pi_getEditPanel();

//debug( $content, "content" );
		return $content;
		
	}		
	

	
	/**
	 * Display single questionnaire from tt_content ( template )
	 */
	function displayForm($content,$conf)	{
		
		$content= "";
		
		$questionnaireItem=array();
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_USER_INT_obj=1;	// Configuring so caching is not expected. This value means that no cHash params are ever set. We do this, because it's a USER_INT object!
		
	    
		// This sets the title of the page for use in indexed search results:
		if ($this->internal["currentRow"]["title"])	$GLOBALS["TSFE"]->indexedDocTitle=$this->internal["currentRow"]["title"];
			


		$this->answers["complete"] = 0;
		$this->saveAnswersToDb(); 
		$this->answersData = t3lib_div::xml2array($this->answers["content"]);		
//debug( $this->answersData, "answer data" );		
		
		
		$t = array();
		$t["main"] = $this->globalSubpartArray["###TEMPLATE_EDIT###"] ;
		
		$t["questionnaire"] = $this->cObj->getSubpart( $t["main"], "###QUESTIONNAIRE###");		
		$t["question_list"] = $this->cObj->getSubpart( $t["questionnaire"], "###QUESTION_LIST###");
		$t["validation_message"] = $this->cObj->getSubpart( $t["questionnaire"], "###VALIDATION_MESSAGE###");		
		$t["question"] = $this->cObj->getSubpart( $t["question_list"], "###QUESTION###");
		$t["question_compulsory"] = $this->cObj->getSubpart( $t["question"], "###QUESTION_COMPULSORY###");
		$t["question_visible"] = $this->cObj->getSubpart( $t["question"], "###QUESTION_VISIBLE###");
		$t["question_hidden"] = $this->cObj->getSubpart( $t["question"], "###QUESTION_HIDDEN###");		
		$t["questionnaire_missing"] = $this->cObj->getSubpart( $t["main"], "###QUESTIONNAIRE_MISSING###");	
		$t["button_back"] = $this->cObj->getSubpart( $t["questionnaire"], "###BUTTON_BACK###");
		$t["button_next"] = $this->cObj->getSubpart( $t["questionnaire"], "###BUTTON_NEXT###");	
		$t["button_save"] = $this->cObj->getSubpart( $t["questionnaire"], "###BUTTON_SAVE###");
		$t["page_numbering"] = $this->cObj->getSubpart( $t["questionnaire"], "###PAGE_NUMBERING###");				
					
		$main = $t["main"];			
		$questionnaire = "";
		$questionnaire_missing = "";
		
											
		$questionnaireItem = $this->questionnaire; 
				
		if( isset( $questionnaireItem["uid"] ) )  { 
			
			if( ! isset($this->questionAttr) )  {
				$this->generateQuestionAttr();
			}	
//debug( $this->questionAttr, "attr" );
			$questionnaire = $t["questionnaire"];							  	
		    $questionnaire = $this->cObj->substituteMarker( $questionnaire, "###FORM_NAME###", $GLOBALS['TSFE']->uniqueHash() );	
		    $questionnaire = $this->cObj->substituteMarker( $questionnaire, "###FORM_URL###", $this->formUrl );	  
			
			// list of fieldnames for display and javascript checking
			$requiredItems = array();		
			foreach($this->questionArray as $questionItem)  {	
				if ( ! $this->questionAttr[ $questionItem["uid"]]["hide_global"]  && $questionItem["compulsory"] )  {
					 $requiredItems[ ] = rawurlencode($this->answerVar.'['.$questionnaireItem["uid"].']['.$questionItem["uid"].']');  // fieldname
					 $requiredItems[ ] = rawurlencode($questionItem["contents"]);  											// field content
					 $requiredItems[ ] = rawurlencode('id_'.$questionnaireItem["uid"].'_'.$questionItem["uid"]);  											// field content					 
				}		
			}			
										
			$validateForm = "";
			//$validateForm = ' onsubmit="return validateForm(\''.$formName.'\',\''.implode($requiredItems,',').'\',\''.rawurlencode($this->conf['goodMess']).'\',\''.rawurlencode($this->conf['badMess']).'\',\''.rawurlencode($this->conf['emailMess']).'\')"';
			$questionnaire = $this->cObj->substituteMarker( $questionnaire, "###FORM_VALIDATE###", $validateForm );	
			  			 			
			$validation_message = "";
			if( ! $this->validationOk ) {
				$validation_message = $t["validation_message"];	
			}
			$questionnaire = $this->cObj->substituteSubpart( $questionnaire, "###VALIDATION_MESSAGE###", $validation_message );
			
			$hidden =	'<input type="hidden" name="page_type_'.$this->contentId.'" value="questionnaire" >';
			
			if( $this->paginalView )  {
				$hidden .=	'<input type="hidden" name="question_start_'.$this->contentId.'" value="'.$this->questionStart.'" >';
				$pageOf = intval( floor( intval(count($this->questionArray) ) / intval($this->questionPerPage) ) );
				if( intval(count($this->questionArray)) % intval($this->questionPerPage) )   {
					$pageOf += 1;  
				}	  
				$pageNum = intval( floor( intval($this->questionStart) / intval($this->questionPerPage) )) + 1 ;		
			}	
			$questionnaire = $this->cObj->substituteMarker( $questionnaire, "###HIDDEN_FIELDS###", $hidden );	

						
			$question_list = "";
			foreach($this->questionArray as $questionItem)  {
				if( $this->questionAttr[ $questionItem["uid"] ]["hide_global"] == 1 )   {
					continue ;					
				}	
				$question = $t["question"] ;
				$question_visible = "" ;
				$question_hidden = "" ;
				$id = 'id_'.$this->contentId.'_'.$questionnaireItem["uid"].'_'.$questionItem["uid"];				
				if( $this->questionAttr[ $questionItem["uid"] ]["hide_page"] == 1 )   {  
	    			$question_hidden = $t["question_hidden"];
	    			$question_hidden = $this->cObj->substituteMarker( $question_hidden, "###QUESTION_ELEM_ID###", $id );
				}
				else {
	    			$question_visible = $t["question_visible"];
	    			$question_visible = $this->cObj->substituteMarker( $question_visible, "###QUESTION_ELEM_ID###", $id );						
				}		
				$question = $this->cObj->substituteSubpart( $question, "###QUESTION_VISIBLE###", $question_visible );
				$question = $this->cObj->substituteSubpart( $question, "###QUESTION_HIDDEN###", $question_hidden );				
				$question = $this->cObj->substituteMarker( $question, "###QUESTION_CONTENTS###", $questionItem["contents"] );
				$question = $this->cObj->substituteMarker( $question, "###QUESTION_ID###", $questionItem["id"] );
								
				$question_compulsory = "" ;
				if( $questionItem["compulsory"] )  {
					$question_compulsory = $t["question_compulsory"];	
				}	
				$question = $this->cObj->substituteSubpart( $question, "###QUESTION_COMPULSORY###", $question_compulsory );
				$choices = "";  
				$jsEvents = array();
				switch( $questionItem["type"] )   {
					case "yesno" :	foreach( $this->questionAttr[$questionItem["uid"]]["choices"] as $key => $val )  {
								$jsEvents[$key] = 'onClick="';
								foreach( $val["sel_on"] as $selkey => $selval )  {
								   $id = 'id_'.$this->contentId.'_'.$questionnaireItem["uid"].'_'.$selval;
								   $jsEvents[$key] .= " changeObjectVisibility("."'".$id."','visible'); ";
								}
								foreach( $val["sel_off"] as $selkey => $selval )  {
								   $id = 'id_'.$this->contentId.'_'.$questionnaireItem["uid"].'_'.$selval;
								   $jsEvents[$key] .= " changeObjectVisibility("."'".$id."','hidden'); ";
								}
								$jsEvents[$key] .= ' "';
							}

							$choices = $this->renderYesNo( $content, $questionnaireItem, $questionItem, $this->answersData[$questionItem["uid"]], $jsEvents ) ;	
							break ;
					case "single" : foreach( $this->questionAttr[$questionItem["uid"]]["choices"] as $key => $val )  {
								$jsEvents[$key] = 'onClick="';
								foreach( $val["sel_on"] as $selkey => $selval )  {
								   $id = 'id_'.$this->contentId.'_'.$questionnaireItem["uid"].'_'.$selval;
								   $jsEvents[$key] .= " changeObjectVisibility("."'".$id."','visible'); ";
								}
								foreach( $val["sel_off"] as $selkey => $selval )  {
								   $id = 'id_'.$this->contentId.'_'.$questionnaireItem["uid"].'_'.$selval;
								   $jsEvents[$key] .= " changeObjectVisibility("."'".$id."','hidden'); ";
								}
								$jsEvents[$key] .= ' "';
							}
							$choices = $this->renderSingle( $content, $questionnaireItem, $questionItem, $this->answersData[$questionItem["uid"]], $jsEvents ) ;
							break ;
					case "multi"  :	$choices = $this->renderMulti( $content, $questionnaireItem, $questionItem, $this->answersData[$questionItem["uid"]] ) ;
							break ;
					case "multi_multi" : $choices = $this->renderMultiMulti( $content, $questionnaireItem, $questionItem, $this->answersData[$questionItem["uid"]] ) ;
							break ;
					case "open" :	$choices = $this->renderOpen( $content, $questionnaireItem, $questionItem, $this->answersData[$questionItem["uid"]] ) ;	
							break;
					default :	break;
					}	
				
					$question = $this->cObj->substituteMarker( $question, "###CHOICES###", $choices );		
					$question_list .= $question ;
			}

			$questionnaire = $this->cObj->substituteSubpart( $questionnaire, "###QUESTION_LIST###", $question_list ); 	
			$button_back = "";
			$button_next = "";
			$button_save = "";
			
			

			if( ! $this->paginalView )  {
				if( $questionnaireItem["overview"] ) {


						$button_next = $t["button_next"] ;
						$button_next = $this->cObj->substituteMarker( $button_next, "###BUTTON_NEXT_NAME###", "overview" );				   	
				} 
				else {
						$button_save = $t["button_save"] ;
						$button_save = $this->cObj->substituteMarker( $button_save, "###BUTTON_SAVE_NAME###", "save" );						
				}
					  	
		    }
		    else   {
			    if ( $this->questionStart > 0 )  {
					$button_back = $t["button_back"] ;
					$button_back = $this->cObj->substituteMarker( $button_back, "###BUTTON_BACK_NAME###", "page_up" );				    
				}    
				if (  ($this->questionStart + $this->questionPerPage) >= $this->questionCnt )   {
					if( $questionnaireItem["overview"] ) {
							$button_next = $t["button_next"] ;
							$button_next = $this->cObj->substituteMarker( $button_next, "###BUTTON_NEXT_NAME###", "overview" );				   	
					} 
					else {
							$button_save = $t["button_save"] ;
							$button_save = $this->cObj->substituteMarker( $button_save, "###BUTTON_SAVE_NAME###", "save" );						
					}					 										
				}	
				else  {
						$button_next = $t["button_next"] ;
						$button_next = $this->cObj->substituteMarker( $button_next, "###BUTTON_NEXT_NAME###", "page_down" );											
				}	    
			} 						
			$questionnaire = $this->cObj->substituteSubpart( $questionnaire, "###BUTTON_BACK###", $button_back ); 	
			$questionnaire = $this->cObj->substituteSubpart( $questionnaire, "###BUTTON_NEXT###", $button_next ); 
			$questionnaire = $this->cObj->substituteSubpart( $questionnaire, "###BUTTON_SAVE###", $button_save ); 									  

			$page_numbering = "";
			if( $this->paginalView )  {
				$page_numbering = $t["page_numbering"];
				$page_numbering = $this->cObj->substituteMarker( $page_numbering, "###PAGE_NUM###", $pageNum );
				$page_numbering = $this->cObj->substituteMarker( $page_numbering, "###PAGE_TOTAL###", $pageOf );	
			}
			$questionnaire = $this->cObj->substituteSubpart( $questionnaire, "###PAGE_NUMBERING###", $page_numbering );	
		}
		else  {
		    $questionnaire_missing = $t["questionnaire_missing"];
		}
		$main = $this->cObj->substituteSubpart( $main, "###QUESTIONNAIRE###", $questionnaire );
		$main = $this->cObj->substituteSubpart( $main, "###QUESTIONNAIRE_MISSING###", $questionnaire_missing );	
		$content = $main ;	
			
		$this->pi_getEditPanel();

//debug( $content, "content" );
		return $content;
		
	}		
	
	/**
	 * validate questionnaire on server side and clear answer on hidden questions
	 */
	function validateQuestionnaire()	{
		$this->validationOk = 1;		 
		$this->answersData = t3lib_div::xml2array($this->answers["content"]);		
		$this->generateQuestionAttr();
//debug( $this->answersData, "answersData in validate"  );
//debug( $this->questionAttr, "q. attr");		
		foreach($this->questionArray as $questionItem)  {
			if(  $this->questionAttr[ $questionItem["uid"] ]["hide_page"] == 1  )   {
				 unset( $this->answersData[ $questionItem["uid"]] ) ;	
			}	
			if( ( $this->questionAttr[ $questionItem["uid"] ]["hide_global"] == 0 ) &&
			    ( $this->questionAttr[ $questionItem["uid"] ]["hide_page"] == 0 ) &&
			      $questionItem["compulsory"] )   {
				switch( $questionItem["type"] )  {
					case "yesno":
					case "single":
					case "multi":	if( ! isset( $this->answersData[ $questionItem["uid"] ] ) )   {

										$this->validationOk = 0 ;
									}
									break;
					case "multi_multi":	foreach( $this->questionAttr[ $questionItem["uid"] ]["choices"] as $key => $val )  {
											if( ! isset( $this->answersData[ $questionItem["uid"] ][$key] ) )   {
												$this->validationOk = 0 ;
											}
										}
										break ;		
					case "open":	if( ! isset( $this->answersData[ $questionItem["uid"] ] ) || ($this->answersData[ $questionItem["uid"] ] == "") )   {
										$this->validationOk = 0 ;
									}
									break;												
				}					
			}
		}						
		
		$this->answers["content"] = t3lib_div::array2xml( $this->answersData,'',0,'T3QuestionnaireAnswer',0,array("useNindex" => 1, "useIndexTagForNum" => "A" ));
		$this->answers["complete"] = 0;		
		$this->saveAnswersToDb();		
	    return $this->validationOk ;		
	}	
	
	
	
	/**
	 * scroll questionnaire display one page up and skip empty pages
	 */
	function pageUpForm($content,$conf)	{
		$emptyPage = 0;
		if( $this->paginalView )  {
                     do  {
		        if( ( $this->questionStart - $this->questionPerPage ) >= 0 )   {	
			   $this->questionStart = $this->questionStart - $this->questionPerPage;
			   $emptyPage = $this->generateQuestionAttr();			   
		        }
		        else  {
		             $this->questionStart = 0 ;  	  
	                }	
                    } while (  $emptyPage  && ! $this->isFirstPage()  ) ;  	
	    }  
//debug( $this->isFirstPage(), "is first page ?");
		  return  $this->displayForm($content,$conf);
	}	
	
	



	/**
	 * scroll questionnaire display one page down and skip empty pages
	 */
	function pageDownForm($content,$conf)	{
		$emptyPage = 0;
		if( $this->paginalView )  {
                      do {
	         	  if( ( $this->questionStart + $this->questionPerPage ) < $this->questionCnt )   {	
			         $this->questionStart = $this->questionStart + $this->questionPerPage;
				 $emptyPage = $this->generateQuestionAttr();			   
		          }
                      } while ( $emptyPage  && ! $this->isLastPage() ) ;
                      if(  $emptyPage &&  $this->isLastPage() &&  $this->questionnaire["overview"] )   {
                             return  $this->displayOverview($content,$conf);
                      }
                      else    {
                             return  $this->displayForm($content,$conf);
                      }
	       }  
               else   {
		  return  $this->displayForm($content,$conf);
               }
	}		
	
	/**
	 * check if we are on the first page
	 */
	function isFirstPage()	{		
		if( $this->paginalView )  {
		     return $this->questionStart > 0 ? 0 : 1  ;	
		}
		else  {
		     return 1;  	  
	        }	  	  
	}		


	/**
	 * check if we are on the last page
	 */
	function isLastPage()	{		
		if( $this->paginalView )  {
		  if( ( $this->questionStart + $this->questionPerPage ) < $this->questionCnt )   {	
                       return 0;
		  }
                  else   {
                       return 1;
                   }
	        }
                else  {
		     return 1;
                }  
	}		
	
	/**
	 * Display answers overview form
	 */
	function displayOverview($content,$conf)	{
		
		$questionnaireItem=array();	
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_USER_INT_obj=1;	// Configuring so caching is not expected. This value means that no cHash params are ever set. We do this, because it's a USER_INT object!
	

		// This sets the title of the page for use in indexed search results:
		if ($this->internal["currentRow"]["title"])	$GLOBALS["TSFE"]->indexedDocTitle=$this->internal["currentRow"]["title"];

		$this->answers["complete"] = 0;
		$this->saveAnswersToDb(); 
		$this->answersData = t3lib_div::xml2array($this->answers["content"]);		
		//debug( $answersData, "answersData" );
		

		$content= "";
		$t["main"] = $this->globalSubpartArray["###TEMPLATE_OVERVIEW###"] ;
		$t["question_list"] = $this->cObj->getSubpart( $t["main"], "###QUESTION_LIST###");
		$t["question"] = $this->cObj->getSubpart( $t["question_list"], "###QUESTION###");
		$t["question_compulsory"] = $this->cObj->getSubpart( $t["question"], "###QUESTION_COMPULSORY###");
					
		$main = "";			
		$questionnaireItem = $this->questionnaire; 
						
		if( isset( $questionnaireItem["uid"] ) )  { 

			if( ! isset($this->questionAttr) )  {
				$this->generateQuestionAttr();
			}														

			$main = $t["main"];							  	
		    $main = $this->cObj->substituteMarker( $main, "###FORM_NAME###", $GLOBALS['TSFE']->uniqueHash() );	
		    $main = $this->cObj->substituteMarker( $main, "###FORM_URL###", $this->formUrl );		    		
	
			$hidden =	'<input type="hidden" name="page_type_'.$this->contentId.'" value="overview" >';
			if( $this->paginalView )  {
				  $hidden .=	'<input type="hidden" name="question_start_'.$this->contentId.'" value="'.$this->questionStart.'" >';
			}	
		    $main = $this->cObj->substituteMarker( $main, "###HIDDEN_FIELDS###", $hidden );								

		    $question_list = "";
			foreach($this->questionArray as $questionItem)  {																																																																																																																							if( $hiddenItem )  continue ;					
				if( $this->questionAttr[ $questionItem["uid"] ]["hide_page"] == 1 )   {
					continue ;					
				}	
				$question = $t["question"] ;					
				$question = $this->cObj->substituteMarker( $question, "###QUESTION_ID###", htmlspecialchars( $questionItem["id"] ) );
				$question = $this->cObj->substituteMarker( $question, "###QUESTION_CONTENTS###", htmlspecialchars( $questionItem["contents"] ) );								
				$question_compulsory = "";
				if( $questionItem["compulsory"] )  {
				   $question_compulsory = $t["question_compulsory"];	
				}

				$question = $this->cObj->substituteSubpart( $question, "###QUESTION_COMPULSORY###", $question_compulsory );		

				switch( $questionItem["type"] )  {
					case "yesno" :	$answer = $this->renderYesNoOverview( $content, $questionnaireItem, $questionItem, $this->answersData[$questionItem["uid"]] ) ;
									break;	
					case "single":	$answer = $this->renderSingleOverview( $content, $questionnaireItem, $questionItem, $this->answersData[$questionItem["uid"]] ) ;
									break;										
					case "multi":	$answer = $this->renderMultiOverview( $content, $questionnaireItem, $questionItem, $this->answersData[$questionItem["uid"]] ) ;
									break;					
					case "multi_multi":	$answer = $this->renderMultiMultiOverview( $content, $questionnaireItem, $questionItem, $this->answersData[$questionItem["uid"]] ) ;
									break;		
					case "open":	$answer = $this->renderOpenOverview( $content, $questionnaireItem, $questionItem, $this->answersData[$questionItem["uid"]] ) ;
									break;	
					default:  $answer = "" ;																
				}
				$question = $this->cObj->substituteMarker( $question, "###ANSWER###", $answer );
				$question_list .= $question;	
			}	
			$main = $this->cObj->substituteSubpart( $main, "###QUESTION_LIST###", $question_list );	
			
			$main = $this->cObj->substituteMarker( $main, "###BUTTON_BACK_NAME###", "back" );	
			$main = $this->cObj->substituteMarker( $main, "###BUTTON_SAVE_NAME###", "save" );			
					    
		}
		$content = $main;
		$this->pi_getEditPanel();
		return $content;
		
	}		
	

	/**
	 * Render questionnaire item of type "yesno"  
	 */
	 
	function renderYesNo( $content, $questionnaireItem, $questionItem, $answerItem, $jsEvents )	{
		$content = "" ;
		
		$t["main"] = $this->globalSubpartArray["###CHOICES_YESNO_EDIT###"] ;
		$t["yes"] = $this->cObj->getSubpart( $t["main"], "###ITEM_YES###");
		$t["no"] = $this->cObj->getSubpart( $t["main"], "###ITEM_NO###");				

		$checkedYes = "" ;
		$checkedNo  = "" ; 
		if( isset( $answerItem ))  {	
			if( $answerItem == "1" )  {
				$checkedYes = 'checked' ;	
			}	
			if( $answerItem == "0" )  {
				$checkedNo = 'checked' ;	
			}				       	
		}
		$yes = $t["yes"];
		$yes = $this->cObj->substituteMarker( $yes, "###ITEM_NAME###", $this->answerVar.'['.$questionnaireItem["uid"].']['.$questionItem["uid"].']' );
		$yes = $this->cObj->substituteMarker( $yes, "###ITEM_VALUE###", "1" );
		$yes = $this->cObj->substituteMarker( $yes, "###ITEM_CHECKED###", $checkedYes );
		$yes = $this->cObj->substituteMarker( $yes, "###ITEM_EVENTS###", $jsEvents[1] );
		$yes = $this->cObj->substituteMarker( $yes, "###ITEM_LABEL###", htmlspecialchars($questionItem["labelyes"]) );

		$no = $t["no"];
		$no = $this->cObj->substituteMarker( $no, "###ITEM_NAME###", $this->answerVar.'['.$questionnaireItem["uid"].']['.$questionItem["uid"].']' );
		$no = $this->cObj->substituteMarker( $no, "###ITEM_VALUE###", "0" );
		$no = $this->cObj->substituteMarker( $no, "###ITEM_CHECKED###", $checkedNo );
		$no = $this->cObj->substituteMarker( $no, "###ITEM_EVENTS###", $jsEvents[0] );
		$no = $this->cObj->substituteMarker( $no, "###ITEM_LABEL###", htmlspecialchars($questionItem["labelno"]) );		

		$content = $t["main"] ;
		$content = $this->cObj->substituteSubpart( $content, "###ITEM_YES###", $yes );	
		$content = $this->cObj->substituteSubpart( $content, "###ITEM_NO###", $no );						
		return $content ;				
	}			
	
	
	
	/**
	 * Render answer  on item of type "yesno" 
	 */
	 
	function renderYesnoOverview( $content, $questionnaireItem, $questionItem, $answerItem )	{
		$content = "" ;
		$t["main"] = $this->globalSubpartArray["###CHOICES_YESNO_VIEW###"] ;
		$t["answer_filled"] = $this->cObj->getSubpart( $t["main"], "###ANSWER_FILLED###");
		$t["answer_empty"] = $this->cObj->getSubpart( $t["main"], "###ANSWER_EMPTY###");

		$answer_filled = "";
		$answer_empty = "";		
		if( isset( $answerItem ) )  {
			$answer_filled = $t["answer_filled"] ;
			$label = $answerItem ? htmlspecialchars($questionItem["labelyes"]) : htmlspecialchars($questionItem["labelno"]) ;
			$answer_filled = $this->cObj->substituteMarker( $answer_filled, "###ITEM_LABEL###", $label ); 
		}
		else  {
			$answer_empty = $t["answer_empty"] ;
		}
		
		$content = $t["main"] ;
		$content = $this->cObj->substituteSubpart( $content, "###ANSWER_FILLED###", $answer_filled );
		$content = $this->cObj->substituteSubpart( $content, "###ANSWER_EMPTY###", $answer_empty );					
		return $content ;	
	}				
	
		
	/**
	 * Render questionnaire item of type "single"  
	 */
	 
	function renderSingle( $content, $questionnaireItem, $questionItem, $answerItem, $jsEvents )	{
		$content = "" ;
		
		$t["main"] = $this->globalSubpartArray["###CHOICES_SINGLE_EDIT###"] ;
		$t["rows"] = $this->cObj->getSubpart( $t["main"], "###ROWS###");
		$t["row"] = $this->cObj->getSubpart( $t["main"], "###ROW###");				

		$choices = explode( chr(10), $questionItem["choices"] ); 
			
		if( ! is_array( $choices )  )   {
			return ;	
		}	
		if( isset( $answerItem ) && ($answerItem != "") )  {
			$checkedNum = $answerItem ;          	
		}  else   {
			$checkedNum = -1 ;				
		}	
		$rows = "";		
		foreach( $choices  as $key => $val ) {
		    $row = $t["row"];
		    $row = $this->cObj->substituteMarker( $row, "###ROW_NAME###", $this->answerVar.'['.$questionnaireItem["uid"].']['.$questionItem["uid"].']' );
		    $row = $this->cObj->substituteMarker( $row, "###ROW_VALUE###", $key );
		    $row = $this->cObj->substituteMarker( $row, "###ROW_CHECKED###", ( $checkedNum == $key ? "checked" : "" ) );
		    $row = $this->cObj->substituteMarker( $row, "###ROW_EVENTS###", $jsEvents[$key] );
		    $row = $this->cObj->substituteMarker( $row, "###ROW_LABEL###", htmlspecialchars($val) );

		    $rows .= $row ;
		}
		$content = $t["main"] ;
		$content = $this->cObj->substituteSubpart( $content, "###ROWS###", $rows );					
		return $content ;				
	}		
	

	/**
	 * Render answer on item of type "single"  
	 */
	 
	function renderSingleOverview( $content, $questionnaireItem, $questionItem, $answerItem )	{
		$content = "" ;
		$t["main"] = $this->globalSubpartArray["###CHOICES_SINGLE_VIEW###"] ;
		$t["answer_filled"] = $this->cObj->getSubpart( $t["main"], "###ANSWER_FILLED###");
		$t["answer_empty"] = $this->cObj->getSubpart( $t["main"], "###ANSWER_EMPTY###");

		$answer_filled = "";
		$answer_empty = "";	
		$choices = explode( chr(10), $questionItem["choices"] );			
		if( ! is_array( $choices ) ||  ! isset( $answerItem ) || ($answerItem == "" ))   {
			$answer_empty = $t["answer_empty"] ;
		}
		else  {

			$answer_filled = $t["answer_filled"] ;
			$label = "empty"; 
			foreach( $choices  as $key => $val ) {
				if(  $answerItem == $key  )  {
					$label = htmlspecialchars($val) ;	
				}
			}					
			$answer_filled = $this->cObj->substituteMarker( $answer_filled, "###ITEM_LABEL###",  $label  ); 			
		}
		
		$content = $t["main"] ;
		$content = $this->cObj->substituteSubpart( $content, "###ANSWER_FILLED###", $answer_filled );
		$content = $this->cObj->substituteSubpart( $content, "###ANSWER_EMPTY###", $answer_empty );					
		return $content ;	
	}				
		
	
	/**
	 * Render questionnaire item of type "multi" ( on template ) 
	 */
	 
	function renderMulti( $content, $questionnaireItem, $questionItem, $answerItem )	{
		$content = "" ;
		$t["main"] = $this->globalSubpartArray["###CHOICES_MULTI_EDIT###"] ;
		$t["rows"] = $this->cObj->getSubpart( $t["main"], "###ROWS###");
		$t["row"] = $this->cObj->getSubpart( $t["main"], "###ROW###");				

		$choices = explode( chr(10), $questionItem["choices"] ); 
			
		if( ! is_array( $choices )  )   {
			return ;	
		}	
		if( is_array( $answerItem ))  {
			$checkedNum = $answerItem ;          	
		}  else   {
			$checkedNum = array() ;				
		}	
		$rows = "";		
		foreach( $choices  as $key => $val ) {
		    $row = $t["row"];
		    $row = $this->cObj->substituteMarker( $row, "###ROW_NAME###", $this->answerVar.'['.$questionnaireItem["uid"].']['.$questionItem["uid"].']['.$key.']' );
		    $row = $this->cObj->substituteMarker( $row, "###ROW_VALUE###", "1" );
		    $row = $this->cObj->substituteMarker( $row, "###ROW_CHECKED###", ( $checkedNum[$key]  ? "checked" : "" ) );
		    $row = $this->cObj->substituteMarker( $row, "###ROW_LABEL###", htmlspecialchars($val) );

		    $rows .= $row ;
		}
		$content = $t["main"] ;
		$content = $this->cObj->substituteSubpart( $content, "###ROWS###", $rows );					
		return $content ;				
	}	
	
	
	/**
	 * Render answer on item of type "multi"  
	 */
	 
	function renderMultiOverview( $content, $questionnaireItem, $questionItem, $answerItem )	{
		$content = "" ;
		$t["main"] = $this->globalSubpartArray["###CHOICES_MULTI_VIEW###"] ;
		$t["answer_filled"] = $this->cObj->getSubpart( $t["main"], "###ANSWER_FILLED###");
		$t["answer_empty"] = $this->cObj->getSubpart( $t["main"], "###ANSWER_EMPTY###");
		$t["row"] = $this->cObj->getSubpart( $t["answer_filled"], "###ROW###");						


		$answer_empty = "";

		$answer_filled = "";
		$choices = explode( chr(10), $questionItem["choices"] ); 
		if( ! is_array( $choices ) ||  ! isset( $answerItem ) )   {
			$answer_empty = $t["answer_empty"] ;				
		}	
		else 
		{
			$answer_filled = $t["answer_filled"] ;
			$rows = "";
			foreach( $choices  as $key => $val ) {
				$row = "";	
				if(  isset( $answerItem[$key] )  )  {
					$row = $t["row"] ;
					$row = $this->cObj->substituteMarker( $row, "###ROW_LABEL###", htmlspecialchars($val) );												
				}	
				$rows .= $row ;
			}
			$answer_filled = $this->cObj->substituteSubpart( $answer_filled, "###ROWS###", $rows );							
		}			

		$content = $t["main"] ;
		$content = $this->cObj->substituteSubpart( $content, "###ANSWER_FILLED###", $answer_filled );
		$content = $this->cObj->substituteSubpart( $content, "###ANSWER_EMPTY###", $answer_empty );					
		return $content ;				
	}		
			
	
	/**
	 * Render questionnaire item of type "multi_multi"  
	 */
	 
	function renderMultiMulti( $content, $questionnaireItem, $questionItem, $answerItem )	{
		$content = "" ;
		$t["main"] = $this->globalSubpartArray["###CHOICES_MULTI_MULTI_EDIT###"] ;
		$t["header"] = $this->cObj->getSubpart( $t["main"], "###COL_HEADER###");
		$t["rows"] = $this->cObj->getSubpart( $t["main"], "###ROWS###");
		$t["row"] = $this->cObj->getSubpart( $t["main"], "###ROW###");				
		$t["row_elems"] = $this->cObj->getSubpart( $t["main"], "###ROW_ELEMS###");
		$t["row_elem"] = $this->cObj->getSubpart( $t["main"], "###ROW_ELEM###");		
		$t["header_items"] =  $this->cObj->getSubpart( $t["header"], "###COL_HEADER_ITEMS###");
		$t["header_item"] =  $this->cObj->getSubpart( $t["header_items"], "###COL_HEADER_ITEM###");
		$header_items = ""; 
		for( $i = 1; $i <= $questionItem["cols"] ; $i++ )   {
			$header_item = $this->cObj->substituteMarker( $t["header_item"], "###COL_HEADER_VALUE###", $i );
			$header_items .= $this->cObj->substituteSubpart( $t["header_items"], "###COL_HEADER_ITEM###", $header_item );	
		}	
		$header = $this->cObj->substituteSubpart( $t["header"], "###COL_HEADER_ITEMS###", $header_items );		

		$choices = explode( chr(10), $questionItem["choices"] ); 
			
		if( ! is_array( $choices )  )   {
			return ;	
		}	
		if( is_array( $answerItem ))  {
			$checkedNum = $answerItem ;          	
		}  else   {
			$checkedNum = array() ;				
		}	
		$rows = "";		
		foreach( $choices  as $key => $val ) {
		    $row = $t["row"];
		    $row = $this->cObj->substituteMarker( $row, "###ROW_LABEL###", htmlspecialchars($val) );
		    $row_elems = "" ;				
		    for( $i = 1; $i <= $questionItem["cols"] ; $i++ )   {
		        $row_elem = $t["row_elem"];
			$row_elem = $this->cObj->substituteMarker( $row_elem, "###ROW_ELEM_NAME###", $this->answerVar.'['.$questionnaireItem["uid"].']['.$questionItem["uid"].']['.$key.']' );
			$row_elem = $this->cObj->substituteMarker( $row_elem, "###ROW_ELEM_VALUE###", $i );
			$row_elem = $this->cObj->substituteMarker( $row_elem, "###ROW_ELEM_CHECKED###", ($checkedNum[$key]==$i ? "checked" : "" ) );
			$row_elems .= $row_elem ;
		    }
		    $rows .= $this->cObj->substituteSubpart( $row, "###ROW_ELEMS###", $row_elems ); 				
			
		}
		$content = $t["main"] ;
		$content = $this->cObj->substituteSubpart( $content, "###COL_HEADER###", $header );
		$content = $this->cObj->substituteSubpart( $content, "###ROWS###", $rows );					
		return $content ;				
	}	

		
	/**
	 * Render answer on item of type "multi_multi"   
	 */
	 
	function renderMultiMultiOverview( $content, $questionnaireItem, $questionItem, $answerItem )	{
		$content = "" ;
		$t["main"] = $this->globalSubpartArray["###CHOICES_MULTI_MULTI_VIEW###"] ;
		$t["answer_filled"] = $this->cObj->getSubpart( $t["main"], "###ANSWER_FILLED###");
		$t["answer_empty"] = $this->cObj->getSubpart( $t["main"], "###ANSWER_EMPTY###");
		$t["row_filled"] = $this->cObj->getSubpart( $t["answer_filled"], "###ROW_FILLED###");	

		$t["row_empty"] = $this->cObj->getSubpart( $t["answer_filled"], "###ROW_EMPTY###");						

		$answer_empty = "";
		$answer_filled = "";
		$choices = explode( chr(10), $questionItem["choices"] ); 
		if( ! is_array( $choices ) ||  ! isset( $answerItem ) )   {
			$answer_empty = $t["answer_empty"] ;				
		}	
		else 


		{
			$answer_filled = $t["answer_filled"] ;
			$rows = "";
			foreach( $choices  as $key => $val ) {
				$row_empty = "";
				$row_filled = "";	
				if(  isset( $answerItem[$key] )  )  {
					$row_filled = $t["row_filled"] ;
					$row_filled = $this->cObj->substituteMarker( $row_filled, "###ROW_LABEL###", htmlspecialchars($val) );		
					$row_filled = $this->cObj->substituteMarker( $row_filled, "###ROW_VALUE###", htmlspecialchars( $answerItem[$key]) );										
				}	
				else  {
					$row_empty = $t["row_empty"] ;
					$row_empty = $this->cObj->substituteMarker( $row_empty, "###ROW_LABEL###", htmlspecialchars($val) );	
				}	
				$rows .= $row_filled . $row_empty ;
			}
			$answer_filled = $this->cObj->substituteSubpart( $answer_filled, "###ROWS###", $rows );							
		}			

		$content = $t["main"] ;
		$content = $this->cObj->substituteSubpart( $content, "###ANSWER_FILLED###", $answer_filled );
		$content = $this->cObj->substituteSubpart( $content, "###ANSWER_EMPTY###", $answer_empty );					
		return $content ;				
	}	
	
	
	
	/**
	 * Render questionnaire item of type "open"  
	 */
	 
	function renderOpen( $content, $questionnaireItem, $questionItem, $answerItem )	{
		$content = "" ;
		
		$t["main"] = $this->globalSubpartArray["###CHOICES_OPEN_EDIT###"] ;

		$value = "" ;
		if( isset( $answerItem ))  {
			$value = htmlspecialchars($answerItem) ;
		}
		$out = $t["main"] ;
		$out = $this->cObj->substituteMarker( $out, "###ITEM_NAME###", $this->answerVar.'['.$questionnaireItem["uid"].']['.$questionItem["uid"].']' );
		$out = $this->cObj->substituteMarker( $out, "###ITEM_VALUE###", $value );
		$out = $this->cObj->substituteMarker( $out, "###ITEM_COLS###", $questionItem["cols"] );
		$out = $this->cObj->substituteMarker( $out, "###ITEM_ROWS###", $questionItem["rows"] );

		$content = $out ;						
		return $content ;				
	}				
	

	/**
	 * Render answer  on item of type "open" (template)
	 */
	 
	function renderOpenOverview( $content, $questionnaireItem, $questionItem, $answerItem )	{
		$content = "" ;
		$t["main"] = $this->globalSubpartArray["###CHOICES_OPEN_VIEW###"] ;
		$t["answer_filled"] = $this->cObj->getSubpart( $t["main"], "###ANSWER_FILLED###");
		$t["answer_empty"] = $this->cObj->getSubpart( $t["main"], "###ANSWER_EMPTY###");

		$answer_filled = "";

		$answer_empty = "";		
		if( isset( $answerItem ) && ($answerItem != "") )  {
			$answer_filled = $t["answer_filled"] ;
			$answer_filled = $this->cObj->substituteMarker( $answer_filled, "###ITEM_VALUE###", htmlspecialchars( $answerItem ) ); 
		}
		else  {
			$answer_empty = $t["answer_empty"] ;
		}
		
		$content = $t["main"] ;
		$content = $this->cObj->substituteSubpart( $content, "###ANSWER_FILLED###", $answer_filled );

		$content = $this->cObj->substituteSubpart( $content, "###ANSWER_EMPTY###", $answer_empty );					
		return $content ;	
	}					
	
	
	/**
	 * Generate question runtime atributes table ( label list ) 
	 */	
	
	function generateQuestionAttr()  {

		$this->questionAttr=array();   
		foreach($this->questionArray as $questionItem)  {	
			switch( $questionItem["type"] )   { 
				case "yesno" :  $this->questionAttr[ $questionItem["uid"]]["choices"]["0"]["sel_on"]= array();
								$this->questionAttr[ $questionItem["uid"]]["choices"]["0"]["sel_off"]= array();

								$this->questionAttr[ $questionItem["uid"]]["choices"]["0"]["label"]= $questionItem["labelno"] ;
								$this->questionAttr[ $questionItem["uid"]]["choices"]["1"]["sel_on"]= array();
								$this->questionAttr[ $questionItem["uid"]]["choices"]["1"]["sel_off"]= array();
								$this->questionAttr[ $questionItem["uid"]]["choices"]["1"]["label"]= $questionItem["labelyes"] ;
								break;
				case "single":  
				case "multi" :	$choices = explode( chr(10), $questionItem["choices"] );
								foreach( $choices  as $key => $val )  {
									$this->questionAttr[ $questionItem["uid"]]["choices"][$key]["label"]= $val;
									$this->questionAttr[ $questionItem["uid"]]["choices"][$key]["sel_on"]= array();	
									$this->questionAttr[ $questionItem["uid"]]["choices"][$key]["sel_off"]= array();
								}
								break;
				case "multi_multi" : $choices = explode( chr(10), $questionItem["choices"] );
								foreach( $choices  as $key => $val )  {
									$this->questionAttr[ $questionItem["uid"]]["choices"][$key]["label"]= $val;
									$this->questionAttr[ $questionItem["uid"]]["choices"][$key]["sel_on"]= array();	
									$this->questionAttr[ $questionItem["uid"]]["choices"][$key]["sel_off"]= array();
								}
								break;
				case "open" : 	$this->questionAttr[ $questionItem["uid"]] = array();
						break ;	
 																
			}	
		}
		$questionIdx = 0;	
                $pageEmpty = 1; 	
		foreach($this->questionArray as $questionItem)  {	
			if( $this->paginalView && ( ($questionIdx < $this->questionStart) || ($questionIdx > ($this->questionStart + $this->questionPerPage - 1)) )  )   {
				$this->questionAttr[ $questionItem["uid"] ]["hide_global"] = 1;
				$this->questionAttr[ $questionItem["uid"] ]["hide_page"] = 0;				
			}
			else {
			  	$this->questionAttr[ $questionItem["uid"] ]["hide_global"] = 0;
			  	$this->questionAttr[ $questionItem["uid"] ]["hide_page"] = 0;
			}				  						
				// check if dependent questions are visible/hidden
				if( isset($questionItem["dependant"])  ) {
					foreach( $this->questionArray as $qItem )  {
						if(  ( $questionItem["dependant_id"] == $qItem["uid"] ) ) {
						    $qAttrItem = $this->questionAttr[ $qItem["uid"]]["choices"] ;
						    switch( $qItem["type"] )  {
							case "yesno" : 
							case "single" :
									if ( $questionItem["dependant_choice"] != $this->answersData[$qItem["uid"]] )   {		
									    $this->questionAttr[ $questionItem["uid"] ]["hide_page"] = 1;	
										
									}
									foreach( $qAttrItem as $key => $val )   {
									    if ( $questionItem["dependant_choice"] == $key  )  {
									        $this->questionAttr[ $qItem["uid"]]["choices"][$key]["sel_on"][] = $questionItem["uid"]   ;
									    }
									    else {
										$this->questionAttr[ $qItem["uid"]]["choices"][$key]["sel_off"][] = $questionItem["uid"]   ;
									    }
									}
									break;
							case "multi" :  if ( $this->answersData[$qItem["uid"]][$questionItem["dependant_choice"]] != 1 )  {
										$this->questionAttr[ $questionItem["uid"] ]["hide_page"] = 1;
									}
									foreach( $qAttrItem as $key => $val )   {
									    if ( $questionItem["dependant_choice"] == $key  )  {
									        $this->questionAttr[ $qItem["uid"]]["choices"][$key]["sel_on"][] = $questionItem["uid"]   ;
										$this->questionAttr[ $qItem["uid"]]["choices"][$key]["sel_off"][] = $questionItem["uid"]   ;
									    }					    
									} 
									break ;
						    }			
						}	 
					}		
				}
                        // check if at least one question on page is visible 	
			if( $this->paginalView && ! ( ($questionIdx < $this->questionStart) || ($questionIdx > ($this->questionStart + $this->questionPerPage - 1)) )  )   {
				if (  $this->questionAttr[ $questionItem["uid"] ]["hide_page"] == 0  )   {
                                         $pageEmpty = 0;
                                }
							
			}	
			$questionIdx += 1 ;	
		}	
//debug( $pageEmpty, "page is empty ?" );	
                return $pageEmpty ;		
	}
	
	

	/**
	 * Save  answers record to database 
	 */
	 
	function saveAnswersToDb()	{

		
		if( !isset( $this->answers["uid"] ) )  {
		  $this->answers["crdate"] = time();
		}

		$this->answers["tstamp"] = time();
		switch( $this->userIdentity ) { 
		    case "userId" : $whereString = ' fe_user_id = '.$this->loginUserId ;
				    break ;
		    case "userIp" : $whereString = ' fe_user_ip = "'.$this->userIp.'"' ;
				    break ; 
		    case "cookie" : $whereString = ' cookie = "'.$this->cookieValue.'"' ;
				    break ; 
		}
		$whereString .= " AND qid = ".$this->questionnaireId ;
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_questionnaire_answers', $whereString, $this->answers );		
		
	
	}


	/**
	 * Save Questionnaire answers to database and display final message 
	 */
	 
	function saveQuestionnaireToDb($content,$conf)	{

		$this->answers["complete"] = 1;
		$this->saveAnswersToDb();		

		return $this->displayFinalize($content,$conf);
	
	}

	/**
	 * Display final messages 
	 */
	 
	function displayFinalize($content,$conf)	{
		$content = $this->globalSubpartArray["###TEMPLATE_FINALIZE###"] ;
		return $content;
	
	}


	/**
	 * Update answers array from form data. 
	 */
	 
	function updateAnswersFromForm()	{

		if( !isset( $this->answers["uid"] ) )  {
		  $this->answers["crdate"] = time();
		}		

		$answersTmp = t3lib_div::xml2array($this->answers["content"]);
		if( !is_array($answersTmp) ) $answersTmp = array();	
//debug(	$answersTmp, "answers before update" );		
//debug(	$this->formData, "update data" );		
		reset( $this->formData );
		while( list( $key,$val ) = each( $this->formData ) )   {
		   $answersTmp[$key] = $val ;
		}

 
//debug(	$answersTmp, "answers after update" );
		$this->answers["content"] = t3lib_div::array2xml($answersTmp,'',0,'T3QuestionnaireAnswer',0,array("useNindex" => 1, "useIndexTagForNum" => "A"));		
		//debug($this->answers,"updateAnswersFromForm().answers");				
	}


	/**
	 * Questionnaire already filled 
	 */
	 
	function alreadyFilled($content,$conf)	{
		
		$content = "" ;
		$t["main"] = $this->globalSubpartArray["###TEMPLATE_FILLED###"] ;

		$t["for_user_id"] = $this->cObj->getSubpart( $t["main"], "###FOR_USER_ID###");
		$t["for_user_ip"] = $this->cObj->getSubpart( $t["main"], "###FOR_USER_IP###");
		$t["for_cookie"] = $this->cObj->getSubpart( $t["main"], "###FOR_COOKIE###");

		$for_user_id = "";
		$for_user_ip = "";
		$for_cookie = "";
		switch( $this->userIdentity )  {
		    case "userId" : $for_user_id = $t["for_user_id"] ;
				    break ;	
		    case "userIp" : $for_user_ip = $t["for_user_ip"] ;
				    break ;
		    case "cookie" : $for_cookie = $t["for_cookie"] ;
				    break ;				    
		}
		
		$content = $t["main"] ;
		$content = $this->cObj->substituteSubpart( $content, "###FOR_USER_ID###", $for_user_id );
		$content = $this->cObj->substituteSubpart( $content, "###FOR_USER_IP###", $for_user_ip );					
		$content = $this->cObj->substituteSubpart( $content, "###FOR_COOKIE###", $for_cookie );					
		return $content ;				
	}


	/**
	 * Get  questionnaire record
	 */
	function getQuestionnaire($uid="")	{		
		$this->questionnaire=array();
		// //debug( $uid, "uid" );	
                // //debug( $this->cObj->enableFields("tx_questionnaire_questionnaires") );		
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*','tx_questionnaire_questionnaires', 'uid = '.$uid.' '.$this->cObj->enableFields("tx_questionnaire_questionnaires"));   
		if ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))		{
			$this->questionnaire=$row;
		}
		$this->questionArray=array();		
		if ( isset($this->questionnaire["questions"]) )  {
			$this->questionArray= t3lib_div::xml2array($this->questionnaire["questions"]);		
		}		
	}
	
	/**
	 * Convert  questionnaire definition for statistic calculation ( id -> uid )
	 */
	function convertQuestionnaireData()	{		
	     $questionsTmp = t3lib_div::xml2array( $this->questionnaire["questions"] );
	     $this->questionnaire["questions"] = array();
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
	}
     
		

	/**
	 * Get answers array from database record or create new ( if not exist )
	 */
	function getAnswersFromDb($qid)	{		
		$this->answers=array();
		$where_string = 'qid = '.$qid ;
		switch( $this->userIdentity )  {
		    case "userId" : $where_string .= ' AND fe_user_id = '.$this->loginUserId ;
				    break;
		    case "userIp" : $where_string .= ' AND fe_user_id = 0 AND fe_user_ip = "'.$this->userIp.'"' ; 
				    break;
		    case "cookie" : $where_string .= ' AND fe_user_id = 0 AND cookie = "'.$this->cookieValue.'"' ; 
				    break;
		}
        //debug($where_string, "getAnswersFromDb().wherestring");		
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*','tx_questionnaire_answers', $where_string);   
		if ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))		{
			$this->answers=$row;
			//debug("answer got from db");
		}
		else  {
		  $this->answers = array();
		  $this->answers["tstamp"]= time();
		  $this->answers["crdate"] = time();
		  $this->answers["fe_user_id"] = $this->userIdentity == "userId" ? $this->loginUserId : 0 ;
		  $this->answers["fe_user_ip"] = $this->userIp ;
		  $this->answers["cookie"] = $this->cookieValue ;
		  $this->answers["qid"] = $qid;
		  $this->answers["content"] = t3lib_div::array2xml( array(),'',0,'T3QuestionnaireAnswer',0,array("useNindex" => 1, "useIndexTagForNum" => "A" ));		
   		  $GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_questionnaire_answers', $this->answers );
   		  //debug("new answer created");			

   		  }
	}

	

	/**
	 * Returns a url for use in forms and links
	 */
	function getLinkUrl($id="",$excludeList="")	{
		
		$queryString=array();
		$queryString["id"] = "id=".($id ? $id : $GLOBALS["TSFE"]->id);
		$queryString["type"]= $GLOBALS["TSFE"]->type ? 'type='.$GLOBALS["TSFE"]->type : "";
		$queryString["L"]= t3lib_div::GPvar("L") ? 'L='.t3lib_div::GPvar("L") : "";		
		$queryString["begin_at"]= t3lib_div::GPvar("begin_at") ? 'begin_at='.t3lib_div::GPvar("begin_at") : "";

		reset($queryString);
		while(list($key,$val)=each($queryString))	{
			if (!$val || ($excludeList && t3lib_div::inList($excludeList,$key)))	{
				unset($queryString[$key]);
			}
		}
	
		return $GLOBALS["TSFE"]->absRefPrefix.'index.php?'.implode($queryString,"&");	
	}



	/**
	 * display answer statistic + bar chart (template based).
	 */	
	function displayQuestionnaireStatistic( )	 {

	    $this->calculateQuestionnaireStatistic( $this->questionnaireId );
		$t["main"] = $this->globalSubpartArray["###TEMPLATE_STATISTIC###"] ;
		$t["statistic_list"] = $this->cObj->getSubpart( $t["main"], "###STATISTIC_LIST###");
		$t["statistic_item"] = $this->cObj->getSubpart( $t["statistic_list"], "###STATISTIC_ITEM###");	    
	    
	    $content = $t["main"];
	    $statistic_list = '';
	    foreach ( $this->statistic as $key => $val ) {
			$statistic_list .= $this->displayQuestionStatistic( $key, $t["statistic_item"] );
	    }
	    $content = $this->cObj->substituteSubpart( $content, "###STATISTIC_LIST###", $statistic_list );
	    
	    return $content;	
	}		

	/**
	 * calculate  statistic for questionnaire.
	 */	
	function calculateQuestionnaireStatistic( $uid = 0 )	 {
		$this->statistic = array();
		$this->convertQuestionnaireData( )  ;
		$this->getAnswersForStatistic( $uid );		
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
			$this->statistic[$key]["id"] = $val["id"]; 							
			$this->statistic[$key]["contents"] = $val["contents"] ; 
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
	 * display statistic for one question (template based).

	 */	
	function displayQuestionStatistic( $qid = 0, $template = "" )	 {		
		$bar_max_width = intval( $this->conf["statistic."]["barMaxWidth"] );
		if( ! $bar_max_width )  $bar_max_width = 200 ;  
		$bar_height = intval( $this->conf["statistic."]["barHeight"] );
		if( ! $bar_height )  $bar_height = 10 ;  	
		$bar_yes_color = $this->conf["statistic."]["barYesColor"] ;
		$bar_no_color = $this->conf["statistic."]["barNoColor"] ;		
  		
		
		$t["main"] = $template ;
		$t["rows"] = $this->cObj->getSubpart( $t["main"], "###ROWS###");
		$t["row"] = $this->cObj->getSubpart( $t["rows"], "###ROW###");
		$t["row_total"] = $this->cObj->getSubpart( $t["main"], "###ROW_TOTAL###");		
		
		$total = array();
		$image = t3lib_extMgm::siteRelPath('questionnaire')."mod1/image.php?";
		$total["text"] = 'Total answers';
		$total["value"] = $this->statistic[$qid]["total_answers"];	
		$lines = array();
		switch( $this->statistic[$qid]["type"]  )	{
			case "yesno" :  $lines[0]["text"] = $this->statistic[$qid]["labelyes"]["label"];
							$lines[0]["value"] = $this->statistic[$qid]["labelyes"]["num_answers"];
							$lines[0]["bar_value"] = $this->statistic[$qid]["labelyes"]["percentage_answers"].' %';
							$lines[0]["bar_width"] = $bar_max_width * intval($this->statistic[$qid]["labelyes"]["percentage_answers"]) / 100;
							if( ! $lines[0]["bar_width"] )  $lines[0]["bar_width"] = 1;
							$lines[0]["bar_height"] = $bar_height;
							if( $bar_yes_color != '' )  {
								$lines[0]["image"] = $image.'red='.hexdec(substr($bar_yes_color,0,2)).'&green='.hexdec(substr($bar_yes_color,2,2)).'&blue='.hexdec(substr($bar_yes_color,4,2));
							}
							else  {
								$lines[0]["image"] = $image.'red=0&green=255&blue=0';
							}	
							$lines[1]["text"] = $this->statistic[$qid]["labelno"]["label"];
							$lines[1]["value"] = $this->statistic[$qid]["labelno"]["num_answers"];
							$lines[1]["bar_value"] = $this->statistic[$qid]["labelno"]["percentage_answers"].' %';
							$lines[1]["bar_width"] = $bar_max_width * intval($this->statistic[$qid]["labelno"]["percentage_answers"]) / 100;
							if( ! $lines[1]["bar_width"] )  $lines[1]["bar_width"] = 1;
							$lines[1]["bar_height"] = $bar_height;							
							if( $bar_no_color != '' )  {
								$lines[1]["image"] = $image.'red='.hexdec(substr($bar_no_color,0,2)).'&green='.hexdec(substr($bar_no_color,2,2)).'&blue='.hexdec(substr($bar_no_color,4,2));
							}
							else  {
								$lines[1]["image"] = $image.'red=255&green=0&blue=0';
							}								
//debug( $lines, "linie" );
							break;		
			case "single" : 
			case "multi":	foreach( $this->statistic[$qid]["choices"] as $key => $val )   {
								$lines[$key]["text"] = $val["label"];
								$lines[$key]["value"] = $val["num_answers"];
								$lines[$key]["bar_value"] = $val["percentage_answers"].' %';
								$lines[$key]["bar_width"] = $bar_max_width * intval($val["percentage_answers"]) / 100;
								if( ! $lines[$key]["bar_width"] )  $lines[$key]["bar_width"] = 1;
								$lines[$key]["bar_height"] = $bar_height;								
								$lines[$key]["image"] = $image.'red='.rand(0,255).'&green='.rand(0,255).'&blue='.rand(0,255);
							}
							break;	

			case "multi_multi":	foreach( $this->statistic[$qid]["choices"] as $key => $val )   {
								$lines[$key]["text"] = $val["label"];
								$lines[$key]["value"] = $val["num_answers"];
								$lines[$key]["bar_value"] = $val["average_rate"].' avg.';
								$lines[$key]["bar_width"] = $bar_max_width * intval($val["average_rate"]) / $this->statistic[$qid]["cols"];
								if( ! $lines[$key]["bar_width"] )  $lines[$key]["bar_width"] = 1;
								$lines[$key]["bar_height"] = $bar_height;
								$lines[$key]["image"] = $image.'red='.rand(0,255).'&green='.rand(0,255).'&blue='.rand(0,255);
							}
							break;																												
		}
		$main = $t["main"];		
		$main = $this->cObj->substituteMarker( $main, "###QUESTION_ID###", htmlspecialchars($this->statistic[$qid]["id"]) );		
		$main = $this->cObj->substituteMarker( $main, "###QUESTION_CONTENTS###", htmlspecialchars($this->statistic[$qid]["contents"]) );
		$question_compulsory = '';
		if( $this->statistic[$qid]["compulsory"] )   {
			$question_compulsory = $this->cObj->getSubpart( $t["main"], "###QUESTION_COMPULSORY###");;
		}
		$main = $this->cObj->substituteSubpart( $main, "###QUESTION_COMPULSORY###", $question_compulsory );

		
		$rows = '';
		foreach( $lines as $key => $val )   {
			$row = $t["row"];
			$row = $this->cObj->substituteMarker( $row, "###ROW_TEXT###", htmlspecialchars($val["text"]) );
			$row = $this->cObj->substituteMarker( $row, "###ROW_VALUE###", htmlspecialchars($val["value"]) );
			$row = $this->cObj->substituteMarker( $row, "###BAR_IMAGE###", $val["image"] );			
			$row = $this->cObj->substituteMarker( $row, "###BAR_WIDTH###", $val["bar_width"] );		
			$row = $this->cObj->substituteMarker( $row, "###BAR_HEIGHT###", $val["bar_height"] );								
			$row = $this->cObj->substituteMarker( $row, "###BAR_VALUE###", $val["bar_value"] );
			$rows .= $row ;	
		}
		$main = $this->cObj->substituteSubpart( $main, "###ROWS###", $rows );
		$row_total = $t["row_total"];
		$row_total = $this->cObj->substituteMarker( $row_total, "###TOTAL_TEXT###", htmlspecialchars($total["text"]) );
		$row_total = $this->cObj->substituteMarker( $row_total, "###TOTAL_VALUE###", htmlspecialchars($total["value"]) );
		$main = $this->cObj->substituteSubpart( $main, "###ROW_TOTAL###", $row_total );
		
		return $main;	
        }        

	/**
	 * get questionnaire answers for statistic calculation.
	 */
	function getAnswersForStatistic( $qid = 0 )	{
	    $this->answerArray = array();
	    if ( intval($qid) != 0 )  {

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*','tx_questionnaire_answers', 'qid = "'.$qid.'" AND complete > 0');   
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))		{
			$this->answerArray[$row["uid"]] = $row ;
			$this->answerArray[$row["uid"]]["content"] = t3lib_div::xml2array( $row["content"] );
		}
//debug( $this->answerArray , "answers for statistic");		
		if( count($this->answerArray) )  {
		    return 1;
		}
		else {
		    return 0;
		}    	
	    }	 
	    else   return 0;
	}


}	

if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/questionnaire/pi1/class.tx_questionnaire_pi1.php"])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/questionnaire/pi1/class.tx_questionnaire_pi1.php"]);
}

?>