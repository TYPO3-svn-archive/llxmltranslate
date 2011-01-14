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
/**
 * Module 'll-XML' for the 'llxmltranslate' extension.
 *
 * @author	Kasper Skaarhoj <kasper@typo3.com>
 */
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *  100: class tx_llxmltranslate_module1 extends t3lib_SCbase
 *  121:     function init()
 *  130:     function menuConfig()
 *  213:     function main()
 *  232:     function jumpToUrl(URL)
 *  253:     function printContent()
 *  263:     function moduleContent()
 *
 *              SECTION: Rendering
 *  324:     function renderSettings()
 *  355:     function renderTranslate()
 *  386:     function renderExport()
 *  467:     function renderMerge()
 *
 *              SECTION: Create form fields etc.
 *  517:     function createEditForm($relFileRef,$saveStatus=TRUE)
 *  680:     function createEditForm_getItemRow($xmlArray, $relFileRef, $labelKey, $alwaysTextarea=FALSE)
 *  861:     function createEditForm_getItemHead()
 *  891:     function createMergeForm()
 *
 *              SECTION: Saving and getting XML content
 * 1049:     function saveSubmittedData()
 * 1188:     function getXMLdata($fileRef)
 * 1229:     function saveXMLdata($fileRef,$xmlArray, &$saveLog, &$errorLog)
 * 1348:     function createXML($outputArray)
 *
 *              SECTION: Helper functions
 * 1389:     function getllxmlFiles()
 * 1421:     function getllxmlFiles_cached($regenerate=FALSE)
 * 1442:     function loadTranslationStatus($files=FALSE)
 * 1468:     function openCSH($p)
 * 1479:     function llxmlFileInfoBox($xmlArray,$relFileRef)
 *
 * TOTAL FUNCTIONS: 23
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */

// DEFAULT initialization of a module [BEGIN]
unset($MCONF);
require('conf.php');
require($BACK_PATH.'init.php');

// Force Frame to utf8 charset
$TYPO3_CONF_VARS['BE']['forceCharset'] = 'utf-8';

require(t3lib_extMgm::extPath('llxmltranslate').'mod1/class.sc.php');
$BE_USER->modAccess($MCONF,1);	// This checks permissions and exits if the users has no permission for entry.

// Include language file
$LANG->includeLLFile('EXT:llxmltranslate/mod1/locallang.xml');

// Make instance:
$SOBE = t3lib_div::makeInstance('tx_llxmltranslate_module1');
$SOBE->init();
$SOBE->main();
$SOBE->printContent();
?>