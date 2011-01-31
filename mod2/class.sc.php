<?php

if (!defined('TYPO3_MODE'))	die('cannot include like that!');

require(dirname(__FILE__).'/'.$BACK_PATH.'template.php');
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
				'1' => $LANG->getLL('function_settings'),
				'2' => $LANG->getLL('function_translateFile'),
				'4' => $LANG->getLL('function_generateCached'),
				'10' => $LANG->getLL('function_export'),
				'11' => $LANG->getLL('function_merge'),
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
				$this->MOD_MENU['editLang'][$langKey] = $langKey.(' ['.$LANG->sL('LLL:EXT:setup/mod/locallang.xml:lang_'.$langKey).']');
			}
		}

			// Forcing a language upon a user (non-admins):
		if (!$GLOBALS['BE_USER']->isAdmin())	{
			$langKey = $GLOBALS['BE_USER']->user['lang'];
			if (!$langKey)	$langKey = 'default';
			$this->MOD_MENU['editLang'] = array();
			$this->MOD_MENU['editLang'][$langKey] = $langKey.(' ['.$LANG->sL('LLL:EXT:setup/mod/locallang.xml:lang_'.$langKey).']');
		} 

			// Load translation status content:
		$this->loadTranslationStatus();

			// Setting extension
		$this->MOD_MENU['llxml_extlist'] = $this->getExtList();

			// Call parent menu config function:
		parent::menuConfig();

			// Setting files list:
		$this->files = $this->getllxmlFiles_cached();
		$this->MOD_MENU['llxml_files'] = array('_INTERFACE'=> $LANG->getLL('selectbox_interface'));
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
			$this->MOD_MENU['llxml_files']['_CSH'] = $LANG->getLL('selectbox_csh');
			$this->MOD_MENU['llxml_files'] = array_merge($this->MOD_MENU['llxml_files'], $csh_array);
		}

			// Call parent menu config function:
		parent::menuConfig();
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
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS,$LANG;

			// Draw the header.
		$this->doc = t3lib_div::makeInstance('noDoc');
		$this->doc->backPath = $BACK_PATH;
		$this->doc->form = '<form action="index.php" method="post" name="llxmlform" enctype="'.$GLOBALS['TYPO3_CONF_VARS']['SYS']['form_enctype'].'">';
		$this->doc->docType = 'xhtml_trans';
		$this->doc->inDocStylesArray[] = '
			table#translate-table {width:100%;}
			table#translate-table TR TD, table#translate-table INPUT, table#table-merge input { font-size: '.$this->MOD_SETTINGS['fontsize'].'; }
			table#table-merge, table#table-merge td {border:1px solid #000;border-spacing: 0px;border-collapse: collapse; }
			table#table-merge input, table#table-merge textarea {border:0px;}
			.typo3-green { color: #008000; }
			
			table#translate-table th, table#table-merge th {padding:4px;font-size:13px;text-align:left;}
			table#translate-table td.csh_link {background-color:#ff6600;padding:2px;}
			table#translate-table td.csh_link a {color:#fff;font-weight:bold;}
			table#table-merge td {padding:2px;}
			
			table#table-merge input:focus, table#translate-table input:focus, table#table-merge textarea:focus, table#translate-table textarea:focus {background-color:#FFFFDF;border:1px solid #009900;padding:3px;}
			
			h3.uppercase {background-color:#BC3939;color:#fff;padding:2px 0 2px 5px}
			h4#merge {background-color:#59718F;color:#fff;padding:2px;clear:both;}
			
			p.collapse {background-color:#8490A0;margin:0.5em 0;padding:0.2em;clear:both;}
			p.warning {background-color:#FF6600;color:#fff;padding:2px;font-weight:bold;}
			p.collapse span.switch {display:block; color:#fff; font-weight:bold;cursor:pointer;}
			p.collapse .typo3-csh-link {float:left;}
			
			div.typo3-noDoc {width:98%;}
			div#nightly {margin-bottom:80px;}
			
			div.langbox {display:block;width:200px;float:left;}
			
			div.save_button {float:right;margin-bottom:5px;}
			table#translate-table {margin-bottom:5px;}
			select {font-family:monospace;font-size:'.$this->MOD_SETTINGS['fontsize'].'}
		';

		// FORCING charset to utf-8 (since all locallang-XML files are in UTF-8!)
		$GLOBALS['LANG']->charSet = 'utf-8';

			// JavaScript
		$this->doc->JScode = $this->doc->wrapScriptTags('
				script_ended = 0;
				function jumpToUrl(URL)	{
					document.location = URL;
				}
				
				function switchMenu(obj) {
					var el = document.getElementById(obj);
					if ( el.style.display != "none" ) {
						el.style.display = "none";
					}
					else {
						el.style.display = "";
					}
				}
		');

		$this->content.=$this->doc->startPage($LANG->getLL('title_llxml'));
		$this->content.=$this->doc->header($LANG->getLL('header_llxml'));
		$this->content.=$this->doc->divider(5);
		$this->content.=$this->doc->section('',$this->showCSH().t3lib_BEfunc::getFuncMenu('','SET[function]',$this->MOD_SETTINGS['function'],$this->MOD_MENU['function']));
		
		$this->content.= $GLOBALS['BE_USER']->isAdmin() ? $LANG->getLL('select_language').' '.t3lib_BEfunc::getFuncMenu('','SET[editLang]',$this->MOD_SETTINGS['editLang'],$this->MOD_MENU['editLang']):'';

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

		global $LANG;

		// Function menu branching:
		switch((string)$this->MOD_SETTINGS['function'])	{
			case 1:
				$this->content.=$this->doc->section($LANG->getLL('function_settings'),$this->renderSettings(),0,1);
			break;
			case 2:

				// Saving submitted data:
				$result = $this->saveSubmittedData();
				
				// Re-generating file list:
				$files = $this->getllxmlFiles_cached(TRUE);

				// Re-generate status
				$statInfo = $this->loadTranslationStatus($files);
				$this->content.= $this->doc->section($LANG->getLL('function_translateFile'),$this->renderTranslate($result),0,1);
			break;
			case 4:

					// Re-generating file list:
				$files = $this->getllxmlFiles_cached(TRUE,TRUE);

					// Re-generate status
				$statInfo = $this->loadTranslationStatus($files);
				
				$selExt = t3lib_BEfunc::getFuncMenu('', 'SET[llxml_extlist]', $this->MOD_SETTINGS['llxml_extlist'], $this->MOD_MENU['llxml_extlist']);
				$selExt = preg_replace('/<option /', '<option style="' . $style . '" ', $selExt);
		
				$this->content.= $this->doc->section($LANG->getLL('function_generateCached'),
						$LANG->getLL('select_extension'). $selExt . '<br />'.
						$this->showCSH('funcmenu_'.$this->MOD_SETTINGS['function'],$LANG->getLL('statistics')).'<br />'.
						t3lib_div::view_array($statInfo),0,1);
			break;
			case 10:	// Export
				$this->content.=$this->doc->section($LANG->getLL('function_export'),$this->renderExport(),0,1);
			break;
			case 11:	// Export
					// Saving submitted data:
				$result = $this->saveSubmittedData();
				$this->content.=$this->doc->section($LANG->getLL('function_merge'),$this->renderMerge(),0,1);
				
				if ($result[$LANG->getLL('table_savelog')] || $result[$LANG->getLL('table_errors')] ) {
					$this->content.='<p class="collapse warning"><span class="switch" title="'.$LANG->getLL('show_saving_messages').'" onclick="switchMenu(\'results\');">'.$this->showCSH('funcmenu_13_savelog',$LANG->getLL('saving_messages')).'</span><div id="results" style="display:none">'.t3lib_div::view_array($result).'</div>';
				}
			break;
		}
		
		// General notice:
		$this->content.= '<p class="collapse""><span class="switch" title="'.$LANG->getLL('show_box_nightly').'" onclick="switchMenu(\'nightly\');">'.$this->showCSH('funcmenu_12_nightly',$LANG->getLL('nightly_status')).'</span></p>';
		$this->content.= '<div id="nightly"><a href="'.$this->doc->backPath.'../typo3conf/l10n/status.html" target="_blank">typo3conf/l10n/status.html</a> | <a href="http://translation.typo3.org/typo3conf/l10n/status.html" target="_blank">http://translation.typo3.org/typo3conf/l10n/status.html</a></div>';
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

			// Create checkboxes for additiona languages to show:
		$checkOutput = array();
		foreach($this->langKeys as $langKey)	{
			if ($langKey != 'default')	{
				$checkOutput[] = '<div class="langbox">'.t3lib_BEfunc::getFuncCheck('','SET[addLang_'.$langKey.']',$this->MOD_SETTINGS['addLang_'.$langKey]).' '.$langKey.(' ['.$LANG->sL('LLL:EXT:setup/mod/locallang.xml:lang_'.$langKey).']').'</div>';
			}
		}
		$out.= $this->showCSH('funcmenu_'.$this->MOD_SETTINGS['function'],$LANG->getLL('additional_languages')).'<br />'.
				implode('',$checkOutput);

			// Select font size:
		$out.= '<h3 style="clear:both">'.$LANG->getLL('select_font').'</h3>'.
				t3lib_BEfunc::getFuncMenu('','SET[fontsize]',$this->MOD_SETTINGS['fontsize'],$this->MOD_MENU['fontsize']);

			// Return output:
		return $out;
	}

	/**
	 * Render translation screen
	 *
	 * @return	string		HTML
	 */
	function renderTranslate($result)	{
		global $LANG;

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
				
			// Selecting file:
		$style = 'white-space: pre;';
		$selExt = t3lib_BEfunc::getFuncMenu('', 'SET[llxml_extlist]', $this->MOD_SETTINGS['llxml_extlist'], $this->MOD_MENU['llxml_extlist']);
		$selExt = preg_replace('/<option /', '<option style="' . $style . '" ', $selExt);
		$content .= $this->showCSH('funcmenu_'.$this->MOD_SETTINGS['function'],$LANG->getLL('select_extension')). $selExt . '<br />';
		$content .= $LANG->getLL('select_file').t3lib_BEfunc::getFuncMenu('','SET[llxml_files]',$this->MOD_SETTINGS['llxml_files'],$this->MOD_MENU['llxml_files']);
		
		if ($this->files[$this->MOD_SETTINGS['llxml_files']])	{
			$formcontent = '';
			
			if ($result[$LANG->getLL('table_savelog')] || $result[$LANG->getLL('table_errors')] ) {
				$formcontent.='<p class="collapse warning"><span class="switch" title="'.$LANG->getLL('show_saving_messages').'" onclick="switchMenu(\'results\');">'.$this->showCSH('funcmenu_13_savelog',$LANG->getLL('saving_messages')).'</span><div id="results" style="display:none">'.t3lib_div::view_array($result).'</div>';
			}

				// Defining file and getting content:
			$file = $this->files[$this->MOD_SETTINGS['llxml_files']];
			$formcontent.= $this->createEditForm($file,true);

				// Put form together:
			$content.= '<br/>
				<div class="save_button">'.$this->showCSH('funcmenu_2_saving').'
				<input type="submit" name="_save" value="'.$LANG->getLL('save_button').'" /></div>
				'.$LANG->getLL('update_all_value').'<input type="checkbox" name="updateAllValues" value="1" />
				'.
				$formcontent.'

				<div class="save_button" style="margin-bottom:0.5em;"><input type="submit" name="_save" value="'.$LANG->getLL('save_button').'" /></div>
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
		global $LANG;

		$selExt = t3lib_BEfunc::getFuncMenu('', 'SET[llxml_extlist]', $this->MOD_SETTINGS['llxml_extlist'], $this->MOD_MENU['llxml_extlist']);
		$selExt = preg_replace('/<option /', '<option style="' . $style . '" ', $selExt);
				
		$content.=  $LANG->getLL('select_extension'). $selExt . '<br />';
			
			// Adding language selector:
		$content.=$this->showCSH('funcmenu_'.$this->MOD_SETTINGS['function'],$LANG->getLL('select_export_file'));

		if (!t3lib_div::_POST('_export'))	{
				// Create file selector box:
			$opt = array();
			$opt[] = '<option value="" disabled>'.$LANG->getLL('selectbox_interface').'</option>';

				// All non-CSH:
			foreach($this->files as $fN)	{
				if (!t3lib_div::isFirstPartOfStr(basename($fN),'locallang_csh'))	{
					$opt[] = '<option value="'.htmlspecialchars($fN).'">'.htmlspecialchars($fN).'</option>';
				}
			}

			if ($this->checkCSH(''))	{
					// All CSH:
				$opt[] = '<option value=""></option>';
				$opt[] = '<option value="" disabled>'.$LANG->getLL('selectbox_csh').'</option>';
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
				<input type="submit" name="_export" value="'.$LANG->getLL('export').'" />
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
							} else die(sprintf($LANG->getLL('no_xml_output'),$fileRef));
						} else die(sprintf($LANG->getLL('invalid_not_exist'),$fileRef));
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
		global $LANG;

		if (!t3lib_div::_POST('_uploaded'))	{

				// Create selector for files:
			$opt = array();
			$opt[] = '<option></option>';
			foreach($this->MOD_MENU['llxml_files'] as $fileRef => $label)	{
				$opt[] = '<option value="'.htmlspecialchars($fileRef).'">'.htmlspecialchars($label).'</option>';
			}
			
			$selExt = t3lib_BEfunc::getFuncMenu('', 'SET[llxml_extlist]', $this->MOD_SETTINGS['llxml_extlist'], $this->MOD_MENU['llxml_extlist']);
			$selExt = preg_replace('/<option /', '<option style="' . $style . '" ', $selExt);
			// Put form together:		
			$content.=  '
					'.$LANG->getLL('select_extension'). $selExt . '<br />
					'.$LANG->getLL('select_specific_file').'<select name="specFile">'.implode('',$opt).'</select><br/>
					'.$this->showCSH('funcmenu_'.$this->MOD_SETTINGS['function'],$LANG->getLL('upload_merge')).'<input type="file" size="60" name="upload_merge_file" /><br/>
					'.$LANG->getLL('show_only_file').'<input type="checkbox" name="showFileIndexOnly" value="1" /><br/>
					<input type="submit" name="_uploaded" value="'.$LANG->getLL('upload_merge_file_button').'" />
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
		global $BE_USER, $TCA,$LANG;

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
						$keyParts = explode('.',preg_replace('/^_/','',$key));
						$mainKeys[] = $keyParts[0];
					}
					$mainKeys = array_unique($mainKeys);

						// Traverse main keys:
					$allKeys = array_flip($allKeys);
					foreach($mainKeys as $mKey)	{
							// Header for CSH item.
						$itemRow.= '
							<tr>
								<td colspan="4" class="csh_link">&nbsp;<a href="#" title="'.$LANG->getLL('see_csh_in_context').'" onclick="'.htmlspecialchars($this->openCSH($xmlArray['meta']['csh_table'].'.'.$mKey)).'">'.
									$mKey.
									'</a></td>
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
								<td colspan="4" bgcolor="#ff6600"><b>'.$LANG->getLL('remaining').'</b></td>
							</tr>';
						foreach($allKeys as $remKey => $temp)	{
							$itemRow.= $this->createEditForm_getItemRow($xmlArray,$relFileRef,$remKey);
						}
					}
				} else { // Normal:
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
			$output = '';
			
			$output.= '<p class="collapse">
			<span class="switch" title="'.$LANG->getLL('show_box_fileinfo').'" onclick="switchMenu(\'fileinfo\');">'.$this->showCSH('funcmenu_2_stat', $LANG->getLL('box_fileinfo')).'</span></p>
			<div id="fileinfo" style="display:none">'.
			$this->llxmlFileInfoBox($xmlArray,$relFileRef).'</div>
			
			<p class="collapse">
			<span class="switch" title="'.$LANG->getLL('show_box_statistics').'" onclick="switchMenu(\'stats\');">'.$this->showCSH('funcmenu_2_stat', $LANG->getLL('box_statistics')).'</span></p>
			<div id="stats" style="display:none">
			
			<!-- STATUS: -->
			<table border="0" cellpadding="1" cellspacing="1" style="border: 1px solid black;">
				<tr bgcolor="#009900">
					<td style="color:#fff"><b>'.$LANG->getLL('caption_ok').'</b></td>
					<td style="color:#fff">'.htmlspecialchars(count($this->labelStatus[$editLang][$relFileRef]['ok'])).'</td>
				</tr>
				<tr bgcolor="#6666ff">
					<td><b>'.$LANG->getLL('caption_new').'</b></td>
					<td>'.htmlspecialchars(count($this->labelStatus[$editLang][$relFileRef]['new'])).'</td>
				</tr>
				<tr bgcolor="#ff6666">
					<td><b>'.$LANG->getLL('caption_changed').'</b></td>
					<td>'.htmlspecialchars(count($this->labelStatus[$editLang][$relFileRef]['changed'])).'</td>
				</tr>
				<tr bgcolor="#ff6600">
					<td><b>'.$LANG->getLL('caption_unknown').'</b></td>
					<td>'.htmlspecialchars(count($this->labelStatus[$editLang][$relFileRef]['unknown'])).'</td>
				</tr>
			</table>
			</div>

			<p class="collapse">
			<span class="switch" title="'.$LANG->getLL('show_box_translationinterface').'" onclick="switchMenu(\'translationinterface\');">'.$this->showCSH('funcmenu_2_translationinterface'.($xmlArray['meta']['type'] == 'CSH' ? '_csh' : ''),$LANG->getLL('box_translationinterface')).'</span></p>
			<div id="translationinterface">

			<!-- Translation table: -->
			<table border="0" cellpadding="1" cellspacing="1" id="translate-table">'.$itemRow.'
			</table></div>';


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
				<h4>'.sprintf($LANG->getLL('image_overview'),dirname($relFileRef)).'</h4>
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
		GLOBAL $LANG;

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
			if ($xmlArray['meta']['type'] == 'CSH' && preg_match('/\.seeAlso$/',$labelKey))	{
				$opt = array();
					$opt[] = '
						<option value="">'.$LANG->getLL('see_also').'</option>';
					$opt[] = '
						<option value="[Link Title] | http://..../">'.$LANG->getLL('add_regular_url').'</option>';
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
			if ($xmlArray['meta']['type'] == 'CSH' && preg_match('/\.image$/',$labelKey))	{
				$opt = array();
					$opt[] = '
						<option value="">'.$LANG->getLL('image').'</option>';
				$images = t3lib_div::getFilesInDir(PATH_site.dirname($relFileRef).'/cshimages','gif,jpg,jpeg,png',1);
				$this->lastImages = array();
				foreach($images as $link)	{
					$link = preg_replace('/.*ext\//','EXT:',$link);
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
					$descrArray = explode(chr(10),$dataArray['default'][preg_replace('/^_/','',$labelKey).'_descr'],count($images));
					foreach($images as $kk => $ref)	{
						$image = t3lib_div::getFileAbsFileName($ref);
						if ($image)	{
							$imageRelPath = '../'.substr($image,strlen(PATH_site));
							$this->lastImages_count[$ref]++;

							$selector.='
								<hr/>'.$ref.'<br/>
								<img src="'.$GLOBALS['BACK_PATH'].$imageRelPath.'" alt="" style="border:1px solid black;" />
								<p><b>'.$LANG->getLL('description').'</b> <em>'.htmlspecialchars($descrArray[$kk]).'</em></p>';
						} else {
							$selector.='
								<hr/>'.$ref.' <b>'.$LANG->getLL('not_found').'</b><br/>
								<p><b>'.$LANG->getLL('description').'</b> <em>'.htmlspecialchars($descrArray[$kk]).'</em></p>';
						}
					}
				}
			}

				// Default description:
			if ($editLang == 'default')	{
				$contextLabel = $LANG->getLL('context').'<input name="'.htmlspecialchars('labelContext['.$relFileRef.']['.$labelKey.']').'" '.$GLOBALS['TBE_TEMPLATE']->formWidth(30).' value="'.htmlspecialchars(trim($xmlArray['meta']['labelContext'][$labelKey])).'" />';
			} else {
				if (is_array($dataArray['default'][$labelKey])) {
					printf("D \$labelKey=$labelKey, \$editLang=$editLang, \$relFileRef=$relFileRef\n");
				}
				if (is_array($xmlArray['meta']['labelContext'][$labelKey])) {
					printf("X \$labelKey=$labelKey, \$editLang=$editLang, \$relFileRef=$relFileRef\n");
				}
				$contextLabel = nl2br(htmlspecialchars($dataArray['default'][$labelKey])).
					($xmlArray['meta']['labelContext'][$labelKey] ? '<hr/>'.$LANG->getLL('context').'<em>'.htmlspecialchars($xmlArray['meta']['labelContext'][$labelKey]).'</em>' : '');
			}
			$tCells[] = '<td>'.
				$contextLabel.
				$selector.
				'</td>';


				// Prepare status of the label:
			$bgcolor = '';
			$color = '#000';
			if (strlen(trim($dataArray[$editLang][$labelKey])))	{
				$orig_hash = $xmlArray['orig_hash'][$editLang][$labelKey];
				$new_hash = t3lib_div::md5int($dataArray['default'][$labelKey]);
				if ($editLang!='default' && $orig_hash != $new_hash)	{
					if (!$orig_hash)	{
						$st = $LANG->getLL('status_interrogation');
						$bgcolor = '#ff6600';
						$this->labelStatus[$editLang][$relFileRef]['unknown'][] = $labelKey;
					} else {
						$st = $LANG->getLL('status_changed');
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
					$st = $LANG->getLL('status_ok');
					$bgcolor = '#009900';
					$color="#fff";
					$this->labelStatus[$editLang][$relFileRef]['ok'][] = $labelKey;
				}
			} else {
				$st = $LANG->getLL('status_new');
				$bgcolor = '#6666ff';
				$this->labelStatus[$editLang][$relFileRef]['new'][] = $labelKey;
			}


				// Editing field:
			$elValue = trim($dataArray[$editLang][$labelKey]);
			$elValueDefault = trim($dataArray['default'][$labelKey]);

		if (count(explode(chr(10),$elValue))>=2 || count(explode(chr(10),$elValueDefault))>=2 || $alwaysTextarea)	{
				$wrapOff = substr($labelKey,-8)=='.seeAlso' ? 'off' : '';
				if (empty($elValue)) $line=round(strlen($elValueDefault)/70); else $line=round(strlen($elValue)/70);
				$formElement = '<textarea name="'.htmlspecialchars($elName).'" rows="'.$line.'" wrap="'.$wrapOff.'" '.$GLOBALS['TBE_TEMPLATE']->formWidthText(50,'',$wrapOff).'>'.t3lib_div::formatForTextarea($elValue).'</textarea>';
				
			}
			else if (strlen($elValue)>100 ||strlen($elValueDefault)>100) {
				$line=round(strlen($elValue)/70);
				$formElement = '<textarea name="'.htmlspecialchars($elName).'" rows="'.$line.'" '.$GLOBALS['TBE_TEMPLATE']->formWidthText(50,'','').'>'.t3lib_div::formatForTextarea($elValue).'</textarea>';
			} else {
				$formElement = '<input name="'.htmlspecialchars($elName).'" '.$GLOBALS['TBE_TEMPLATE']->formWidth(50).' value="'.htmlspecialchars($elValue).'" />';
			}
#debug(t3lib_div::debug_ordvalue($elValue),$elName);
			$tCells[] = '<td bgcolor="'.$bgcolor.'">'.$formElement.'</td>';

				// Status label:
			$tCells[] = '<td bgcolor="'.$bgcolor.'" style="color:'.$color.';text-align:center;">'.$st.'</td>';

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
		GLOBAL $LANG;
		if ($this->MOD_SETTINGS['editLang'])	{

				// Width of each label column is set by a clear-gif:
			$clearGif = '<br/><img src="clear.gif" width="250" height="1" alt="" />';

			$tCells = array();
			$tCells[] = '<th>'.$LANG->getLL('form_key').'</th>';
			$tCells[] = '<th style="width:50%">'.$LANG->getLL('form_default').$clearGif.'</th>';
			$tCells[] = '<th>'.$this->MOD_SETTINGS['editLang'].'</th>';
			$tCells[] = '<th>'.$LANG->getLL('form_status').'</th>';

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
		GLOBAL $LANG;

			// Total files
		$totalFile = 0;
		
			// Read uploaded file:
		$uploadedTempFile = t3lib_div::upload_to_tempfile($GLOBALS['HTTP_POST_FILES']['upload_merge_file']['tmp_name']);
		list($hash,$fileContent) = explode(':',t3lib_div::getUrl($uploadedTempFile),2);
		$fileContent = preg_replace('/[[:space:]]/','',$fileContent);

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
							$totalFile++;
							if (!$specFile || !strcmp($specFile,$fileRef))	{
								$formcontent.='<h4 id="merge">'.$LANG->getLL('merge_file').' '.htmlspecialchars($fileRef).'</h4>';

								if (in_array($fileRef, $this->files))	{
									$xmlArray = $this->getXMLdata($fileRef);
									$clearGif = '<br/><img src="clear.gif" width="250" height="1" alt="" />';

									$rows = array();
									$rows[] = '
										<tr class="bgColor5">
											<th>'.$LANG->getLL('merge_point').'</td>
											<th style="width:50%;">'.$LANG->getLL('form_default').$clearGif.'</td>
											<th>'.$editLang.' '.$LANG->getLL('local').$clearGif.'</td>
											<th>'.$editLang.' '.$LANG->getLL('from_merge_point').'</td>
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
													$line=round(strlen($labelValue)/70);
													$wrapOff = substr($labelKey,-8)=='.seeAlso' ? 'off' : '';
													$formElement = '<textarea name="'.htmlspecialchars($elName).'" rows="'.$line.'" wrap="'.$wrapOff.'" '.$GLOBALS['TBE_TEMPLATE']->formWidthText(50,'',$wrapOff).'>'.t3lib_div::formatForTextarea($labelValue).'</textarea>';
												} elseif (strlen($labelValue)>100) {
													$line=round(strlen($labelValue)/70);
													$formElement = '<textarea name="'.htmlspecialchars($elName).'" rows="'.$line.'" '.$GLOBALS['TBE_TEMPLATE']->formWidthText(50,'','').'>'.t3lib_div::formatForTextarea($labelValue).'</textarea>';
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
														$checkBox = '<span style="background-color: #666666;"><input name="check_'.$elName.'" type="checkbox" value="1" /> <br/>'.$LANG->getLL('message_uncertainty_about_original_label').'</span>';
													} else {
														$checkBox = '<span style="background-color: red;"><input name="check_'.$elName.'" type="checkbox" value="1" /> <br/>'.$LANG->getLL('message_newvalue_not_translated').'</span>';
													}
												} else {
													$checkBox = '<input name="check_'.$elName.'" type="checkbox" value="1" checked="checked" />';
												}

													// Compile row:
												$rows[] = '
													<tr class="bgColor4">
														<td style="text-align:center;">'.$checkBox.'</td>
														<td>'.nl2br(htmlspecialchars($elValueDefault)).'</td>
														<td>'.nl2br(htmlspecialchars($elValue)).'</td>
														<td>'.$formElement.'</td>
													</tr>
													<tr class="bgColor4">
													<td colspan="2">&nbsp;</td>
													<td colspan="2" style="background-color:#fff;">'.$diffHTML.'</td>
													</tr>
												';
											}

										} else $errors[] = sprintf($LANG->getLL('no_key_with_name'),$labelKey);
									}


									if (count($rows)>1)	{
										$formcontent.=
											'<p class="collapse">
											'.t3lib_BEfunc::cshItem('_MOD_txllxmltranslateM1', 'funcmenu_2_fileinfo', $this->doc->backPath,'').'
											<a class="switch" title="'.$LANG->getLL('show_box_fileinfo').'" onclick="switchMenu(\'fileinfo'.$totalFile.'\');">'.$LANG->getLL('box_fileinfo').'</a></p>
											<div id="fileinfo'.$totalFile.'" style="display:none;margin-bottom:1em;">'.$this->llxmlFileInfoBox($xmlArray,$fileRef).'</div>'.
											(!$fileIndexOnly ? '
											<table id="table-merge">
												'.implode('', $rows).'
											</table>'.
											t3lib_div::view_array($errors) : $LANG->getLL('change_to_submit').' '.(count($rows)-1));
									} else {
										$formcontent.= $LANG->getLL('no_change_file').'<br/>';
									}
								} else {
									$formcontent.= '<p class="warning">'.sprintf($LANG->getLL('error_file_not_local'),$fileRef).' 
									'.t3lib_div::view_array($labelValues).'</p>';
								}
							}
						}

							// Put form together:
						$content.= '
							<div class="save_button" style="margin-bottom:0.5em;">
								<input type="submit" name="_save" value="'.$LANG->getLL('save_button').'" />
							</div>

							'.$formcontent.'

							<div class="save_button" style="margin-bottom:0.5em;">
								<input type="hidden" name="checkboxMode" value="1" />
								<input type="submit" name="_save" value="'.$LANG->getLL('save_button').'" />
							</div>
							';
					} else $content.= '<p class="warning">'.$LANG->getLL('no_file_strange').'</p>';
				} else $content.= '<p class="warning">'.sprintf($LANG->getLL('error_file_not_local'),$mergeContent['meta']['language'],$editLang).'</p>';
			} else $content.=  '<p class="warning">'.$LANG->getLL('error_strange_no_array').'</p>';
		} else $content.= '<p class="warning">'.$LANG->getLL('error_invalid_file_content').'</p>';

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
		GLOBAL $LANG;
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
															$saveLog[$fileRef][$LANG->getLL('savelog_data')][$langKey][$labelKey] = $labelValue;

															$save = TRUE;
														}

															// Update orig-hash
														if ($langKey!='default')	{
															if (strlen($labelValue))	{
																$origHash = t3lib_div::md5int($xmlArray['data']['default'][$labelKey]);
																if ($updateAllValues || $origHash != $xmlArray['orig_hash'][$langKey][$labelKey])	{
																	$xmlArray['orig_hash'][$langKey][$labelKey] = $origHash;
																	$saveLog[$fileRef][$LANG->getLL('savelog_orig_hash')][$langKey][$labelKey] = $LANG->getLL('savelog_updated');

																	$save = TRUE;
																}

																#debug(array($xmlArray['orig_text'][$langKey][$labelKey] , $xmlArray['data']['default'][$labelKey]));
																if ($updateAllValues || $xmlArray['orig_text'][$langKey][$labelKey] != $xmlArray['data']['default'][$labelKey])	{
																	$xmlArray['orig_text'][$langKey][$labelKey] = $xmlArray['data']['default'][$labelKey];
																	$saveLog[$fileRef][$LANG->getLL('savelog_orig_text')][$langKey][$labelKey] = $LANG->getLL('savelog_updated');

																	$save = TRUE;
																}
															}
														} else {
															unset($xmlArray['orig_hash']['default']);
															unset($xmlArray['orig_text']['default']);
														}
													}
												} else $errorLog[$fileRef][] = sprintf($LANG->getLL('error_label_not_found'),$labelKey);
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
														$errorLog[$fileRef][] = array($LANG->getLL('error_illegal_key'),$diff);
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
														$saveLog[$fileRef][$LANG->getLL('savelog_messages',true)][$LANG->getLL('message_removed_blanks',true)][] = $labelKey;
													}
												}

													// Setting log messages:
												$saveLog[$fileRef][$LANG->getLL('savelog_messages',true)][] = $LANG->getLL('serialized_lgd').' '.strlen(serialize($xmlArray['data'][$langKey]));
												$saveLog[$fileRef][$LANG->getLL('savelog_messages',true)][] = $LANG->getLL('serialized_hash').' '.md5(serialize($xmlArray['data'][$langKey]));
											}
										} else $errorLog[$fileRef][] = sprintf($LANG->getLL('error_label_not_found'),$langKey);
									} else $errorLog[$fileRef][] = sprintf($LANG->getLL('error_not_language_key'),$langKey);
								}
							} else $errorLog[$fileRef][] = sprintf($LANG->getLL('error_post_data_not_array'),$fileRef);

								// Context?
							if (is_array($labelContext[$fileRef]))	{
								$xmlArray['meta']['labelContext'] = $labelContext[$fileRef];
								foreach($xmlArray['meta']['labelContext'] as $kk => $vv)	{
									if (!strlen(trim($vv)))	unset($xmlArray['meta']['labelContext'][$kk]);
								}
								$save = TRUE;
								$saveLog[$fileRef]['labelContext'] = $LANG->getLL('savelog_updated');
							}

								// Saving modifications:
							if ($save)	{
									// Create / Save XML:
								$this->saveXMLdata($fileRef, $xmlArray, $saveLog, $errorLog);
							}
						} else $errorLog[$fileRef][] = sprintf($LANG->getLL('error_file_not_contain_xml_array'),$fileRef);
					} else $errorLog[$fileRef][] = sprintf($LANG->getLL('error_file_reference_not_valid'),$fileRef);
				}
			} else $errorLog[$LANG->getLL('table_errors')][] = $LANG->getLL('error_input_post_not_array');
			
				// Return logs:
			return array(
				$LANG->getLL('table_savelog') => $saveLog,
				$LANG->getLL('table_errors') => $errorLog
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
		GLOBAL $LANG;
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
					echo t3lib_div::view_array(array($LANG->getLL('error_not_find_reference'),$dataArray['data'][$editLang]));
					$extArray = array();
				}

				$dataArray['data'][$editLang] = is_array($extArray['data'][$editLang]) ? $extArray['data'][$editLang] : array();
				$dataArray['orig_hash'][$editLang] = is_array($extArray['orig_hash'][$editLang]) ? $extArray['orig_hash'][$editLang] : array();
				$dataArray['orig_text'][$editLang] = is_array($extArray['orig_text'][$editLang]) ? $extArray['orig_text'][$editLang] : array();
			}
		} else {
			echo sprintf($LANG->getLL('error_file_not_contain_xml_array_output'),$fileRef);
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
		GLOBAL $LANG;

			// Getting MAIN ll-XML file content and editing language:
		$dataArray = t3lib_div::xml2array(t3lib_div::getUrl(PATH_site . $fileRef));
		$editLang = $this->MOD_SETTINGS['editLang'];

		if (is_array($dataArray))	{

				// If no entry is found for the language key, then force a value depending on meta-data setting. By default an automated filename will be used:
			$autoFileName = '';
			if ($editLang!='default') {
				// Looking for external storage for edit language if not default:
				$autoFileName = t3lib_div::llXmlAutoFileName(PATH_site . $fileRef, $editLang);
				if (!isset($dataArray['data'][$editLang]) || t3lib_div::_POST('_moveToExternalFile') || @file_exists(PATH_site . $autoFileName)) {
					$dataArray['data'][$editLang] = $autoFileName;
				}

				// If autoFileName, check if exists, if not, create:
//				if ($autoFileName)	{
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
								$saveLog[$fileRef][$LANG->getLL('create_new_external_file',true)] = substr($extFile,strlen(PATH_site));
							} else $errorLog[$fileRef][] = sprintf($LANG->getLL('error_tried_to_create'),$extFile);
						} else $errorLog[$fileRef][] = $LANG->getLL('error_path_not_in_typo3conf');
					}
//				}

					// Looking for external file settings:
				if (is_string($dataArray['data'][$editLang]) && strlen($dataArray['data'][$editLang]))	{

						// Getting the file:
					$extFile = t3lib_div::getFileAbsFileName($dataArray['data'][$editLang]);
					if ($extFile && @is_file($extFile))	{
						$saveLog[$fileRef]['Messages'][] = $LANG->getLL('error_found_external_file').' '.$dataArray['data'][$editLang];

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
							if (t3lib_div::_POST('updateAllValues') || t3lib_div::getUrl($extFile) != md5($XML)) {
								t3lib_div::writeFile($extFile, $XML);

									// Checking if the localized file was saved as it should be:
								if (md5(t3lib_div::getUrl($extFile)) == md5($XML))	{
									$saveLog[$fileRef][$LANG->getLL('savelog_saving_external')] = $LANG->getLL('done_upper');
								} else {
									$tempFile = t3lib_div::tempnam('llxml_');
									t3lib_div::writeFile($tempFile, $XML);
									$errorLog[$fileRef][] = sprintf($LANG->getLL('error_write_access_problem_external'),$extFile,$tempFile);
								}
							} else {
								$saveLog[$fileRef][$LANG->getLL('savelog_saving_external')] = $LANG->getLL('savelog_xml_content_not_change');
							}
						} else $errorLog[$fileRef][] = $LANG->getLL('error_could_not_find_xml').' '.$dataArray['data'][$editLang];
					} else $errorLog[$fileRef][] = $LANG->getLL('error_could_not_find_file').' '.$dataArray['data'][$editLang];
				}
			}

				// Unsetting the hash and original text for default (just because it does not make sense!)
			unset($xmlArray['meta']['_ORIG_LANGUAGE_DATA']);
			unset($xmlArray['orig_hash']['default']);
			unset($xmlArray['orig_text']['default']);

				// Save MAIN file:
			if (!@is_writeable(PATH_site . $fileRef)) {
				$errorLog[$fileRef][] = sprintf($LANG->getLL('error_file_not_writable'),$fileRef);
			}
			else {
				$XML = $this->createXML($xmlArray);
				if (md5(t3lib_div::getUrl(PATH_site.$fileRef)) != md5($XML))	{
					t3lib_div::writeFile(PATH_site.$fileRef, $XML);

						// Checking if the main file was saved as it should be:
					if (md5(t3lib_div::getUrl(PATH_site.$fileRef)) == md5($XML))	{
						$saveLog[$fileRef][$LANG->getLL('savelog_saving_main')] = $LANG->getLL('done_upper');
					} else {
						$tempFile = t3lib_div::tempnam('llxml_');
						t3lib_div::writeFile($tempFile, $XML);
						$errorLog[$fileRef][] = sprintf($LANG->getLL('savelog_saving_main'),$fileRef,$tempFile);
					}
				} else {
					$saveLog[$fileRef][$LANG->getLL('savelog_saving_main')] = $LANG->getLL('savelog_xml_content_not_change');
				}
			}
		} else die($LANG->getLL('warning_wrong_file').' '.$fileRef);
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

		if (!$extPath) {
			// Traverse extension locations:
			foreach($this->extPathList as $path)	{
				if (is_dir(PATH_site . $path))	{
					$files = t3lib_div::getAllFilesAndFoldersInPath($files, PATH_site . $path, 'xml');
				}
			}
		}
		else {
			$files = t3lib_div::getAllFilesAndFoldersInPath($files, $extPath . '/', 'xml');
		}

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
	 * @param	boolean		If set, then get ALL locallang-XML files and not specific files
	 * @return	array		Array of files.
	 */
	function getllxmlFiles_cached($regenerate = false,$all = false)	{
		$set = t3lib_div::_GP('SET');
		$extPath = $set['llxml_extlist'] ? $set['llxml_extlist'] : $this->MOD_SETTINGS['llxml_extlist'];
		$ext = $this->MOD_MENU['llxml_extlist'][$extPath];
		if ($ext == '' && $all == FALSE) {
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
		global $BE_USER,$LANG;

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

					$count[$cKey][$LANG->getLL('caption_ok')] += count($this->labelStatus[$editLang][$relFileRef]['ok']);
					$count[$cKey][$LANG->getLL('caption_changed')] += count($this->labelStatus[$editLang][$relFileRef]['changed']);
					$count[$cKey][$LANG->getLL('caption_unknown')] += count($this->labelStatus[$editLang][$relFileRef]['unknown']);
					$count[$cKey][$LANG->getLL('caption_new')] += count($this->labelStatus[$editLang][$relFileRef]['new']);
				}
			}

			$statInfo[$LANG->getLL('cache_labels_missing')] = $count['LABELS']['changed'] + $count['LABELS']['new'];
			$statInfo[$LANG->getLL('cache_parsetime')] = t3lib_div::milliseconds()-$pt;
			$statInfo[$LANG->getLL('cache_labels')] = $count['LABELS'];
			$statInfo[$LANG->getLL('cache_csh')] = $count['CSH'];

			$BE_USER->setAndSaveSessionData('tx_llxmltranslate:status', $this->labelStatus);
		}

		return $statInfo;
	}

	/**
	 * Used to generate the HTML for "typo3conf/l10n/status.html" - see CLI script.
	 */
	function writeReportForAll()	{
		GLOBAL $LANG;
		
		@ob_end_clean();
		ob_start();
		$startTime = time();
		echo '<h2>'.$LANG->getLL('report_translation_status').' '.t3lib_BEfunc::dateTime(time()).'</h2>';

		$details = $header = '';

		$header.= '<tr>
		<td><b>'.$LANG->getLL('report_language').'</b></td>
		<td><b>'.$LANG->getLL('report_langkey').'</b></td>
		<td><b>'.$LANG->getLL('report_label_mising').'</b></td
		<td><b>'.$LANG->getLL('report_complete').'</b></td>
		<td><b>'.$LANG->getLL('report_missing').'</b></td>
		<td><b>'.$LANG->getLL('report_download').'</b></td>
		</tr>';
			// Find all files:
		$files = array();
		foreach($this->extPathList as $path)	{
			$files = array_merge($files, $this->getllxmlFiles(PATH_site . $path));
		}

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
		echo '<h3>'.$LANG->getLL('report_files').'</h3>';
		t3lib_div::debug($files);
		echo $details;

		echo '<hr>'.sprintf($LANG->getLL('report_files'),floor((time()-$startTime)/60),((time()-$startTime)%60));

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
	
		GLOBAL $LANG;

			// Get editing language:
		$editLang = $this->MOD_SETTINGS['editLang'];

			// Automatic external file message:
		$msg_autoFilename = '';
		$externalFile = t3lib_div::llXmlAutoFileName(PATH_site.$relFileRef, $editLang);
		if ($editLang=='default') {
			$msg_autoFilename = $LANG->getLL('msg_default_language');
		} elseif ($externalFile) {
			$msg_autoFilename = $externalFile;

			if (@file_exists(PATH_site . $externalFile)) {
				// Here is translated file exists but original file still keeps entries.
				// Happens when translating core and core language files are read-only
				$msg_autoFilename .= '<br/><span class="typo3-green"><b>'.sprintf($LANG->getLL('msg_translation_external_file'),$externalFile).'</b></span>';
			}
			elseif (isset($xmlArray['meta']['_ORIG_LANGUAGE_DATA']) && !is_array($xmlArray['meta']['_ORIG_LANGUAGE_DATA'])) {
				// Here is locallang.xml references external file like this:
				//        <languageKey index="dk">EXT:irfaq/lang/dk.locallang.xml</languageKey>
				$msg_autoFilename = $xmlArray['meta']['_ORIG_LANGUAGE_DATA'];
				$msg_autoFilename .= '<br /><span class="typo3-green"><b>'.sprintf($LANG->getLL('msg_translation_external_file'),$xmlArray['meta']['_ORIG_LANGUAGE_DATA']).'</b></span>';
			}
			elseif (is_array($xmlArray['meta']['_ORIG_LANGUAGE_DATA']))	{
				// Here if translations are inside main file
				$msg_autoFilename.='<br/><span class="typo3-red"><b>'.sprintf($LANG->getLL('msg_translation_main_file'),count($xmlArray['meta']['_ORIG_LANGUAGE_DATA']),'<input type="checkbox" name="_moveToExternalFile" value="1" checked="checked" /> ').'</b></span>';
			} else {
				// Here if no translation in main file, no references and no external file
				$msg_autoFilename.='<br/><span class="typo3-red"><b>'.$LANG->getLL('msg_does_not_exist').'</b></span>';
			}
		} else {
			$msg_autoFilename = '<span class="typo3-red"><b>'.$LANG->getLL('msg_file_not_in_extension').'</b></span>';
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
					<td><b>'.$LANG->getLL('description').'</b></td>
					<td>'.htmlspecialchars($xmlArray['meta']['description']).'</td>
				</tr>
				<tr class="bgColor4">
					<td><b>'.$LANG->getLL('type').'</b></td>
					<td>'.$theType.'</td>
				</tr>
				'.($xmlArray['meta']['type']=='CSH' ? '
				<tr class="bgColor4">
					<td><b>'.$LANG->getLL('csh_table').'</b></td>
					<td><a href="#" onclick="'.htmlspecialchars($this->openCSH($xmlArray['meta']['csh_table'])).'">'.htmlspecialchars($xmlArray['meta']['csh_table']).'</a></td>
				</tr>' : '').'
				<tr class="bgColor4">
					<td><b>'.$LANG->getLL('external_filename').'</b></td>
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
		GLOBAL $LANG;
		$confArr = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['llxmltranslate']);
		
		$extList = array('' => '');
		foreach($this->extPathList as $path) {
			$dir = PATH_site . $path;
			if (is_dir($dir)) {
				$dirs = t3lib_div::get_dirs($dir);
				if (is_array($dirs)) {
					foreach ($dirs as $dirname) {
						if ($dirname{0} != '.') {
							$path = $dir . $dirname;
							$version = $this->getExtVersion($dirname, $path);
							if ($version) {					
								$extTitle = $this->getExtTitle($dirname, $path);
								$extTitle = str_replace(' ','&nbsp;',str_pad($extTitle, 45, ' '));
								$dirname = str_replace(' ','&nbsp;',str_pad($dirname, 30, ' '));
								if ($confArr['OrderByTitleExtension']) {	
									$TitleInFirst = 1;
									$extList[$path] = $extTitle.$dirname.$version;
								}
								else {		
									$TitleInFirst = 0;
									$extList[$path] = $dirname.$extTitle.$version;
								}								
								if ($confArr['HideExtensionWithoutFiles']) {
									// Hide extension without files
									$files = $this->getllxmlFiles($path);
									if (empty($files))	{
										unset($extList[$path]);
									}
								}
							}
						}
					}
				}
			}
		}
		asort($extList);
		if ($TitleInFirst) {
			$Select_header = str_replace(' ','&nbsp;',str_pad($LANG->getLL('title_extension'),45," ")).str_replace(' ','&nbsp;',str_pad($LANG->getLL('key_extension'),30," ")).$LANG->getLL('version_extension');
		} else {
			$Select_header = str_replace(' ','&nbsp;',str_pad($LANG->getLL('key_extension'),30," ")).str_replace(' ','&nbsp;',str_pad($LANG->getLL('title_extension'),45," ")).$LANG->getLL('version_extension');
		}
		$extList = array_merge(array('__' => $Select_header, '___' => str_replace(' ','&nbsp;',str_pad('',82,'='))),$extList);
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

	/**
	 * Obtains extension title
	 *
	 * @param	string	$extKey	Extension key
	 * @param	string	$extPath	Extension path
	 * @return	string	Extension title
	 */
	function getExtTitle($extKey, $extPath) {
		$_EXTKEY = $extKey;
		@include($extPath . '/ext_emconf.php');
		return ucfirst($EM_CONF[$_EXTKEY]['title']);
	}
	
	
	function includeLocalLang()	{
		$llFile = t3lib_extMgm::extPath('llxmltranslate').'mod1/locallang.xml';
		$this->LL = t3lib_div::readLLXMLfile($llFile, $GLOBALS['LANG']->lang);
	}

	/**
	 * Show old or new CSH
	 *
	 * @param	string	$labelName	Label name in xml file
	 * @param	string	$labe		Text string of the language label
	 * @return	string	Help bubbles
	 */
	function showCSH($labelName='', $label='') {
		$refTCA = '_MOD_txllxmltranslateM1'; /* Reference for TCA (see extTable.php) */

		// New help in TYPO3 4.5 and more
		if (t3lib_div::int_from_ver(TYPO3_version) >= 4005000) {
	                $csh = t3lib_BEfunc::wrapInHelp($refTCA, $labelName, $label);
		} else {
     			// use old help before TYPO3 4.5
			$csh = t3lib_BEfunc::cshItem($refTCA, $labelName , $this->doc->backPath).$label;
		}

		return $csh;
	}
	
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/llxmltranslate/mod2/index.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/llxmltranslate/mod2/index.php']);
}

?>
