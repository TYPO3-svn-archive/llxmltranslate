<?php

########################################################################
# Extension Manager/Repository config file for ext "llxmltranslate".
#
# Auto generated 12-02-2011 16:06
#
# Manual updates:
# Only the data in the array - everything else is removed by next
# writing. "version" and "dependencies" must not be touched!
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
	'version' => '2.4.1',
	'_md5_values_when_last_written' => 'a:87:{s:9:"ChangeLog";s:4:"2ede";s:26:"_l10n_folder_index.php.txt";s:4:"80b1";s:21:"ext_conf_template.txt";s:4:"b5c6";s:12:"ext_icon.gif";s:4:"e9a1";s:14:"ext_tables.php";s:4:"8c48";s:17:"locallang_csh.xml";s:4:"85a0";s:22:"cli/cache_update.phpsh";s:4:"ce9a";s:12:"cli/conf.php";s:4:"aa65";s:22:"cli/generate_ter.phpsh";s:4:"e67f";s:28:"cli/zip_language_packs.phpsh";s:4:"481b";s:19:"cshimages/shot1.png";s:4:"6041";s:20:"cshimages/shot10.png";s:4:"6d68";s:20:"cshimages/shot11.png";s:4:"d8f4";s:20:"cshimages/shot12.png";s:4:"d30d";s:20:"cshimages/shot13.png";s:4:"2c02";s:20:"cshimages/shot14.png";s:4:"94d1";s:20:"cshimages/shot15.png";s:4:"8947";s:20:"cshimages/shot16.png";s:4:"c0b1";s:19:"cshimages/shot2.png";s:4:"f901";s:19:"cshimages/shot3.png";s:4:"ab7d";s:19:"cshimages/shot4.png";s:4:"5aa0";s:19:"cshimages/shot5.png";s:4:"bf7e";s:19:"cshimages/shot6.png";s:4:"970b";s:19:"cshimages/shot7.png";s:4:"2b5f";s:19:"cshimages/shot8.png";s:4:"632b";s:19:"cshimages/shot9.png";s:4:"c2a3";s:14:"doc/manual.sxw";s:4:"18e8";s:13:"mod1/conf.php";s:4:"11d1";s:22:"mod1/locallang_mod.xml";s:4:"c3c8";s:17:"mod2/class.sc.php";s:4:"2702";s:14:"mod2/clear.gif";s:4:"cc11";s:13:"mod2/conf.php";s:4:"5655";s:14:"mod2/index.php";s:4:"53f0";s:18:"mod2/locallang.xml";s:4:"5dcd";s:22:"mod2/locallang_mod.xml";s:4:"0f7c";s:19:"mod2/moduleicon.gif";s:4:"eaef";s:26:"test/de.locallang_test.xml";s:4:"cd7a";s:26:"test/dk.locallang_test.xml";s:4:"939c";s:26:"test/es.locallang_test.xml";s:4:"443b";s:26:"test/fr.locallang_test.xml";s:4:"6063";s:26:"test/it.locallang_test.xml";s:4:"0c85";s:23:"test/locallang_test.xml";s:4:"9b09";s:26:"test/no.locallang_test.xml";s:4:"30e8";s:15:"trunk/ChangeLog";s:4:"2ede";s:32:"trunk/_l10n_folder_index.php.txt";s:4:"80b1";s:27:"trunk/ext_conf_template.txt";s:4:"b5c6";s:20:"trunk/ext_emconf.php";s:4:"4b08";s:18:"trunk/ext_icon.gif";s:4:"e9a1";s:20:"trunk/ext_tables.php";s:4:"8c48";s:23:"trunk/locallang_csh.xml";s:4:"85a0";s:28:"trunk/cli/cache_update.phpsh";s:4:"ce9a";s:18:"trunk/cli/conf.php";s:4:"aa65";s:28:"trunk/cli/generate_ter.phpsh";s:4:"e67f";s:34:"trunk/cli/zip_language_packs.phpsh";s:4:"481b";s:25:"trunk/cshimages/shot1.png";s:4:"6041";s:26:"trunk/cshimages/shot10.png";s:4:"6d68";s:26:"trunk/cshimages/shot11.png";s:4:"d8f4";s:26:"trunk/cshimages/shot12.png";s:4:"d30d";s:26:"trunk/cshimages/shot13.png";s:4:"2c02";s:26:"trunk/cshimages/shot14.png";s:4:"94d1";s:26:"trunk/cshimages/shot15.png";s:4:"8947";s:26:"trunk/cshimages/shot16.png";s:4:"c0b1";s:25:"trunk/cshimages/shot2.png";s:4:"f901";s:25:"trunk/cshimages/shot3.png";s:4:"ab7d";s:25:"trunk/cshimages/shot4.png";s:4:"5aa0";s:25:"trunk/cshimages/shot5.png";s:4:"bf7e";s:25:"trunk/cshimages/shot6.png";s:4:"970b";s:25:"trunk/cshimages/shot7.png";s:4:"2b5f";s:25:"trunk/cshimages/shot8.png";s:4:"632b";s:25:"trunk/cshimages/shot9.png";s:4:"c2a3";s:20:"trunk/doc/manual.sxw";s:4:"18e8";s:19:"trunk/mod1/conf.php";s:4:"11d1";s:28:"trunk/mod1/locallang_mod.xml";s:4:"c3c8";s:23:"trunk/mod2/class.sc.php";s:4:"64c6";s:20:"trunk/mod2/clear.gif";s:4:"cc11";s:19:"trunk/mod2/conf.php";s:4:"5655";s:20:"trunk/mod2/index.php";s:4:"53f0";s:24:"trunk/mod2/locallang.xml";s:4:"5dcd";s:28:"trunk/mod2/locallang_mod.xml";s:4:"0f7c";s:25:"trunk/mod2/moduleicon.gif";s:4:"eaef";s:32:"trunk/test/de.locallang_test.xml";s:4:"cd7a";s:32:"trunk/test/dk.locallang_test.xml";s:4:"939c";s:32:"trunk/test/es.locallang_test.xml";s:4:"443b";s:32:"trunk/test/fr.locallang_test.xml";s:4:"6063";s:32:"trunk/test/it.locallang_test.xml";s:4:"0c85";s:29:"trunk/test/locallang_test.xml";s:4:"9b09";s:32:"trunk/test/no.locallang_test.xml";s:4:"30e8";}',
	'constraints' => array(
		'depends' => array(
			'php' => '3.0.0-0.0.0',
			'typo3' => '4.0.0-0.0.0',
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