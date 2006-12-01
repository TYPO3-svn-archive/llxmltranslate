<?php

########################################################################
# Extension Manager/Repository config file for ext: "llxmltranslate"
#
# Auto generated 24-03-2006 11:40
#
# Manual updates:
# Only the data in the array - anything else is removed by next write.
# "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'locallang-XML translation tool',
	'description' => 'Tool used to translate locallang*.xml files inside the TYPO3 backend.',
	'category' => 'module',
	'shy' => 0,
	'dependencies' => '',
	'conflicts' => '',
	'priority' => '',
	'loadOrder' => '',
	'module' => 'mod1,cli',
	'state' => 'beta',
	'internal' => 0,
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearCacheOnLoad' => 0,
	'lockType' => '',
	'author' => 'Kasper Skaarhoj',
	'author_email' => 'kasper@typo3.com',
	'author_company' => '',
	'CGLcompliance' => '',
	'CGLcompliance_note' => '',
	'version' => '2.2.0',
	'_md5_values_when_last_written' => 'a:39:{s:26:"_l10n_folder_index.php.txt";s:4:"46e4";s:15:"ext_emconf.php~";s:4:"60c5";s:12:"ext_icon.gif";s:4:"e9a1";s:14:"ext_tables.php";s:4:"e9d5";s:17:"locallang_csh.xml";s:4:"085b";s:22:"cli/cache_update.phpsh";s:4:"ea6d";s:12:"cli/conf.php";s:4:"8f83";s:22:"cli/generate_ter.phpsh";s:4:"4305";s:28:"cli/zip_language_packs.phpsh";s:4:"3d06";s:19:"cshimages/shot1.png";s:4:"6041";s:20:"cshimages/shot10.png";s:4:"6d68";s:20:"cshimages/shot11.png";s:4:"d8f4";s:20:"cshimages/shot12.png";s:4:"d30d";s:20:"cshimages/shot13.png";s:4:"2c02";s:20:"cshimages/shot14.png";s:4:"94d1";s:20:"cshimages/shot15.png";s:4:"8947";s:20:"cshimages/shot16.png";s:4:"c0b1";s:19:"cshimages/shot2.png";s:4:"f901";s:19:"cshimages/shot3.png";s:4:"ab7d";s:19:"cshimages/shot4.png";s:4:"5aa0";s:19:"cshimages/shot5.png";s:4:"bf7e";s:19:"cshimages/shot6.png";s:4:"970b";s:19:"cshimages/shot7.png";s:4:"2b5f";s:19:"cshimages/shot8.png";s:4:"632b";s:19:"cshimages/shot9.png";s:4:"c2a3";s:14:"doc/manual.sxw";s:4:"18e8";s:17:"mod1/class.sc.php";s:4:"efb8";s:14:"mod1/clear.gif";s:4:"cc11";s:13:"mod1/conf.php";s:4:"46f8";s:14:"mod1/index.php";s:4:"5806";s:22:"mod1/locallang_mod.php";s:4:"3b71";s:19:"mod1/moduleicon.gif";s:4:"e9a1";s:26:"test/de.locallang_test.xml";s:4:"b35e";s:26:"test/dk.locallang_test.xml";s:4:"939c";s:26:"test/es.locallang_test.xml";s:4:"443b";s:26:"test/fr.locallang_test.xml";s:4:"6063";s:26:"test/it.locallang_test.xml";s:4:"0c85";s:23:"test/locallang_test.xml";s:4:"3452";s:26:"test/no.locallang_test.xml";s:4:"30e8";}',
	'constraints' => array(
		'depends' => array(
			'php' => '3.0.0-',
			'typo3' => '3.7.0-',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'suggests' => array(
	),
);

?>