<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

if (TYPO3_MODE=='BE')	{
	
	// add module after 'Web'
	if (!isset($TBE_MODULES['txllxml']))	{
		$temp_TBE_MODULES = array();
		foreach($TBE_MODULES as $key => $val) {
			if ($key === 'user') {
				$temp_TBE_MODULES[$key] = $val;
				$temp_TBE_MODULES['txllxml'] = $val;
			} else {
				$temp_TBE_MODULES[$key] = $val;
			}
		}
		$TBE_MODULES = $temp_TBE_MODULES;
		unset($temp_TBE_MODULES);
	}

	t3lib_extMgm::addModule('txllxml','','',t3lib_extMgm::extPath($_EXTKEY).'mod1/');
	t3lib_extMgm::addModule('txllxml','translate','bottom',t3lib_extMgm::extPath($_EXTKEY).'mod2/');
	t3lib_extMgm::addLLrefForTCAdescr('_MOD_txllxmltranslateM1','EXT:llxmltranslate/locallang_csh.xml');
}
?>
