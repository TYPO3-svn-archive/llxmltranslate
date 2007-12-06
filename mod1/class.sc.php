<?php

if (!defined('TYPO3_MODE'))	die('cannot include like that!');

require($BACK_PATH.'template.php');
require_once(PATH_t3lib.'class.t3lib_scbase.php');
require_once(PATH_t3lib.'class.t3lib_diff.php');


/**
 * Module 'll-XML' for the 'llxmltranslate' extension.
 *
 * @author	Kasper Skaarhoj <kasper@typo3.com>
 * @package TYPO3
 * @subpackage tx_llxmltranslate
 */
class tx_llxmltranslate_module1 extends t3lib_SCbase {

		// External, static:
	var $translateDefault = TRUE;

		// Internal, static:
	var $langKeys;			// contains exploded languages string.
	var $files;				// Array of locallang-XML files.

		// Internal, dynamic:
	var $counter = 0;			// Incremented for each table row.
	var $labelStatus = array();		// Array for storing label status
	var $cshLinks = array();		// CSH links
	var $lastImages = array();
	var $lastImages_count = array();

	var $extPathList = array(
				'typo3/sysext/',
				'typo3/ext/',
				'typo3conf/ext/'
			);

	/**
	 * Initialize
	 *
	 * @return	void
	 */
	function init()	{
		parent::init();
	}

	/**
	 * Adds items to the ->MOD_MENU array. Used for the function menu selector.
	 *
	 * @return	void
	 */
	function menuConfig()	{
		global $LANG;

			// Configure menu:
		$this->MOD_MENU = Array (
			'function' => Array (
				'1' => 'Settings',
				'2' => 'Translate files',
				'4' => 'Re-generate cached information',
				'10' => 'Export',
				'11' => 'Merge',
			),
			'editLang' => array(
#				'' => ''
			),
			'fontsize' => array(
				'10px' => '10px',
				'12px' => '12px',
				'14px' => '14px',
				'16px' => '16px',
				'18px' => '18px',
				'20px' => '20px',

				'6pt' => '6pt',
				'8pt' => '8pt',
				'10pt' => '10pt',
				'12pt' => '12pt',
				'14pt' => '14pt',
			)
		);

			// Setting lang-keys:
		$this->langKeys = explode('|',TYPO3_languages);

			// Create checkboxes for language keys / language selector box:
		foreach($this->langKeys as $langKey)	{
			if ($langKey != 'default')	{
				$this->MOD_MENU['addLang_'.$langKey] = '';
			}
			if ($langKey != 'default' || $this->translateDefault)	{
				$this->MOD_MENU['editLang'][$langKey] = $langKey.(' ['.$LANG->sL('LLL:EXT:setup/mod/locallang.php:lang_'.$langKey).']');
			}
		}

			// Forcing a language upon a user (non-admins):
		if (!$GLOBALS['BE_USER']->isAdmin())	{
			$langKey = $GLOBALS['BE_USER']->user['lang'];
			if (!$langKey)	$langKey = 'default';
			$this->MOD_MENU['editLang'] = array();
			$this->MOD_MENU['editLang'][$langKey] = $langKey.(' ['.$LANG->sL('LLL:EXT:setup/mod/locallang.php:lang_'.$langKey).']');
		}

			// Load translation status content:
		$this->loadTranslationStatus();

			// Setting extension
		$this->MOD_MENU['llxml_extlist'] = $this->getExtList();

			// Call parent menu config function:
		parent::menuConfig();

			// Setting files list:
		$this->files = $this->getllxmlFiles_cached();
		$this->MOD_MENU['llxml_files'] = array(''=>'');
		foreach($this->files as $key => $value)	{
			if (substr(basename($value),0,13)!='locallang_csh') {
				$this->MOD_MENU['llxml_files'][$key] = $value;
			}
		}
		$csh_array = array();
		foreach($this->files as $key => $value)	{
			if (substr(basename($value),0,13)=='locallang_csh') {
				$csh_array[$key] = $value;
			}
		}
		if (count($csh_array)) {
			$this->MOD_MENU['llxml_files']['_CSH'] = 'CSH:';
			$this->MOD_MENU['llxml_files'] = array_merge($this->MOD_MENU['llxml_files'], $csh_array);
		}

			// Call parent menu config function:
		parent::menuConfig();

			// Add statistics:
		$editLang = $this->MOD_SETTINGS['editLang'];
		foreach($this->MOD_MENU['llxml_files'] as $key => $value)	{
			if (is_array($this->labelStatus[$editLang][$value]))	{
				$this->MOD_MENU['llxml_files'][$key] = $value.' ['.(count($this->labelStatus[$editLang][$value]['changed'])+count($this->labelStatus[$editLang][$value]['new'])).']';
			}

				// Remove CSH options if not available:
			if (!$this->checkCSH($value))	{
				unset($this->MOD_MENU['llxml_files'][$key]);
			}
		}
	}

	/**
	 * Check if a file is not to be shown because of CSH.
	 *
	 * @param	string		Filename to evaluate.
	 * return boolean	True, if file should be processed.
	 */
	function checkCSH($filename = '') {
		return true;
//		$editLang = $this->MOD_SETTINGS['editLang'];
// 		return !($editLang!='default' && (!$filename || substr(basename($filename),0,13)=='locallang_csh' || substr(basename($filename),0,4)=='CSH:'));
	}

	/**
	 * Main function of the module. Write the content to $this->content
	 *
	 * @return	void
	 */
	function main()	{
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;

			// Draw the header.
		$this->doc = t3lib_div::makeInstance('noDoc');
		$this->doc->backPath = $BACK_PATH;
		$this->doc->form = '<form action="index.php" method="post" name="llxmlform" enctype="'.$GLOBALS['TYPO3_CONF_VARS']['SYS']['form_enctype'].'">';
		$this->doc->docType = 'xhtml_trans';
		$this->doc->inDocStylesArray[] = '
			TABLE#translate-table TR TD, TABLE#translate-table INPUT { font-size: '.$this->MOD_SETTINGS['fontsize'].'; }
			.typo3-green { color: #008000; }
		';

			// FORCING language to "default" and charset to utf-8 (since all locallang-XML files are in UTF-8!)
		$GLOBALS['LANG']->init('default');
		$GLOBALS['LANG']->charSet = 'utf-8';

			// JavaScript
		$this->doc->JScode = $this->doc->wrapScriptTags('
				script_ended = 0;
				function jumpToUrl(URL)	{
					document.location = URL;
				}
		');

		$this->content.=$this->doc->startPage('ll-XML translation');
		$this->content.=$this->doc->header('ll-XML translation');
		$this->content.=$this->doc->section('',t3lib_BEfunc::getFuncMenu('','SET[function]',$this->MOD_SETTINGS['function'],$this->MOD_MENU['function']));
		$this->content.=$this->doc->divider(5);

			// Render content:
		$this->moduleContent();

		$this->content.=$this->doc->spacer(10);
	}

	/**
	 * Prints out the module HTML
	 *
	 * @return	void
	 */
	function printContent()	{
		$this->content.= $this->doc->endPage();
		echo $this->content;
	}

	/**
	 * Generates the module content
	 *
	 * @return	void
	 */
	function moduleContent()	{

			// Getting CSH:
		$csh = t3lib_BEfunc::cshItem('_MOD_txllxmltranslateM1', 'funcmenu_'.$this->MOD_SETTINGS['function'], $this->doc->backPath,'|<br/>');

			// Function menu branching:
		switch((string)$this->MOD_SETTINGS['function'])	{
			case 1:
				$this->content.=$this->doc->section('Settings:',$csh.$this->renderSettings(),0,1);
			break;
			case 2:

					// Saving submitted data:
				$result = $this->saveSubmittedData();
				if ($result)	{
					$this->content.='<h3>SAVING MESSAGES:</h3>'.t3lib_div::view_array($result);
				}

				$this->content.=$this->doc->section('Translate:',$csh.$this->renderTranslate(),0,1);
			break;
			case 4:

					// Re-generating file list:
				$files = $this->getllxmlFiles_cached(TRUE);

					// Re-generate status
				$statInfo = $this->loadTranslationStatus($files);

				$this->content.=$this->doc->section('Re-generate cached content:',
						$csh.
						'Done. <br/>'.
						'Statistics:<br/>'.
						t3lib_div::view_array($statInfo).
						'Select another entry in the menu above! <hr/>'.
						($GLOBALS['BE_USER']->isAdmin() ? t3lib_BEfunc::getFuncMenu('','SET[editLang]',$this->MOD_SETTINGS['editLang'],$this->MOD_MENU['editLang']) : '')
					,0,1);
			break;
			case 10:	// Export
				$this->content.=$this->doc->section('Export:',$csh.$this->renderExport(),0,1);
			break;
			case 11:	// Export
					// Saving submitted data:
				$result = $this->saveSubmittedData();
				if ($result)	{
					$this->content.='<h3>SAVING MESSAGES:</h3>'.t3lib_div::view_array($result);
				}
				$this->content.=$this->doc->section('Merge:',$csh.$this->renderMerge(),0,1);
			break;
		}

			// General notice:
		$this->content.= $this->doc->section('Nightly status','<a href="'.$this->doc->backPath.'../typo3conf/l10n/status.html" target="_blank">typo3conf/l10n/status.html</a>',0,1);

			// General CSH
		$this->content.= t3lib_BEfunc::cshItem('_MOD_txllxmltranslateM1', '', $this->doc->backPath,'|<br/>');
	}












	/*************************
	 *
	 * Rendering
	 *
	 *************************/

	/**
	 * Render "settings" screen
	 *
	 * @return	string		HTML
	 */
	function renderSettings()	{
		global $LANG;

			// Select edit language:
		$out.= '<h3>Select language you want to edit:</h3>'.
				t3lib_BEfunc::getFuncMenu('','SET[editLang]',$this->MOD_SETTINGS['editLang'],$this->MOD_MENU['editLang']);

			// Create checkboxes for additiona languages to show:
		$checkOutput = array();
		foreach($this->langKeys as $langKey)	{
			if ($langKey != 'default')	{
				$checkOutput[] = t3lib_BEfunc::getFuncCheck('','SET[addLang_'.$langKey.']',$this->MOD_SETTINGS['addLang_'.$langKey]).
					' - '.$langKey.(' ['.$LANG->sL('LLL:EXT:setup/mod/locallang.php:lang_'.$langKey).']').'<br/>';
			}
		}
		$out.= '<h3>Additional languages to show during editing:</h3>'.
				implode('',$checkOutput);

			// Select font size:
		$out.= '<h3>Select font size:</h3>'.
				t3lib_BEfunc::getFuncMenu('','SET[fontsize]',$this->MOD_SETTINGS['fontsize'],$this->MOD_MENU['fontsize']);

			// Return output:
		return $out;
	}

	/**
	 * Render translation screen
	 *
	 * @return	string		HTML
	 */
	function renderTranslate()	{

			// Selecting file:
		$style = 'white-space: pre;';
		$selExt = t3lib_BEfunc::getFuncMenu('', 'SET[llxml_extlist]', $this->MOD_SETTINGS['llxml_extlist'], $this->MOD_MENU['llxml_extlist']);
		$selExt = preg_replace('/<option /', '<option style="' . $style . '" ', $selExt);
		$content .= 'Select extension: '. $selExt . '<br />';
		$content .= 'Select file: '.t3lib_BEfunc::getFuncMenu('','SET[llxml_files]',$this->MOD_SETTINGS['llxml_files'],$this->MOD_MENU['llxml_files']) . '<br />';
		$content .= 'Language: '.t3lib_BEfunc::getFuncMenu('','SET[editLang]',$this->MOD_SETTINGS['editLang'],$this->MOD_MENU['editLang']);

		if ($this->files[$this->MOD_SETTINGS['llxml_files']])	{
			$formcontent = '';

				// Defining file and getting content:
			$file = $this->files[$this->MOD_SETTINGS['llxml_files']];
			$formcontent.= $this->createEditForm($file);

				// Put form together:
			$content.= '<br/>'.
				t3lib_BEfunc::cshItem('_MOD_txllxmltranslateM1', 'funcmenu_2_saving', $this->doc->backPath,'|<br/>').'
				<input type="submit" name="_save" value="Save" /><br/>
				Update all values (even if not changed): <input type="checkbox" name="updateAllValues" value="1" /><hr/>
				'.
				$formcontent.'

				<input type="submit" name="_save" value="Save" />
				';
		}

		return $content;
	}

	/**
	 * Render export screen
	 *
	 * @return	string		HTML
	 */
	function renderExport()	{

			// Adding language selector:
		$content.='Language: '.t3lib_BEfunc::getFuncMenu('','SET[editLang]',$this->MOD_SETTINGS['editLang'],$this->MOD_MENU['editLang']);

		if (!t3lib_div::_POST('_export'))	{
				// Create file selector box:
			$opt = array();
			$opt[] = '<option></option>';

				// All non-CSH:
			foreach($this->files as $fN)	{
				if (!t3lib_div::isFirstPartOfStr(basename($fN),'locallang_csh'))	{
					$opt[] = '<option value="'.htmlspecialchars($fN).'">'.htmlspecialchars($fN).'</option>';
				}
			}

			if ($this->checkCSH(''))	{
					// All CSH:
				$opt[] = '<option value=""></option>';
				$opt[] = '<option value="">CSH:</option>';
				foreach($this->files as $fN)	{
					if (t3lib_div::isFirstPartOfStr(basename($fN),'locallang_csh'))	{
						$opt[] = '<option value="'.htmlspecialchars($fN).'">'.htmlspecialchars($fN).'</option>';
					}
				}
			}

			$content.='
				<br/>
				<select name="filesToExport[]" multiple="multiple" size="20">
					'.implode('
					',$opt).'
				</select>
				<br/>
				<input type="submit" name="_export" value="Export" />
			';

			return $content;
		} else {	// Exporting:

				// Files to export:
			$exportFiles = t3lib_div::_POST('filesToExport');

				// Traverse files, read content and compile file:
			if (is_array($exportFiles))	{
				$outputArray = array();
				$outputArray['meta']['language'] = $this->MOD_SETTINGS['editLang'];
				$outputArray['files'] = array();

				foreach($exportFiles as $fileRef)	{
					if (in_array($fileRef, $this->files))	{
						if (t3lib_div::getFileAbsFileName(PATH_site.$fileRef) && @is_file(PATH_site.$fileRef))	{
							$dat = $this->getXMLdata($fileRef);
							if (is_array($dat))	{
								$outputArray['files'][$fileRef] = array();
								if (is_array($dat['data'][$this->MOD_SETTINGS['editLang']]))	{
									$outputArray['files'][$fileRef] = $dat['data'][$this->MOD_SETTINGS['editLang']];
									$outputArray['orig_hash'][$fileRef] = $dat['orig_hash'][$this->MOD_SETTINGS['editLang']];
								}
							} else die('File '.$fileRef.' offered no XML output!');
						} else die('File '.$fileRef.' was invalid or did not exist!');
					}
				}

					// Let user save file:
				$mimeType = 'application/octet-stream';
				$fileName = 'llxml_merge.'.$this->MOD_SETTINGS['editLang'].'.'.date('YmdHis').'.ser';
				$output = base64_encode(serialize($outputArray));
				$output = md5($output).':'.$output;

				Header('Content-Type: '.$mimeType);
				Header('Content-Disposition: attachment; filename='.$fileName);
				echo $output;
				exit;
			}
		}
	}

	/**
	 * Render merge screen
	 *
	 * @return	string		HTML
	 */
	function renderMerge()	{

			// Adding language selector:
		$content.='Language: '.t3lib_BEfunc::getFuncMenu('','SET[editLang]',$this->MOD_SETTINGS['editLang'],$this->MOD_MENU['editLang']);

		if (!t3lib_div::_POST('_uploaded'))	{

				// Create selector for files:
			$opt = array();
			$opt[] = '<option></option>';
			foreach($this->MOD_MENU['llxml_files'] as $fileRef => $label)	{
				$opt[] = '<option value="'.htmlspecialchars($fileRef).'">'.htmlspecialchars($label).'</option>';
			}
				// Put form together:
			$content.= '
				<br/>
					Upload merge-file: <input type="file" size="60" name="upload_merge_file" /><br/>
					Select specific file: <select name="specFile">'.implode('',$opt).'</select><br/>
					Show only which files are inside: <input type="checkbox" name="showFileIndexOnly" value="1" /><br/>
					<input type="submit" name="_uploaded" value="Upload merge-file" />
				';
		} else {
			$content.= $this->createMergeForm();
		}

		return $content;
	}










	/*************************
	 *
	 * Create form fields etc.
	 *
	 *************************/

	/**
	 * Creates table with field for a single file
	 *
	 * @param	string		File reference of a locallang-XML file (relative to PATH_site)
	 * @param	boolean		IF set, the statitics for the file is saved to session
	 * @return	string		HTML content
	 */
	function createEditForm($relFileRef,$saveStatus=TRUE)	{
		global $BE_USER, $TCA;

			// Read file, parse XML:
		$xmlArray = $this->getXMLdata($relFileRef);

		if (is_array($xmlArray))	{

				// Initialize status arrays:
			$editLang = $this->MOD_SETTINGS['editLang'];
			$this->labelStatus[$editLang][$relFileRef] = array(
				'ok' => array(),
				'changed' => array(),
				'unknown' => array(),
				'new' => array(),
			);

				// Create table header:
			$itemRow = $this->createEditForm_getItemHead();

				// Traverse default labels and create form field for each one:
			if (is_array($xmlArray['data']['default']))	{
					// Check for CSH
				if ($xmlArray['meta']['type'] == 'CSH')	{
						// First, all keys from "default" is traversed and "main-keys" (before a ".") is found:
					$allKeys = array_keys($xmlArray['data']['default']);
					$mainKeys = array();
					foreach($allKeys as $key)	{
						$keyParts = explode('.',ereg_replace('^_','',$key));
						$mainKeys[] = $keyParts[0];
					}
					$mainKeys = array_unique($mainKeys);

						// Traverse main keys:
					$allKeys = array_flip($allKeys);
					foreach($mainKeys as $mKey)	{
							// Header for CSH item.
						$itemRow.= '
							<tr>
								<td colspan="4" bgcolor="#ff6600"><a href="#" onclick="'.htmlspecialchars($this->openCSH($xmlArray['meta']['csh_table'].'.'.$mKey)).'"><b>'.
									$mKey.
									'</b></a>&nbsp;</td>
							</tr>';

							// All standard field names:
						$fieldNames = array(
							(!isset($TCA[$xmlArray['meta']['csh_table']]) ? $mKey.'.alttitle' : ''),
							$mKey.'.description',
							$mKey.'.details',
							$mKey.'.syntax',
							'_'.$mKey.'.seeAlso',
							'_'.$mKey.'.image',
							$mKey.'.image_descr',
						);

						if ($xmlArray['meta']['csh_table'])	{
							$valueToAdd =
								$xmlArray['meta']['csh_table'].
								($mKey ? ':'.$mKey : '');
							if (!in_array($valueToAdd,$this->labelStatus['_CSH_references']))	{
								$this->labelStatus['_CSH_references'][] = $valueToAdd;
							}
						}

						foreach($fieldNames as $lkName)	{
							if ($lkName && $editLang=='default' || (substr($lkName,0,1)!='_' && isset($xmlArray['data']['default'][$lkName])))	{
								if (substr($lkName,-7)!='.syntax' || $xmlArray['data']['default'][$lkName])	{	// Here we will hide the (very rarely used) "syntax" field if there is no content in it...
									$itemRow.= $this->createEditForm_getItemRow($xmlArray,$relFileRef,$lkName, TRUE);
								}
							}
							unset($allKeys[$lkName]);
						}
					}

						// Remaining elements in key:
					if (count($allKeys))	{
						$itemRow.= '
							<tr>
								<td colspan="4" bgcolor="#ff6600"><b>REMAINING:</b></td>
							</tr>';
						foreach($allKeys as $remKey => $temp)	{
							$itemRow.= $this->createEditForm_getItemRow($xmlArray,$relFileRef,$remKey);
						}
					}
				} else {	// Normal:
					foreach($xmlArray['data']['default'] as $labelKey => $labelValue)	{
						if ($editLang=='default' || substr($labelKey,0,1)!='_')	{
							$itemRow.= $this->createEditForm_getItemRow($xmlArray,$relFileRef,$labelKey);
						}
					}
				}
			}

				// Update label status:
			$this->labelStatus['_FILETYPE'][$relFileRef] = $xmlArray['meta']['type'];
			if ($saveStatus)	$BE_USER->setAndSaveSessionData('tx_llxmltranslate:status', $this->labelStatus);

				// Compile table:
			$output = '
			'.t3lib_BEfunc::cshItem('_MOD_txllxmltranslateM1', 'funcmenu_2_fileinfo', $this->doc->backPath,'|<br/>').
			$this->llxmlFileInfoBox($xmlArray,$relFileRef).'

			'.t3lib_BEfunc::cshItem('_MOD_txllxmltranslateM1', 'funcmenu_2_stat', $this->doc->backPath,'|<br/>').'

			<!-- STATUS: -->
			<table border="0" cellpadding="1" cellspacing="1" style="border: 1px solid black;">
				<tr bgcolor="#009900">
					<td><b>OK</b></td>
					<td>'.htmlspecialchars(count($this->labelStatus[$editLang][$relFileRef]['ok'])).'</td>
				</tr>
				<tr bgcolor="#6666ff">
					<td><b>New</b></td>
					<td>'.htmlspecialchars(count($this->labelStatus[$editLang][$relFileRef]['new'])).'</td>
				</tr>
				<tr bgcolor="#ff6666">
					<td><b>Changed:</b></td>
					<td>'.htmlspecialchars(count($this->labelStatus[$editLang][$relFileRef]['changed'])).'</td>
				</tr>
				<tr bgcolor="#ff6600">
					<td><b>Unknown:</b></td>
					<td>'.htmlspecialchars(count($this->labelStatus[$editLang][$relFileRef]['unknown'])).'</td>
				</tr>
			</table>

			'.t3lib_BEfunc::cshItem('_MOD_txllxmltranslateM1', 'funcmenu_2_translationinterface'.($xmlArray['meta']['type'] == 'CSH' ? '_csh' : ''), $this->doc->backPath,'|<br/>').'

			<!-- Translation table: -->
			<table border="0" cellpadding="1" cellspacing="1" id="translate-table">'.$itemRow.'
			</table>';


			if ($xmlArray['meta']['type'] == 'CSH')	{
					// Images overview:
				$imageRows = array();
				foreach($this->lastImages as $ref)	{
					$image = t3lib_div::getFileAbsFileName($ref);
					if ($image)	{
						$imageRelPath = '../'.substr($image,strlen(PATH_site));

						$imageRows[] = '
							<tr>
								<td>'.$ref.'</td>
								<td>'.$this->lastImages_count[$ref].'</td>
								<td><img src="'.$GLOBALS['BACK_PATH'].$imageRelPath.'" alt="" /></td>
							</tr>
						';
					}
				}
				$output.='
				<h4>Image Overview ("'.dirname($relFileRef).'/cshimages/"):</h4>
				'.(count($imageRows) ? '<table border="1">
					'.implode('',$imageRows).'
				</table>
				':'');
			}

				// Output:
			return $output;
		}
	}

	/**
	 * Creates a single form row
	 *
	 * @param	array		XML array from file
	 * @param	string		File reference of a locallang-XML file (relative to PATH_site)
	 * @param	string		Label key.
	 * @param	boolean		If set, the field is always rendered as a textarea.
	 * @return	string		HTML table row, <tr>
	 */
	function createEditForm_getItemRow($xmlArray, $relFileRef, $labelKey, $alwaysTextarea = false) {

			// Initialize:
		$dataArray = $xmlArray['data'];
		$editLang = $this->MOD_SETTINGS['editLang'];
		$elName = 'lldat['.$relFileRef.']['.$editLang.']['.$labelKey.']';

			// If editLang is selected (always...?)
		if ($editLang)	{

				// Init row data:
			$tCells = array();
			$tCells[] = '<td>['.htmlspecialchars($labelKey).']</td>';		// Key

				// Link selector:
			$selector = '';
			if ($xmlArray['meta']['type'] == 'CSH' && ereg('\.seeAlso$',$labelKey))	{
				$opt = array();
					$opt[] = '
						<option value="">[ SEE ALSO ]</option>';
					$opt[] = '
						<option value="[Link Title] | http://..../">[ ADD REGULAR URL ]</option>';
				sort($this->cshLinks);
				foreach($this->cshLinks as $link)	{
					$opt[] = '
						<option value="'.$link.'">'.$link.'</option>';
				}

				$onChange = "
					document.forms.llxmlform['".$elName."'].value =
						document.forms.llxmlform['".$elName."'].value +
						(document.forms.llxmlform['".$elName."'].value ? ', \\n' : '') +
						this.options[this.selectedIndex].value;
					this.selectedIndex = 0;
					return false;
				";
				$selector = '
					<br/>
					<select name="_" onchange="'.htmlspecialchars($onChange).'">
					'.implode('',$opt).'
					</select>
				';
			}

				// Image selector:
			if ($xmlArray['meta']['type'] == 'CSH' && ereg('\.image$',$labelKey))	{
				$opt = array();
					$opt[] = '
						<option value="">[ IMAGE ]</option>';
				$images = t3lib_div::getFilesInDir(PATH_site.dirname($relFileRef).'/cshimages','gif,jpg,jpeg,png',1);
				$this->lastImages = array();
				foreach($images as $link)	{
					$link = ereg_replace('.*ext\/','EXT:',$link);
					$this->lastImages[] = $link;
					$opt[] = '
						<option value="'.$link.'">'.$link.'</option>';
				}

				$onChange = "
					document.forms.llxmlform['".$elName."'].value =
						document.forms.llxmlform['".$elName."'].value +
						(document.forms.llxmlform['".$elName."'].value ? ', \\n' : '') +
						this.options[this.selectedIndex].value;
					this.selectedIndex = 0;
					return false;
				";
				$selector = '
					<br/>
					<select name="_" onchange="'.htmlspecialchars($onChange).'">
					'.implode('',$opt).'
					</select>
				';

					// Show images:
				$images = t3lib_div::trimExplode(',',$dataArray['default'][$labelKey],1);
				if (count($images))	{
					$descrArray = explode(chr(10),$dataArray['default'][ereg_replace('^_','',$labelKey).'_descr'],count($images));
					foreach($images as $kk => $ref)	{
						$image = t3lib_div::getFileAbsFileName($ref);
						if ($image)	{
							$imageRelPath = '../'.substr($image,strlen(PATH_site));
							$this->lastImages_count[$ref]++;

							$selector.='
								<hr/>'.$ref.'<br/>
								<img src="'.$GLOBALS['BACK_PATH'].$imageRelPath.'" alt="" style="border:1px solid black;" />
								<p><b>Description:</b> <em>'.htmlspecialchars($descrArray[$kk]).'</em></p>';
						} else {
							$selector.='
								<hr/>'.$ref.' <b>NOT FOUND!</b><br/>
								<p><b>Description:</b> <em>'.htmlspecialchars($descrArray[$kk]).'</em></p>';
						}
					}
				}
			}

				// Default description:
			if ($editLang == 'default')	{
				$contextLabel = 'Context: <input name="'.htmlspecialchars('labelContext['.$relFileRef.']['.$labelKey.']').'" '.$GLOBALS['TBE_TEMPLATE']->formWidth(30).' value="'.htmlspecialchars(trim($xmlArray['meta']['labelContext'][$labelKey])).'" />';
			} else {
				$contextLabel = nl2br(htmlspecialchars($dataArray['default'][$labelKey])).
					($xmlArray['meta']['labelContext'][$labelKey] ? '<hr/>Context: <em>'.htmlspecialchars($xmlArray['meta']['labelContext'][$labelKey]).'</em>' : '');
			}
			$tCells[] = '<td>'.
				$contextLabel.
				$selector.
				'</td>';


				// Prepare status of the label:
			$bgcolor = '';
			if (strlen(trim($dataArray[$editLang][$labelKey])))	{
				$orig_hash = $xmlArray['orig_hash'][$editLang][$labelKey];
				$new_hash = t3lib_div::md5int($dataArray['default'][$labelKey]);
				if ($editLang!='default' && $orig_hash != $new_hash)	{
					if (!$orig_hash)	{
						$st = '?';
						$bgcolor = '#ff6600';
						$this->labelStatus[$editLang][$relFileRef]['unknown'][] = $labelKey;
					} else {
						$st = 'Changed!';
						$bgcolor = '#ff6666';
						$this->labelStatus[$editLang][$relFileRef]['changed'][] = $labelKey;

						if (strlen(trim($xmlArray['orig_text'][$editLang][$labelKey])))	{
							$diffEngine = t3lib_div::makeInstance('t3lib_diff');
							$diffHTML = $diffEngine->makeDiffDisplay(
											$xmlArray['orig_text'][$editLang][$labelKey],
											$dataArray['default'][$labelKey]
										);
							$st.='<div style="width:200px; background-color:white;">'.$diffHTML.'</div>';
						}
					}
				} else {
					$st = 'OK';
					$bgcolor = '#009900';
					$this->labelStatus[$editLang][$relFileRef]['ok'][] = $labelKey;
				}
			} else {
				$st = 'NEW';
				$bgcolor = '#6666ff';
				$this->labelStatus[$editLang][$relFileRef]['new'][] = $labelKey;
			}


				// Editing field:
			$elValue = trim($dataArray[$editLang][$labelKey]);
			$elValueDefault = trim($dataArray['default'][$labelKey]);

			if (count(explode(chr(10),$elValue))>=2 || count(explode(chr(10),$elValueDefault))>=2 || $alwaysTextarea)	{
				$wrapOff = substr($labelKey,-8)=='.seeAlso' ? 'off' : '';
				$formElement = '<textarea name="'.htmlspecialchars($elName).'" rows="'.(count(explode(chr(10),$elValueDefault))+1).'" wrap="'.$wrapOff.'" '.$GLOBALS['TBE_TEMPLATE']->formWidthText(50,'',$wrapOff).'>'.t3lib_div::formatForTextarea($elValue).'</textarea>';
			} else {
				$formElement = '<input name="'.htmlspecialchars($elName).'" '.$GLOBALS['TBE_TEMPLATE']->formWidth(50).' value="'.htmlspecialchars($elValue).'" />';
			}
#debug(t3lib_div::debug_ordvalue($elValue),$elName);
			$tCells[] = '<td bgcolor="'.$bgcolor.'">'.$formElement.'</td>';

				// Status label:
			$tCells[] = '<td bgcolor="'.$bgcolor.'">'.$st.'</td>';

				// Additional support languages:
			foreach($this->langKeys as $langK)	{
				if ($this->MOD_SETTINGS['addLang_'.$langK])	{
					$tCells[] = '<td>'.nl2br(htmlspecialchars($dataArray[$langK][$labelKey])).'</td>';
				}
			}

				// Return row:
			return '
				<tr class="bgColor4'.(($this->counter++)%2 ? '-20' : '').'">
					'.implode('
					',$tCells).'
				</tr>';
		}
	}

	/**
	 * Create single row header
	 *
	 * @return	string		HTML table row.
	 */
	function createEditForm_getItemHead()	{
		if ($this->MOD_SETTINGS['editLang'])	{

				// Width of each label column is set by a clear-gif:
			$clearGif = '<br/><img src="clear.gif" width="250" height="1" alt="" />';

			$tCells = array();
			$tCells[] = '<td>Key</td>';
			$tCells[] = '<td>Default:'.$clearGif.'</td>';
			$tCells[] = '<td>'.$this->MOD_SETTINGS['editLang'].'</td>';
			$tCells[] = '<td>Status:</td>';

			foreach($this->langKeys as $langK)	{
				if ($this->MOD_SETTINGS['addLang_'.$langK])	{
					$tCells[] = '<td>'.$langK.$clearGif.'</td>';
				}
			}

				// Return row:
			return '
			<tr class="bgColor5" style="font-weight: bold;">
				'.implode('
				',$tCells).'
			</tr>';
		}
	}

	/**
	 * @return	[type]		...
	 */
	function createMergeForm()	{

			// Read uploaded file:
		$uploadedTempFile = t3lib_div::upload_to_tempfile($GLOBALS['HTTP_POST_FILES']['upload_merge_file']['tmp_name']);
		list($hash,$fileContent) = explode(':',t3lib_div::getUrl($uploadedTempFile),2);
		$fileContent = ereg_replace('[[:space:]]','',$fileContent);
#debug(array($fileContent));

		t3lib_div::unlink_tempfile($uploadedTempFile);
		$specFile = $this->files[t3lib_div::_POST('specFile')];
		$fileIndexOnly = t3lib_div::_POST('showFileIndexOnly');

			// Check upload:
		if (1 || $hash == md5($fileContent))	{
			$mergeContent = unserialize(base64_decode($fileContent));
			$editLang = $this->MOD_SETTINGS['editLang'];

			if (is_array($mergeContent))	{
				if ($mergeContent['meta']['language'] == $editLang)	{
					$formcontent = '';

					if (is_array($mergeContent['files']))	{
						foreach($mergeContent['files'] as $fileRef => $labelValues)	{
							$errors = array();

							if (!$specFile || !strcmp($specFile,$fileRef))	{
								$formcontent.='<h4>File: '.htmlspecialchars($fileRef).'</h4>';

								if (in_array($fileRef, $this->files))	{

									$xmlArray = $this->getXMLdata($fileRef);
									$clearGif = '<br/><img src="clear.gif" width="250" height="1" alt="" />';

									$rows = array();
									$rows[] = '
										<tr class="bgColor5" style="font-weight: bold;">
											<td>Label key:</td>
											<td>Default:'.$clearGif.'</td>
											<td>'.$editLang.' (local):'.$clearGif.'</td>
											<td>Merge:</td>
											<td>'.$editLang.' (from merge file):</td>
											<td>Diff. between local and merge-value:'.$clearGif.'</td>
										</tr>
									';

									foreach($labelValues as $labelKey => $labelValue)	{
										if (isset($xmlArray['data']['default'][$labelKey]))	{

												// Looking for difference:
											if (strcmp($xmlArray['data'][$editLang][$labelKey],$labelValue))	{

													// Initialize values:
												$elName = 'lldat['.$fileRef.']['.$editLang.']['.$labelKey.']';
												$elValue = trim($xmlArray['data'][$editLang][$labelKey]);
												$elValueDefault = trim($xmlArray['data']['default'][$labelKey]);

													// Creating field name:
												if (count(explode(chr(10),$labelValue))>=2)	{
													$wrapOff = substr($labelKey,-8)=='.seeAlso' ? 'off' : '';
													$formElement = '<textarea name="'.htmlspecialchars($elName).'" rows="'.(count(explode(chr(10),$labelValue))+1).'" wrap="'.$wrapOff.'" '.$GLOBALS['TBE_TEMPLATE']->formWidthText(50,'',$wrapOff).'>'.t3lib_div::formatForTextarea($labelValue).'</textarea>';
												} else {
													$formElement = '<input name="'.htmlspecialchars($elName).'" '.$GLOBALS['TBE_TEMPLATE']->formWidth(50).' value="'.htmlspecialchars($labelValue).'" />';
												}

													// Diff:
												if (!$fileIndexOnly)	{
													$diffEngine = t3lib_div::makeInstance('t3lib_diff');
													$diffHTML = $diffEngine->makeDiffDisplay(
																	$elValue,
																	$labelValue
																);
												}

												if (strcmp($mergeContent['orig_hash'][$fileRef][$labelKey], t3lib_div::md5int($xmlArray['data']['default'][$labelKey])))	{
													if (!$mergeContent['orig_hash'][$fileRef][$labelKey])	{
														$checkBox = '<span style="background-color: #666666;"><input name="check_'.$elName.'" type="checkbox" value="1" /> <br/>Uncertainty about which original label this translation is based on...(?)</span>';
													} else {
														$checkBox = '<span style="background-color: red;"><input name="check_'.$elName.'" type="checkbox" value="1" /> <br/>New value was not translated from the save original label as on this system!</span>';
													}
												} else {
													$checkBox = '<input name="check_'.$elName.'" type="checkbox" value="1" checked="checked" />';
												}

													// Compile row:
												$rows[] = '
													<tr class="bgColor4">
														<td>['.htmlspecialchars($labelKey).']</td>
														<td>'.nl2br(htmlspecialchars($elValueDefault)).'</td>
														<td>'.nl2br(htmlspecialchars($elValue)).'</td>
														<td>'.$checkBox.'</td>
														<td>'.$formElement.'</td>
														<td><div style="width:200px; background-color:white; border: 1px solid black;">'.$diffHTML.'</div></td>
													</tr>
												';
											}

										} else $errors[] = 'No key with name "'.$labelKey.'"';
									}


									if (count($rows)>1)	{
										$formcontent.=
											$this->llxmlFileInfoBox($xmlArray,$fileRef).
											(!$fileIndexOnly ? '
											<table border="1">
												'.implode('', $rows).'
											</table>'.
											t3lib_div::view_array($errors) : 'Changes to submit: '.(count($rows)-1));
									} else {
										$formcontent.='No changes to this file<br/>';
									}
								} else {
									$formcontent.='

									ERROR: File "'.$fileRef.'" was not on the local system, so cannot merge any content from it!
									'.t3lib_div::view_array($labelValues);
								}
							}
						}

							// Put form together:
						$content.= '
							<br/>
							<input type="submit" name="_save" value="Save" />
							<hr/>

							'.$formcontent.'

							<hr/>
							<input type="hidden" name="checkboxMode" value="1" />
							<input type="submit" name="_save" value="Save" />
							';
					} else $content.='<hr/>No files, strange...';
				} else $content.= '<hr />The language in the file was "'.$mergeContent['meta']['language'].'" and your edit setting is "'.$editLang.'". Please change your edit setting so they match (if you can). Otherwise you cannot merge the file!';
			} else $content.= '<hr />ERROR: Strange, the file did not contain an array!?';
		} else $content.= '<hr />ERROR: Invalid file content; Hash check didn\'t match up';

		return $content;
	}













	/*************************
	 *
	 * Saving and getting XML content
	 *
	 *************************/

	/**
	 * Saving submitted data to file(s)
	 *
	 * @return	array		Save and Error logs
	 */
	function saveSubmittedData()	{
		if (t3lib_div::_POST('_save'))	{

				// Form data:
			$llDat = t3lib_div::_POST('lldat');
			$check_llDat = t3lib_div::_POST('check_lldat');
			$checkboxMode = t3lib_div::_POST('checkboxMode');
			$updateAllValues = t3lib_div::_POST('updateAllValues');

			$labelContext = t3lib_div::_POST('labelContext');
			$saveLog = array();
			$errorLog = array();

				// Traverse files:
			if (is_array($llDat))	{
				foreach($llDat as $fileRef => $langData)	{

						// Check file:
					$absFile = t3lib_div::getFileAbsFileName($fileRef);
					if (PATH_site.$fileRef==$absFile && is_file($absFile) && substr(basename($absFile),0,9)=='locallang' && substr(basename($absFile),-4)=='.xml')	{

							// Read XML array:
						$xmlArray = $this->getXMLdata($fileRef);
						if (is_array($xmlArray))	{
							$save = FALSE;

								// Traverse languages:
							if (is_array($langData))	{
								foreach($langData as $langKey => $dataValues)	{
									if ($langKey==$this->MOD_SETTINGS['editLang'] && in_array($langKey,$this->langKeys))	{	// Setting ONLY editLanguage language key! (one-language-at-a-time policy)

											// Traverse labels:
										if (is_array($dataValues))	{
											$newValueArray = array();

											foreach($dataValues as $labelKey => $labelValue)	{
												if ($langKey=='default' || isset($xmlArray['data']['default'][$labelKey]))	{

													if (!$checkboxMode || $check_llDat[$fileRef][$langKey][$labelKey])	{

															// Setting existing:
														if (isset($xmlArray['data'][$langKey][$labelKey]))	{
															$newValueArray[$labelKey] = $xmlArray['data'][$langKey][$labelKey];
														}

															// Setting label value:
														$labelValue = str_replace(chr(13),'',trim($labelValue));
														if ($updateAllValues || strcmp($labelValue, $newValueArray[$labelKey]))	{
															$newValueArray[$labelKey] = $labelValue;
															$saveLog[$fileRef]['data'][$langKey][$labelKey] = $labelValue;

															$save = TRUE;
														}

															// Update orig-hash
														if ($langKey!='default')	{
															if (strlen($labelValue))	{
																$origHash = t3lib_div::md5int($xmlArray['data']['default'][$labelKey]);
																if ($updateAllValues || $origHash != $xmlArray['orig_hash'][$langKey][$labelKey])	{
																	$xmlArray['orig_hash'][$langKey][$labelKey] = $origHash;
																	$saveLog[$fileRef]['orig_hash'][$langKey][$labelKey] = 'UPDATED';

																	$save = TRUE;
																}

																#debug(array($xmlArray['orig_text'][$langKey][$labelKey] , $xmlArray['data']['default'][$labelKey]));
																if ($updateAllValues || $xmlArray['orig_text'][$langKey][$labelKey] != $xmlArray['data']['default'][$labelKey])	{
																	$xmlArray['orig_text'][$langKey][$labelKey] = $xmlArray['data']['default'][$labelKey];
																	$saveLog[$fileRef]['orig_text'][$langKey][$labelKey] = 'UPDATED';

																	$save = TRUE;
																}
															}
														} else {
															unset($xmlArray['orig_hash']['default']);
															unset($xmlArray['orig_text']['default']);
														}
													}
												} else $errorLog[$fileRef][] = 'Label for "'.$labelKey.'" was not found as a default label!';
											}

												// If set, then transfer the new array:
											if ($save)	{
												if ($checkboxMode)	{
													if (!is_array($xmlArray['data'][$langKey]))		$xmlArray['data'][$langKey] = array();
													$xmlArray['data'][$langKey] = t3lib_div::array_merge_recursive_overrule($xmlArray['data'][$langKey], $newValueArray);
												} else {
														// Finding any ADDITIONAL FIELDS which are not in the default language:
													$diff = array_diff(array_keys(is_array($xmlArray['data'][$langKey]) ? $xmlArray['data'][$langKey] : array()), array_keys($newValueArray));
													if (count($diff))	{
														foreach($diff as $nonAllowedKey)	{
															$newValueArray[$nonAllowedKey] = $xmlArray['data'][$langKey][$nonAllowedKey];
														}
														$errorLog[$fileRef][] = array('Found illegal keys in array. (See below). Preserving them anyways.',$diff);
													}

														// Setting new (newly ordered) array:
													$xmlArray['data'][$langKey] = $newValueArray;
												}

													// Remove blanks:
												foreach($xmlArray['data'][$langKey] as $labelKey => $labelValue)	{
													if (!strlen($labelValue))	{
														unset($xmlArray['data'][$langKey][$labelKey]);
														unset($xmlArray['orig_hash'][$langKey][$labelKey]);
														unset($xmlArray['orig_text'][$langKey][$labelKey]);
														$saveLog[$fileRef]['Messages']['REMOVED BLANKS:'][] = $labelKey;
													}
												}

													// Setting log messages:
												$saveLog[$fileRef]['Messages'][] = 'Serialized lgd: '.strlen(serialize($xmlArray['data'][$langKey]));
												$saveLog[$fileRef]['Messages'][] = 'Serialized hash: '.md5(serialize($xmlArray['data'][$langKey]));
											}
										} else $errorLog[$fileRef][] = 'Labels for "'.$langKey.'" was not an array!';
									} else $errorLog[$fileRef][] = '"'.$langKey.'" was not a language key (or not the edit-language selected)!';
								}
							} else $errorLog[$fileRef][] = 'POST data for "'.$fileRef.'" file was not an array!';

								// Context?
							if (is_array($labelContext[$fileRef]))	{
								$xmlArray['meta']['labelContext'] = $labelContext[$fileRef];
								foreach($xmlArray['meta']['labelContext'] as $kk => $vv)	{
									if (!strlen(trim($vv)))	unset($xmlArray['meta']['labelContext'][$kk]);
								}
								$save = TRUE;
								$saveLog[$fileRef]['labelContext'] = 'UPDATED';
							}

								// Saving modifications:
							if ($save)	{
									// Create / Save XML:
								$this->saveXMLdata($fileRef, $xmlArray, $saveLog, $errorLog);
							}
						} else $errorLog[$fileRef][] = '"'.$fileRef.'" did not contain a proper XML array!';
					} else $errorLog[$fileRef][] = 'File reference not valid! ('.$fileRef.')';
				}
			} else $errorLog['_ERROR'][] = 'Input POST data was not an array';

				// Return logs:
			return array(
				'SAVE_LOG' => $saveLog,
				'ERRORS' => $errorLog
			);
		}
	}

	/**
	 * Reads the XML file and converts it into an array
	 * You can expect to get a complete data array for 'default' AND ONLY the select edit language (since ll-XML with externally stored content for various languages is only read for the edit-language)
	 *
	 * @param	string		XML file reference, relative to PATH_site.
	 * @return	array	Array (contents of XML file)
	 */
	function getXMLdata($fileRef)	{

			// Getting MAIN ll-XML file content:
		$dataArray = t3lib_div::xml2array(t3lib_div::getUrl(PATH_site.$fileRef));
		$editLang = $this->MOD_SETTINGS['editLang'];

		if (is_array($dataArray))	{

			$dataArray['meta']['_ORIG_LANGUAGE_DATA'] = $dataArray['data'][$editLang];

				// If no entry is found for the language key, then force a value depending on meta-data setting. By default an automated filename will be used:
			$autoFileName = '';
			if ($editLang != 'default') {
				$autoFileName = t3lib_div::llXmlAutoFileName(PATH_site.$fileRef, $editLang);
				if ($autoFileName && @file_exists(PATH_site . $autoFileName))	{
					$dataArray['data'][$editLang] = $autoFileName;
				}
			}

				// Looking for external values for a certain edit-language:
			if ($editLang!='default' && is_string($dataArray['data'][$editLang]) && strlen($dataArray['data'][$editLang]))	{
				$file = t3lib_div::getFileAbsFileName($dataArray['data'][$editLang]);
				if ($file && @is_file($file))	{
					$extArray = t3lib_div::xml2array(t3lib_div::getUrl($file));
				} elseif ($autoFileName) {
			#		echo t3lib_div::view_array(array('Notice: auto-created file not found, will be created during save.',$autoFileName));
					$extArray = array();
				} else {
					echo t3lib_div::view_array(array('ERROR!: could not find referenced file, please fix',$dataArray['data'][$editLang]));
					$extArray = array();
				}

				$dataArray['data'][$editLang] = is_array($extArray['data'][$editLang]) ? $extArray['data'][$editLang] : array();
				$dataArray['orig_hash'][$editLang] = is_array($extArray['orig_hash'][$editLang]) ? $extArray['orig_hash'][$editLang] : array();
				$dataArray['orig_text'][$editLang] = is_array($extArray['orig_text'][$editLang]) ? $extArray['orig_text'][$editLang] : array();
			}
		} else {
			echo $fileRef.' did not contain parsed XML! Output:';
			echo '<hr/>'.$dataArray.'<hr/>';
		}

		return $dataArray;
	}

	/**
	 * Saves the language array as XML file
	 * Will read the original file and determine if an external file is used for the current language. If so, the external file is updated.
	 *
	 * @param	string		XML file reference, relative to PATH_site.
	 * @param	array		Array to save
	 * @param	array		Save log array (passed by reference)
	 * @param	array		Error log array (passed by reference)
	 * @return	void
	 */
	function saveXMLdata($fileRef,$xmlArray, &$saveLog, &$errorLog)	{

			// Getting MAIN ll-XML file content and editing language:
		$dataArray = t3lib_div::xml2array(t3lib_div::getUrl(PATH_site . $fileRef));
		$editLang = $this->MOD_SETTINGS['editLang'];

		if (is_array($dataArray))	{

				// If no entry is found for the language key, then force a value depending on meta-data setting. By default an automated filename will be used:
			if ($editLang!='default' && (!isset($dataArray['data'][$editLang])) || t3lib_div::_POST('_moveToExternalFile'))	{
				$autoFileName = $dataArray['data'][$editLang] = t3lib_div::llXmlAutoFileName(PATH_site . $fileRef, $editLang);
			} else {
				$autoFileName = '';
			}

				// Looking for external storage for edit language if not default:
			if ($editLang!='default')	{

					// If autoFileName, check if exists, if not, create:
				if ($autoFileName)	{
					$extFile = t3lib_div::getFileAbsFileName($dataArray['data'][$editLang]);
					if ($extFile && !@is_file($extFile))	{
						$XML = $this->createXML(array('data' => array()),TRUE);

							// Dir:
						$deepDir = dirname(substr($extFile,strlen(PATH_site))).'/';
						if (t3lib_div::isFirstPartOfStr($deepDir,'typo3conf/l10n/'.$editLang.'/'))	{
							t3lib_div::mkdir_deep(PATH_site,$deepDir);

								// Write file:
							t3lib_div::writeFile($extFile, $XML);

							if (md5(t3lib_div::getUrl($extFile)) == md5($XML))	{
								$saveLog[$fileRef]['CREATED NEW EXTERNAL LANGUAGE FILE'] = substr($extFile,strlen(PATH_site));
							} else $errorLog[$fileRef][] = 'ERROR, Tried to create "'.$extFile.'" but MD5 check failed!';
						} else $errorLog[$fileRef][] = 'ERROR, path was not in typo3conf/ext/ or typo3conf/l10n/';
					}
				}

					// Looking for external file settings:
				if (is_string($dataArray['data'][$editLang]) && strlen($dataArray['data'][$editLang]))	{

						// Getting the file:
					$extFile = t3lib_div::getFileAbsFileName($dataArray['data'][$editLang]);
					if ($extFile && @is_file($extFile))	{
						$saveLog[$fileRef]['Messages'][] = 'Found external file: '.$dataArray['data'][$editLang];

							// Reading XML content out of file:
						$extArray = t3lib_div::xml2array(t3lib_div::getUrl($extFile));
						if (is_array($extArray))	{

								// Setting language specific information in the XML file array:
							$extArray['data'][$editLang] = is_array($xmlArray['data'][$editLang]) ? $xmlArray['data'][$editLang] : array();
							$extArray['orig_hash'][$editLang] = is_array($xmlArray['orig_hash'][$editLang]) ? $xmlArray['orig_hash'][$editLang] : array();
							$extArray['orig_text'][$editLang] = is_array($xmlArray['orig_text'][$editLang]) ? $xmlArray['orig_text'][$editLang] : array();

								// Setting reference to the external file:
							if ($autoFileName)	{
								unset($xmlArray['data'][$editLang]);
							} else {
								$xmlArray['data'][$editLang] = $dataArray['data'][$editLang];
							}

								// Unsetting the hash and original text for this language as well:
							unset($xmlArray['orig_hash'][$editLang]);
							unset($xmlArray['orig_text'][$editLang]);

								// Create XML and save file:
							$XML = $this->createXML($extArray,TRUE);
							if (md5(t3lib_div::getUrl($extFile)) != md5($XML))	{
								t3lib_div::writeFile($extFile, $XML);

									// Checking if the localized file was saved as it should be:
								if (md5(t3lib_div::getUrl($extFile)) == md5($XML))	{
									$saveLog[$fileRef]['SAVING EXTERNAL'] = 'DONE';
								} else {
									$tempFile = t3lib_div::tempnam('llxml_');
									t3lib_div::writeFile($tempFile, $XML);
									$errorLog[$fileRef][] = 'SAVED CONTENT DID NOT match what was saved to the file (EXTERNAL FILE)! Write access problem to "'.$extFile.'"? (backup file is saved to "'.$tempFile.'"). Recovery suggestion: Fix write permissions for the file and re-submit page.';
								}
							} else {
								$saveLog[$fileRef]['SAVING EXTERNAL'] = 'Not needed, XML content didn\'t change.';
							}
						} else $errorLog[$fileRef][] = 'ERROR, could not find XML in file: '.$dataArray['data'][$editLang];
					} else $errorLog[$fileRef][] = 'ERROR, could not find file: '.$dataArray['data'][$editLang];
				}
			}

				// Unsetting the hash and original text for default (just because it does not make sense!)
			unset($xmlArray['meta']['_ORIG_LANGUAGE_DATA']);
			unset($xmlArray['orig_hash']['default']);
			unset($xmlArray['orig_text']['default']);

				// Save MAIN file:
			if (!@is_writeable(PATH_site . $fileRef)) {
				$errorLog[$fileRef][] = 'Warning: ' . $fileRef . ' is not writable! Old translations (if any) will be left in the file!';
			}
			else {
				$XML = $this->createXML($xmlArray);
				if (md5(t3lib_div::getUrl(PATH_site.$fileRef)) != md5($XML))	{
					t3lib_div::writeFile(PATH_site.$fileRef, $XML);

						// Checking if the main file was saved as it should be:
					if (md5(t3lib_div::getUrl(PATH_site.$fileRef)) == md5($XML))	{
						$saveLog[$fileRef]['SAVING MAIN'] = 'DONE';
					} else {
						$tempFile = t3lib_div::tempnam('llxml_');
						t3lib_div::writeFile($tempFile, $XML);
						$errorLog[$fileRef][] = 'SAVED CONTENT DID NOT match what was saved to the file! Write access problem to "'.$fileRef.'"? (backup file is saved to "'.$tempFile.'"). Recovery suggestion: Fix write permissions for the file and re-submit page.';
					}
				} else {
					$saveLog[$fileRef]['SAVING MAIN'] = 'Not needed, XML content didn\'t change.';
				}
			}
		} else die('WRONG FILE: '.$fileRef);
	}

	/**
	 * Creates XML string from input array
	 *
	 * @param	array		locallang-XML array
	 * @param	boolean		If set, then the XML will have a document tag for an external file.
	 * @return	string		XML content
	 */
	function createXML($outputArray,$ext=FALSE)	{

			// Options:
		$options = array(
			#'useIndexTagForAssoc'=>'key',
			'parentTagMap' => array(
				'data' => 'languageKey',
				'orig_hash' => 'languageKey',
				'orig_text' => 'languageKey',
				'labelContext' => 'label',
				'languageKey' => 'label'
			)
		);

			// Creating XML file from $outputArray:
		$XML = '<?xml version="1.0" encoding="utf-8" standalone="yes" ?>'.chr(10);
		$XML.= t3lib_div::array2xml($outputArray,'',0,$ext ? 'T3locallangExt' : 'T3locallang',0,$options);

		return $XML;
	}










	/*************************
	 *
	 * Helper functions
	 *
	 *************************/

	/**
	 * Get all locallang-XML files from system, global and local extensions
	 *
	 * @return	array		Array of files.
	 */
	function getllxmlFiles($extPath)	{

			// Initialize:
		$files = array();

			// Traverse extension locations:
	/*
		foreach($this->extPathList as $path)	{
			if (is_dir(PATH_site . $path))	{
				$files = t3lib_div::getAllFilesAndFoldersInPath($files, PATH_site . $path, 'xml');
			}
		}
	*/
		$files = t3lib_div::getAllFilesAndFoldersInPath($files, $extPath . '/', 'xml');

			// Remove prefixes
		$files = t3lib_div::removePrefixPathFromList($files, PATH_site);

			// Remove all non-locallang files (looking at the prefix)
		foreach($files as $key => $value)	{
			if (substr(basename($value), 0, 9) != 'locallang')	{
				unset($files[$key]);
			}
		}

		return $files;
	}

	/**
	 * Get all locallang-XML files (generates it unless found in ses. data)
	 *
	 * @param	boolean		If set, then the file list is regenerated.
	 * @return	array		Array of files.
	 */
	function getllxmlFiles_cached($regenerate = false)	{
		$set = t3lib_div::_GP('SET');
		$extPath = $set['llxml_extlist'] ? $set['llxml_extlist'] : $this->MOD_SETTINGS['llxml_extlist'];
		$ext = $this->MOD_MENU['llxml_extlist'][$extPath];
		if ($ext == '') {
			$files = array();
		}
		else {
			if (!$regenerate)	{
				$files = $GLOBALS['BE_USER']->getSessionData('tx_llxmltranslate:files:' . $ext);
			}

			if (!is_array($files))	{
				$files = $this->getllxmlFiles($extPath);
				$GLOBALS['BE_USER']->setAndSaveSessionData('tx_llxmltranslate:files:' . $ext, $files);
			}
		}

		return $files;
	}

	/**
	 * Load translation status information.
	 *
	 * @param	array		If you supply an array of locallang files they will be analysed and information cached (used for "Re-generate cached information".
	 * @return	array		Statistical information
	 */
	function loadTranslationStatus($files = false, $cshOK = false) {
		global $BE_USER;

		$statInfo = array();
		$editLang = $this->MOD_SETTINGS['editLang'];

		$this->labelStatus = $BE_USER->getSessionData('tx_llxmltranslate:status');
		$this->cshLinks = $this->labelStatus['_CSH_references'] = is_array($this->labelStatus['_CSH_references']) ? array_unique($this->labelStatus['_CSH_references']) : array();

		if (is_array($files))	{
			$this->labelStatus = array();
			$this->labelStatus['_CSH_references'] = array();

			$pt = t3lib_div::milliseconds();
			$count = array();
			foreach($files as $relFileRef)	{
				if ($cshOK || $this->checkCSH($relFileRef)) {
					$this->createEditForm($relFileRef, false);

					$cKey = substr(basename($relFileRef), 0, 13) == 'locallang_csh' ? 'CSH' : 'LABELS';

					$count[$cKey]['ok'] += count($this->labelStatus[$editLang][$relFileRef]['ok']);
					$count[$cKey]['changed'] += count($this->labelStatus[$editLang][$relFileRef]['changed']);
					$count[$cKey]['unknown'] += count($this->labelStatus[$editLang][$relFileRef]['unknown']);
					$count[$cKey]['new'] += count($this->labelStatus[$editLang][$relFileRef]['new']);
				}
			}

			$statInfo['Labels missing translation'] = $count['LABELS']['changed'] + $count['LABELS']['new'];
			$statInfo['parsetime'] = t3lib_div::milliseconds()-$pt;
			$statInfo['labels'] = $count['LABELS'];
			$statInfo['csh'] = $count['CSH'];

			$BE_USER->setAndSaveSessionData('tx_llxmltranslate:status', $this->labelStatus);
		}

		return $statInfo;
	}

	/**
	 * Used to generate the HTML for "typo3conf/l10n/status.html" - see CLI script.
	 */
	function writeReportForAll()	{
		@ob_end_clean();
		ob_start();
		$startTime = time();
		echo '<h2>Translation Status '.t3lib_BEfunc::dateTime(time()).'</h2>';

		$details = $header = '';


		$header.= '<tr>
		<td><b>Language:</b></td>
		<td><b>Langkey:</b></td>
		<td><b>Labels missing translation:</b></td>
		<td><b>Complete %:</b></td>
		<td><b>Missing CSH:</b></td>
		<td><b>Download:</b></td>
		</tr>';
			// Find all files:
		$files = array();
		foreach($this->extPathList as $path)	{
			$files = array_merge($files, $this->getllxmlFiles(PATH_site . $path));
		}
//			$files = $this->getllxmlFiles();

		t3lib_div::loadTCA('be_users');
		foreach($GLOBALS['TCA']['be_users']['columns']['lang']['config']['items'] as $pair)	{
			if ($pair[1])	{
					// Re-generate status
				$this->MOD_SETTINGS['editLang'] = $pair[1];
				$statInfo = $this->loadTranslationStatus($files, true);

				$header.= '<tr>
				<td><a href="#'.$pair[1].'">'.$pair[0].'</a></td>
				<td>'.$pair[1].'</td>
				<td>'.$statInfo['Labels missing translation'].'</td>
				<td>'.round(100-$statInfo['Labels missing translation']/($statInfo['Labels missing translation']+$statInfo['labels']['ok'])*100).'%</td>
				<td>'.($statInfo['csh']['new']+$statInfo['csh']['changed']+$statInfo['csh']['unknown']).'</td>
				<td>'.
					(@is_file(PATH_site.'typo3conf/l10n/'.$pair[1].'.zip') ? '<a href="'.$pair[1].'.zip">'.$pair[1].'.zip</a> ' : '').
					(@is_file(PATH_site.'typo3conf/l10n/'.$pair[1].'.tgz') ? '<a href="'.$pair[1].'.tgz">'.$pair[1].'.tgz</a> ' : '').
					'</td>
				</tr>';


				$details.= '<h3 id="'.$pair[1].'">'.$pair[0].' ('.$pair[1].')</h3>';
				$details.=t3lib_div::view_array($statInfo);
			}
		}

			// Output:
		echo '<table border="1">'.$header.'</table>';
		echo '<h3>Files:</h3>';
		t3lib_div::debug($files);
		echo $details;

		echo '<hr>Processing took '.floor((time()-$startTime)/60).':'.((time()-$startTime)%60).' minutes:seconds';

			// Output:
		$output = ob_get_contents().chr(10);
		ob_end_clean();

		return $output;
	}

	/**
	 * Open window for Context Sensitive Help
	 *
	 * @param	string		Parameters to pass to view_help.php in the GET var "tfID".
	 * @return	string		JavaScript code for an onclick-handler in an <a> tag.
	 */
	function openCSH($p)	{
		return 'vHWin=window.open(\''.$GLOBALS['BACK_PATH'].'view_help.php?tfID='.rawurlencode($p).'\',\'viewFieldHelp\',\'height=400,width=600,status=0,menubar=0,scrollbars=1\');vHWin.focus();return false;';
	}

	/**
	 * Creates HTML table with information about the ll-XML file given as array
	 *
	 * @param	array		XML file array
	 * @param	string		The file reference (relative to PATH_site) of the XML file array
	 * @return	string		HTML table.
	 */
	function llxmlFileInfoBox($xmlArray,$relFileRef)	{

			// Get editing language:
		$editLang = $this->MOD_SETTINGS['editLang'];

			// Automatic external file message:
		$msg_autoFilename = '';
		$externalFile = t3lib_div::llXmlAutoFileName(PATH_site.$relFileRef, $editLang);
		if ($editLang=='default') {
			$msg_autoFilename = 'Default language, no external file.';
		} elseif ($externalFile) {
			$msg_autoFilename = $externalFile;

			if (@file_exists(PATH_site . $externalFile)) {
				// Here is translated file exists but original file still keeps entries.
				// Happens when translating core and core language files are read-only
				$msg_autoFilename .= '<br/><span class="typo3-green"><b>Translations in specific external file: "' . $externalFile . '"</b></span>';
			}
			elseif (isset($xmlArray['meta']['_ORIG_LANGUAGE_DATA']) && !is_array($xmlArray['meta']['_ORIG_LANGUAGE_DATA'])) {
				// Here is locallang.xml references external file like this:
				//        <languageKey index="dk">EXT:irfaq/lang/dk.locallang.xml</languageKey>
				$msg_autoFilename = $xmlArray['meta']['_ORIG_LANGUAGE_DATA'];
				$msg_autoFilename .= '<br /><span class="typo3-green"><b>Translations in specific external file: "'.$xmlArray['meta']['_ORIG_LANGUAGE_DATA'].'"</b></span>';
			}
			elseif (is_array($xmlArray['meta']['_ORIG_LANGUAGE_DATA']))	{
				// Here if translations are inside main file
				$msg_autoFilename.='<br/><span class="typo3-red"><b>Translations is in main file ('.count($xmlArray['meta']['_ORIG_LANGUAGE_DATA']).' already)! [<input type="checkbox" name="_moveToExternalFile" value="1" checked="chjecked" /> Move to external file!]</b></span>';
			} else {
				// Here if no translation in main file, no references and no external file
				$msg_autoFilename.='<br/><span class="typo3-red"><b>File does not exist yet, but will be created!</b></span>';
			}
		} else {
			$msg_autoFilename = '<span class="typo3-red"><b>Files might not be in an extension! Alert!</b></span>';
		}

			// Type:
		switch($xmlArray['meta']['type'])	{
			case 'module':
			case 'database':
			case 'CSH':
				$theType = $xmlArray['meta']['type'];
			break;
			default:
				$theType = '<span class="typo3-red"><b>'.$xmlArray['meta']['type'].'</b></span>';
			break;
		}

		$output = '
			<!-- Info about file: -->
			<table border="0" cellpadding="1" cellspacing="1" style="border: 1px solid black;">
				<tr class="bgColor4">
					<td><b>Description:</b></td>
					<td>'.htmlspecialchars($xmlArray['meta']['description']).'</td>
				</tr>
				<tr class="bgColor4">
					<td><b>Type:</b></td>
					<td>'.$theType.'</td>
				</tr>
				'.($xmlArray['meta']['type']=='CSH' ? '
				<tr class="bgColor4">
					<td><b>CSH table:</b></td>
					<td><a href="#" onclick="'.htmlspecialchars($this->openCSH($xmlArray['meta']['csh_table'])).'">'.htmlspecialchars($xmlArray['meta']['csh_table']).'</a></td>
				</tr>' : '').'
				<tr class="bgColor4">
					<td><b>External filename:</b></td>
					<td>'.$msg_autoFilename.'</td>
				</tr>
			</table>
		';

		return $output;
	}

	/**
	 * Returns a list of all extensions
	 *
	 * @return	array	Extension list. Key is path to extension, value is extension key (=directory name)
	 */
	function getExtList() {
		$extList = array('' => '');
		foreach($this->extPathList as $path) {
			$dir = PATH_site . $path;
			if (is_dir($dir)) {
				$dirs = t3lib_div::get_dirs($dir);
				foreach ($dirs as $dirname) {
					if ($dirname{0} != '.') {
						$path = $dir . $dirname;
						$version = $this->getExtVersion($dirname, $path);
						if ($version) {
							$str = str_pad($dirname, 32, ' ');
							$extList[$path] = $str . '(' . $version . ')';
						}
					}
				}
			}
		}
		asort($extList);
		return $extList;
	}

	/**
	 * Obtains extension version
	 *
	 * @param	string	$extKey	Extension key
	 * @param	string	$extPath	Extension path
	 * @return	string	Extension version
	 */
	function getExtVersion($extKey, $extPath) {
		$_EXTKEY = $extKey;
		@include($extPath . '/ext_emconf.php');
		return isset($EM_CONF[$_EXTKEY]['version']) ? $EM_CONF[$_EXTKEY]['version'] : '';
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/llxmltranslate/mod1/index.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/llxmltranslate/mod1/index.php']);
}
?>