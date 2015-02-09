/***************************************************************
*
*  Validate questionnaire form
*
* 
*
*
*
*  Copyright notice
*
*  (c) 1998-2003 Kasper Skaarhoj
*  All rights reserved
*
*  This script is part of the TYPO3 t3lib/ library provided by
*  Kasper Skaarhoj <kasper@typo3.com> together with TYPO3
*
*  Released under GNU/GPL (see license file in tslib/)
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*
*  This copyright notice MUST APPEAR in all copies of this script
***************************************************************/
/*
 *  @co-author Milosz Klosowicz <typo3@miklobit.com>
 *  some functions from "javaScript API"  ( www.aspandjavascript.co.uk/javascript ) 
 */


function validateQuestionnaire(theFormname,theFieldlist,goodMess,badMess,emailMess)	{
	if (document[theFormname] && theFieldlist)	{
		var index=1;
		var theField = split(theFieldlist, ",", index);
		var msg="";
		var theEreg = '';
		var theEregMsg = '';
		var specialMode = '';
		var theId = '';  // id of parent container to check visibility before validation

		alert( 'Id = ' + theId );		
		while (theField) {
			theEreg = '';
			specialMode = '';

				// Check special modes:
			if (theField == '_EREG')	{	// EREG mode: _EREG,[error msg],[JS ereg],[fieldname],[field Label]
				specialMode = theField;

				index++;
				theEregMsg = unescape(split(theFieldlist, ",", index));
				index++;
				theEreg = unescape(split(theFieldlist, ",", index));
			} else if (theField == '_EMAIL')	{
				specialMode = theField;
			}

				// Get real field name if special mode has been set:
			if (specialMode)	{
				index++;
				theField = split(theFieldlist, ",", index);
			}

			index++;
			theLabel = unescape(split(theFieldlist, ",", index));
			theField = unescape(theField);
			index++;
			theId = unescape(split(theFieldlist, ",", index));	 		

			
			alert( 'Id = ' + theId );
			var visibility = getObjectVisibility( theId );
			
			if (document[theFormname][theField] && visibility != 'hidden')	{
				var fObj = document[theFormname][theField];
				var type=fObj.type;
				
		
				
				if (!fObj.type)	{
					type="radio";
				}
				var value="";
				switch(type)	{
					case "text":
					case "textarea":
						value = fObj.value;
					break;
					case "select-one":
						if (fObj.selectedIndex>=0)	{
							value = fObj.options[fObj.selectedIndex].value;
						}
					break;
					case "select-multiple":
						var l=fObj.length;
						for (a=0;a<l;a++)	{
							if (fObj.options[a].selected)	{
								 value+= fObj.options[a].value;
							}
						}
					break;
					case "radio":
						var l=fObj.length;
						for (a=0; a<l;a++)	{
							if (fObj[a].checked)	{
								value = fObj[a].value;
							}
						}
					break;
					default:
						value=1;
				}

				switch(specialMode)	{
					case "_EMAIL":
						var theRegEx_notValid = new RegExp("(@.*@)|(\.\.)|(@\.)|(\.@)|(^\.)", "gi");
						var theRegEx_isValid = new RegExp("^.+\@[a-zA-Z0-9\-\.]+\.([a-zA-Z]{2,4}|[0-9]{1,3})$","");
						if (!theRegEx_isValid.test(value))	{	// This part was supposed to be a part of the condition: " || theRegEx_notValid.test(value)" - but I couldn't make it work (Mozilla Firefox, linux) - Anyone knows why?
							msg+="\n"+theLabel+' ('+(emailMess ? unescape(emailMess) : 'Email address not valid!')+')';
						}
					break;
					case "_EREG":
						var theRegEx_isValid = new RegExp(theEreg,"");
						if (!theRegEx_isValid.test(value))	{
							msg+="\n"+theLabel+' ('+theEregMsg+')';
						}
					break;
					default:
						if (!value)	{
							msg+="\n"+theLabel;
						}
				}
			}
			index++;
			theField = split(theFieldlist, ",", index);
		}
		if (msg)	{
			var theBadMess = unescape(badMess);
			if (!theBadMess)	{
				theBadMess = "You must fill in these fields:";
			}
			theBadMess+="\n";
			alert(theBadMess+msg);
			return false;
		} else {
			var theGoodMess = unescape(goodMess);
			if (theGoodMess)	{
				alert(theGoodMess);
			}
			return true;
		}
	}
}

function split(theStr1, delim, index) {
	var theStr = ''+theStr1;
	var lengthOfDelim = delim.length;
	sPos = -lengthOfDelim;
	if (index<1) {index=1;}
	for (a=1; a<index; a++)	{
		sPos = theStr.indexOf(delim, sPos+lengthOfDelim);
		if (sPos==-1)	{return null;}
	}
	ePos = theStr.indexOf(delim, sPos+lengthOfDelim);
	if(ePos == -1)	{ePos = theStr.length;}
	return (theStr.substring(sPos+lengthOfDelim,ePos));
}

/** 
 * The function returns the style object for the three types of browsers.
 * These are the DOM Browsers (MSIE 6+, Netscape 6+, Mozilla, Opera 7+, Konqueror etc), 
 * 	 Internet Explorer 4/5 and Netscape 4.x.
 * The DOM browsers use document.getElementById to reference object, 
 *	 Internet Explorer uses document.all, Netscape 4.x uses document.layers.	
 */

function getStyleObject(objectId) {
    if(document.getElementById && document.getElementById(objectId)) {
	return document.getElementById(objectId).style;
    } else if (document.all && document.all(objectId)) {
	return document.all(objectId).style;
    } else if (document.layers && document.layers[objectId]) {
		return getObjNN4(document,objectId);
    } else {
	return false;
    } 
} 

/** 
 *  The function hides or shows a page element.
 *  show an element:   changeObjectVisibility('myObjectId', 'visible');
 *  hide an element:   changeObjectVisibility('myObjectId', 'hidden'); 
 */




function changeObjectVisibility(objectId, newVisibility) {
    var styleObject = getStyleObject(objectId, document);
    if(styleObject) {
	styleObject.visibility = newVisibility;
	return true;
    } else {
	return false;
    }
} 


/** 
 *  The function returns a page element visibility.
 *   
 */


function getObjectVisibility(objectId) {
    var styleObject = getStyleObject(objectId, document);
    if(styleObject) { 
	  return styleObject.visibility ;
    } else {	    
	  return false;
    }
} 

/** 
 * The function getObjNN4(obj,name) returns the object for "name". It starts the search in "obj"
 * This function is needed to find nested elements in a page.
 * example:  get object for image with ID "objectId", searches the whole document
 *		getObjNN4(document,'objectId');
 */

function getObjNN4(obj,name)
{
	var x = obj.layers;
	var foundLayer;
	for (var i=0;i<x.length;i++)
	{
		if (x[i].id == name)
		 	foundLayer = x[i];
		else if (x[i].layers.length)
			var tmp = getObjNN4(x[i],name);
		if (tmp) foundLayer = tmp;
	}
	return foundLayer;
}



function changeClass(Elem, myClass) {
	var elem;
	if(document.getElementById) {
		var elem = document.getElementById(Elem);
	} else if (document.all){
		var elem = document.all[Elem];
	}
	elem.className = myClass;
}

function changeImage(target, source) {
	var imageObj;
	
	if (ns4) {
		imageObj = getImage(target);
		if (imageObj) imageObj.src = eval(source).src; 
	} else {
		imageObj = eval('document.images.' + target);
		if (imageObj) imageObj.src = eval(source).src; 
	}
}

function changeBGColour(myObject, colour) {
	if (ns4) {
		var obj = getObjNN4(document, myObject);
		obj.bgColor=colour;
	} else {
		var obj = getStyleObject(myObject);
		if (op5) {
			obj.background = colour;	
		} else {
			obj.backgroundColor = colour;
		}	
	}
}