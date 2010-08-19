<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}
$tempColumns = array (
	'tx_t3notessearch_search_key' => array (		
		'exclude' => 0,		
		'label' => 'LLL:EXT:t3notes_search/locallang_db.xml:pages.tx_t3notessearch_search_key',		
		'config' => array (
			'type' => 'input',	
			'size' => '30',
		)
	),
);


t3lib_div::loadTCA('pages');
t3lib_extMgm::addTCAcolumns('pages',$tempColumns,1);
t3lib_extMgm::addToAllTCAtypes('pages','tx_t3notessearch_search_key;;;;1-1-1');
?>