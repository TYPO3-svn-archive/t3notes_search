<?php

class tx_t3notessearch_cabagphpproxy {
	
	/**
	 * add several special params to the postParams given by the cabag_phpproxy fe_index.php script
	 *
	 * @param	array	postParams
	 * @return	array	modified postParams array
	 */
	function processCurlAdditionalPostParams($postParams) {
		$this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['t3notes_search']);
		
		// Did the user already click once? - then save the option in the fe_user session
		if(!empty($postParams['selectedDatabases'])) {
			$selectedDatabases = $postParams['selectedDatabases'];
			unset($postParams['selectedDatabases']);
			
			$GLOBALS['TSFE']->fe_user->setKey('ses', 'tx_t3notes_search', $selectedDatabases);
			$GLOBALS['TSFE']->fe_user->storeSessionData();
		}
		
		$searchableDatabases = tx_t3notessearch_cabagphpproxy::getSearchableDatabases(true);
		
		// Look for the only selected database
		foreach($searchableDatabases as $database) {
			if($database['selected'] == true) {
				$postParams[$this->extConf['searchDatabaseVariable'].'1'] = $database['tx_t3notessearch_search_key'];
				break;
			}
		}
		
		return $postParams;
	}
	
	/**
	 * add several special curl options for the request
	 *
	 * @param	resource	curl resource
	 * @return	resource	modified curl resource
	 */
	function processCurlResource($curlResource) {
		
		$tx_t3notes_auth = t3lib_div::makeInstance('tx_t3notes_auth');
		
		// add cookie information
		$this->cookie = $tx_t3notes_auth->getRequestCOOKIE();
		curl_setopt($curlResource, CURLOPT_COOKIE, $this->cookie);
		
		return $curlResource;
	}
	
	/**
	 * modify the result data from the curl request
	 *
	 * @param	string	curl result data
	 * @param	array	additional header information about the curl request
	 * @return	resource	modified data
	 */
	function processData($data,$dataAdditionalInfos) {
		$this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['t3notes_search']);
		
		// replace link addresses and image paths in the return data
		if((stristr($dataAdditionalInfos['content_type'], 'application') === false)) {
			// get list of HTML attributes to replace from extension configuration
			$this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['t3notes_search']);
			$searchDetailReplaceList = explode(',',$this->extConf['searchDetailReplaceList']);
			foreach($searchDetailReplaceList as $search) {
				$data = preg_replace_callback('/('.$search.')([^"]*)(")/is', "tx_t3notessearch_cabagphpproxy::encode", $data);
			}
		} 
		
		// add checkboxes to choose the searchable databases
		$checkboxes = tx_t3notessearch_cabagphpproxy::getSearchableCheckboxes();
		
		if(strlen($data) == 0 && !empty($_POST[$this->extConf['searchDatabaseVariable'].'1']) && $_POST[$this->extConf['searchDatabaseVariable'].'1'] != 't3notes_searchNothing' && $dataAdditionalInfos['http_code'] != '200') {
			$data = '';
		} elseif(strlen($data) == 0) {
			// curl request failed - server is probably unreachable
			$data = '<span>noresultcount</span><br />'.$checkboxes;
		} else {
			$data = preg_replace('/^(\<span\>[^\<]*\<\/span\>\<br \/\>)(.*)$/is','$1'.$checkboxes.'$2' , $data);
		}  
		
		return $data;
	}
	
	/**
	 * helper function for processData() to encode/modify the link resources
	 *
	 * @param	array	preg matches
	 * @return	string	modified link address
	 */
	static function encode($matches) {
		$extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['t3notes_search']);
		if(substr($matches[2],0,8) === 'notes://' || substr($matches[2],0,7) === 'http://' || substr($matches[2],0,8) === 'https://') {
			// don't change anything with the notes:// links, they're already fine
			return  $matches[1].$matches[2].$matches[3];
		} else {
			// encode the matched link and add the base url configured in the ext emconf
			return  $matches[1].$extConf['searchDetailBaseUrl'].$matches[2].$matches[3];
		}
	}
	
	/**
	 * helper function to search for all searchable databases
	 *
	 * result array is merged with the selected database array from the user session
	 * every databaseRecord contains the information if the checkbox is selected or not
	 *
	 * @return	array	modified link address
	 */
	static function getSearchableDatabases($getOnlyTheSelectedDatabase=false) {
		$GLOBALS['TSFE']->fe_user->fetchSessionData();
		$dbFromUserSession = $GLOBALS["TSFE"]->fe_user->getKey('ses', 'tx_t3notes_search');
		//print_r($dbFromUserSession);
		
		// Select all SearchableViews from pages table
		$dbRes = $GLOBALS['TYPO3_DB']->exec_SELECTQuery(
			'DISTINCT 
				pages.*',
			'pages',
			"pages.tx_t3notessearch_search_key != '' AND pages.hidden=0 AND pages.deleted = 0",
			'',
			'pages.title'
			);
		
		$searchableDatabases = array();
		
		// Add default empty value to select box
		$defaultRecord = array(
				'tx_t3notessearch_search_key' => 't3notes_searchNothing', 
				'title' => 'Bitte wählen',
				'uid' => '',
				);
		
		// If there is an saved option in fe_user session don't select the default option
		if(!empty($dbFromUserSession)) {
			$defaultRecord['selected'] = false;
		} else {
			$defaultRecord['selected'] = true;
		}
		$searchableDatabases[] = $defaultRecord;
		
		
		// fetch basic data and create article objects
		while($databaseRecord = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbRes)){
			if(!empty($dbFromUserSession)) {
				if($databaseRecord['tx_t3notessearch_search_key'] == $dbFromUserSession) {
					// database is already selected in the user session
					$databaseRecord['selected'] = true;
					$searchableDatabases[] = $databaseRecord;
					if($getOnlyTheSelectedDatabase == true) {
						return $searchableDatabases;
					}
				} else {
					if($getOnlyTheSelectedDatabase == false) {
						// database is not in the usersession so user had not selected it
						$databaseRecord['selected'] = false;
						$searchableDatabases[] = $databaseRecord;
					}
				}
			} else {
				$databaseRecord['selected'] = false;
				$searchableDatabases[] = $databaseRecord;
				if($getOnlyTheSelectedDatabase == true) {
					return $searchableDatabases;
				}
			}
		}
		
		return $searchableDatabases;
		
	}
	
	/**
	 * helper function to create the checkbox string
	 * with every checkbox a searchable database can be activated or disabled for the next request
	 *
	 * @return	string	HTML string with checkbox code
	 */
	static function getSearchableCheckboxes() {
		$extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['t3notes_search']);
		$checkboxes = '';
		$searchableDatabases = tx_t3notessearch_cabagphpproxy::getSearchableDatabases();
		
		//print_r($searchableDatabases);
		$checkboxes = '<select name="t3notesSearchSearchableDatabases" id="t3notesSearchSearchableDatabases" onchange="'.$extConf['selectboxItemOnClick'].'" value="'.$pagesRecord['tx_t3notessearch_search_key'].'" />';
		
		foreach($searchableDatabases as $pagesRecord) {
			if($pagesRecord['selected'] === true) {
				$selected = 'selected="selected"';
			} else {
				$selected = '';
			}
			
			$checkbox = '
			<option '.$selected.' name="'.$pagesRecord['uid'].'"  value="'.$pagesRecord['tx_t3notessearch_search_key'].'" >'.$pagesRecord['title'].'</option>';
			
			$checkboxes .= $checkbox;
		}
		$checkboxes .= '</select>';
		
		if(!empty($extConf['checkboxWrapOuter'])) {
			return str_replace('|',$checkboxes,$extConf['checkboxWrapOuter']);
		} else {
			return $checkboxes;
		}
	}
}

?>
