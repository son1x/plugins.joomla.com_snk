<?php
defined('_JEXEC') or die();


//define("SCOUTNET_SERVER","http://localhost/www.scoutnet.de/public_html/jsonrpc/server.php");
define("SCOUTNET_SERVER","https://www.scoutnet.de/jsonrpc/server.php");

require_once("jsonRPCClient.php");
require_once('stufe.php');
require_once('kalender.php');
require_once('user.php');
require_once('event.php');

jimport( 'joomla.application.component.model' );

class SnkModelSnk extends JModel {
	var $user_cache = array();
	var $stufen_cache = array();
	var $kalender_cache = array();

	function getKalenders($params = null){
		if ($params == null) {
			$ssids =  explode(",",JRequest::getString('SSIDs','4'));
		} else {
			$ssids =  explode(",",$params->get('SSIDs','4'));
		}


		$addids = JRequest::getVar('addids');

		if (is_array($addids)) {
			$ssids = array_merge($ssids, $addids);
		}

		$out = array();
		foreach ($ssids as $ssid){
			$out[] = $this->get_kalender_by_id($ssid);
		}

		return $out;
	}

	function getOptionalKalenders($params = null){
		if ($params == null) {
			$optionalSSIDs = JRequest::getString('optionalSSIDs',"");
		} else {
			$optionalSSIDs = $params->get('optionalSSIDs',"");
		}

		if (!isset($optionalSSIDs) or strlen($optionalSSIDs) == 0) {
			return Array();
		}

		$addSsids =  explode(",",$optionalSSIDs);

		$SN = new com_snk_jsonRPCClient(SCOUTNET_SERVER);

		$results = $SN->get_data_by_global_id($addSsids,array('kalenders'=>$filter));

		foreach ($results as $record) {
			if ($record['type'] === 'kalender'){
				$kalender = new kalender($record['content']);
				$this->kalender_cache[$kalender['ID']] = $kalender;
			}
		}

		$out = array();
		foreach ($addSsids as $ssid){
			$kalender =  $this->get_kalender_by_id($ssid);
			if ($kalender != null) {
				$out[] = $kalender;
			}

		}

		return $out;
	}

	function getEvents($params = null) {
		ini_set('default_socket_timeout',1);
		$SN = new com_snk_jsonRPCClient(SCOUTNET_SERVER);
		$default_limit = 4;

		// only in the component
		if ($params == null) {
			$default_limit = 20;
			$addids = JRequest::getVar('addids');
			$ids =  explode(",",JRequest::getString('SSIDs','4'));
			$limit = JRequest::getString('limit',$default_limit);
			$kategories = JRequest::getString('Kategories');
			$stufen = JRequest::getString('Stufen');
		} else {
			$ids =  explode(",",$params->get('SSIDs',4));
			$limit = $params->get('limit',$default_limit);
			$kategories = $params->get('Kategories');
			$stufen = $params->get('Stufen');
		}

		if (is_array($addids)) {
			$ids = array_merge($ids, $addids);
		}


		$filter = array(
			'limit'=>$limit,
			'after'=>'now()',
		);

		if (isset($kategories) && trim($kategories)) {
			$filter['kategories'] = explode(",",$kategories);
		}

		if (isset($stufen) && trim($stufen)) {
			$filter['stufen'] = explode(",",$stufen);
		}


		$results = $SN->get_data_by_global_id($ids,array('events'=>$filter));

		$events = array();
		foreach ($results as $record) {
			if ($record['type'] === 'user'){
				$user = new user($record['content']);
				$this->user_cache[$user['userid']] = $user;
			} elseif ($record['type'] === 'stufe'){
				$stufe = new stufe($record['content']);
				$this->stufen_cache[$stufe['Keywords_ID']] = $stufe;
			} elseif ($record['type'] === 'kalender'){
				$kalender = new kalender($record['content']);
				$this->kalender_cache[$kalender['ID']] = $kalender;
			} elseif ($record['type'] === 'event') {
				$event = new event($record['content']);

				$author = $this->get_user_by_id($event['Last_Modified_By']);
				if ($author == null) {
					$author = $this->get_user_by_id($event['Created_By']);
				}   

				if ($author != null) {
					$event['Author'] = $author;
				}           

				$stufen = Array();


				if (isset($event['Stufen'])){
					foreach ($event['Stufen'] as $stufenId) {
						$stufe = $this->get_stufe_by_id($stufenId);
						if ($stufe != null) {
							$stufen[] = $stufe;
						}   
					}   
				}   

				$event['Stufen'] = $stufen;

				$event['Kalender'] = $this->get_kalender_by_id($event['Kalender']);

				$events[] = $event;
			} 
		}   
		return $events;
	}

	private function get_stufe_by_id($id) {
		return $this->stufen_cache[$id];
	}

	private function get_kalender_by_id($id) {
		return $this->kalender_cache[$id];
	}

	private function get_user_by_id($id) {
		return $this->user_cache[$id];
	}

}
