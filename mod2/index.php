<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2004 Kasper Skaarhoj (kasper@typo3.com)
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

// DEFAULT initialization of a module [BEGIN]
unset($MCONF);
require('conf.php');
require($BACK_PATH.'init.php');

// Force Frame to utf8 charset
$TYPO3_CONF_VARS['BE']['forceCharset'] = 'utf-8';

require(t3lib_extMgm::extPath('llxmltranslate').'mod2/class.sc.php');
$BE_USER->modAccess($MCONF,1);	// This checks permissions and exits if the users has no permission for entry.

// Include language file
$LANG->includeLLFile('EXT:llxmltranslate/mod2/locallang.xml');

// Make instance:
$SOBE = t3lib_div::makeInstance('tx_llxmltranslate_module1');
$SOBE->init();
$SOBE->main();
$SOBE->printContent();
?>