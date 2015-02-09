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
 * Create .png image 1x1 in given color
 *
 * @author	Milosz Klosowicz <typo3@miklobit.com>
 */


header ("Content-type: image/png");
$red = $_GET["red"];
$green = $_GET["green"];
$blue = $_GET["blue"];
$im = @imagecreate(1, 1)
     or die("Cannot Initialize new GD image stream");
$background_color = imagecolorallocate($im, $red, $green, $blue);     
imagepng($im);
imagedestroy($im);
?> 
