<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

if (TYPO3_MODE=='BE')	{
	t3lib_extMgm::addModule('txllxmltranslateM1','','',t3lib_extMgm::extPath($_EXTKEY).'mod1/');
	t3lib_extMgm::addLLrefForTCAdescr('_MOD_txllxmltranslateM1','EXT:llxmltranslate/locallang_csh.xml');
	t3lib_extMgm::addModule('txllxmltranslateM1','txllxmltranslateM1','bottom',t3lib_extMgm::extPath($_EXTKEY).'mod1/');
}

?>