<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

class EaseeCharger_account {

	private $name = '';
	private $login = '';
	private $password = '';
	private $isEnable = 0;
	private $_mapping = null;
	private $_transforms = null;
	private $_site = 'https://api.easee.cloud/api/';
	private $_modifiedChargers = array ();

	/*     * ********************** Méthodes Static *************************** */

	public static function create($name) {
		$key = 'account::' . $name;
		$config = config::byKey($key, 'EaseeCharger');
		if ($config != '') {
			log::add('EaseeCharger','warning',sprintf(__('Un compte nommé %s existe déjà',__FILE__),$name));
			throw new Exception (sprintf(__('Un compte nommé %s existe déjà!',__FILE__),$name));
		}
		$account = new self();
		$account->setName($name);
		$account->save();
		return $account;
	}

	public static function byName($name) {
		$key = 'account::' . $name;
		$value = config::byKey($key, 'EaseeCharger');
		if ($value == '') {
			return null;
		}
		$value = is_json($value,$value);
		if (array_key_exists('password',$value)) {
			$value['password'] = utils::decrypt($value['password']);
		} else {
			$value['password'] = '';
		}
		$account = new self();
		utils::a2o($account,$value);
		return $account;
	}

	public static function all($_onlyEnable = false) {
		$configs = config::searchKey('account::%', 'EaseeCharger');
		$accounts = array();
		foreach ($configs as $config) {
			if ($_onlyEnable) {
				if (!isset($config['value']['isEnable']) || $config['value']['isEnable'] == 0 || $config['value']['isEnable'] == '') {
					continue;
				}
			}
			$account = new self();
			utils::a2o($account,$config['value']);
			$accounts[] = $account;
		}
		return $accounts;
	}

	/*     * ********************** Méthodes d'instance *************************** */

	/*
	 * Enregistrement du compte
	 */
	public function save() {
		$oldAccount = self::byName($this->getName());
		if (is_object ($oldAccount) and ($oldAccount->getIsEnable() == 1)) {
			$wasEnable = 1;
		} else {
			$wasEnable = 0;
		}

		if ($this->getIsEnable()) {
			if (!$this->getLogin()) {
				throw new Exception (__("Le login doit être défini",__FILE__));
			}
			if (!$this->getPassword()) {
				throw new Exception (__("Le password doit être défini",__FILE__));
			}
			if ($wasEnable == 0 or $oldAccount->getLogin() != $this->getLogin() or $oldAccount->getPassword() != $this->getPassword()) { 
				if (!$this->checkLogin()) {
					throw new Exception(__("Login ou password incorrect",__FILE__));
				}
			}
		}
		$value = utils::o2a($this);
		$value['password'] = utils::encrypt($value['password']);
		$value = json_encode($value);
		$key = 'account::' . $this->name;
		config::save($key, $value, 'EaseeCharger');
		if ($this->getIsEnable() != 1 and ($wasEnable == 1)) {
			$chargers = EaseeCharger::byAccount($this->getName());
			$chargerIds = array();
			foreach ($chargers as $charger) {
				$charger->setIsEnable(0);
				$charger->save();
				$chargerIds[] = $charger->getId();
			}
			$this->setModifiedChargers($chargerIds);
		}
		return $this;
	}

	/*
	 * Suppression du compte
	 */
	public function remove() {
		$chargers = EaseeCharger::byAccount($this->name, false);
		if (count($chargers) > 0) {
			throw new Exception (sprintf(__("Le compte %s est utilisé pour le chargeur %s",__FILE__), $this->name, $chargers[0]->getName()));
		}
		$key = 'account::' . $this->name;
		return config::remove($key,'EaseeCharger');
	}

	/*
	 * Test login et password
	 */
	public function checkLogin($login = '', $password = '') {
		if (!$login) {
			$login = $this->getLogin();
		}
		if (!$password) {
			$password = $this->getPassword();
		}
		$data = array(
			'userName' => $login,
			'password' => $password
		);
		try {
			$result = $this->sendRequest('accounts/login', $data);
			if (is_array($result) and array_key_exists('accessToken',$result)) {
				$token = array (
					'accessToken' => $result['accessToken'],
					'expiresIn' => $result['expiresIn'],
					'expiresAt' => time() + $result['expiresIn'],
					'refreshToken' => $result['refreshToken']
				);
				$this->setToken($token);
			}
		} catch (EaseeCloudException $e) {
			return false;
		}
		return true;
	}

	/*
	 * Envoi d'une requête API au cloud Easee
	 */
	private function sendRequest($path, $data = '', $accessToken='' ) {
		log::add("EaseeCharger","info","┌─" .__("Easee: envoi d'une requête au cloud", __FILE__));
			

		$header = [
			"Accept: application/json",
			"Content-Type: application/*+json"
		];

		if ($accessToken == '') {
			$token = $this->getAccessToken();
		}
		if ($accessToken) {
			$header[] = 'Authorization: Bearer ' . $accessToken;
		} else {
			/*
			 * TODO
			 * Traitement si pas de login et password dans data
			 */
		}

		log::add("EaseeCharger","debug","|        URL: " . $this->_site . $path);
		log::add("EaseeCharger","debug","|     Header: " . print_r($header,true));
		$data2log = $data;
		if (config::byKey('unsecurelog','EaseeCharger') != 1) {
			if (is_array($data2log)) {
				if ( array_key_exists('password',$data2log) and ($data2log['password'] != '')) {
					$data2log['password'] = "**********";
				}
				if ( array_key_exists('token',$data2log) and ($data2log['token'] != '')) {
					$data2log['token'] = "**********";
				}
			}
		}
		log::add("EaseeCharger","debug", "|      data: " . print_r($data2log,true));

		if (is_array($data)) {
			$data = json_encode($data);
		}

		$curl = curl_init();
		if ($curl === false) {
			throw new Exception (__("Erreur lors l'initialisation de CURL",__FILE__));
		}
		curl_setopt_array($curl, [
			CURLOPT_URL => $this->_site . $path,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => $data == "" ? 'GET' : 'POST',
			CURLOPT_HTTPHEADER => $header,
			CURLOPT_POSTFIELDS => $data,
		]);

		$response = curl_exec($curl);
		if ($response === false) {
			curl_close($curl);
			throw new Exception (__("Erreur lors de la requête CURL",__FILE__));
		}

		if (curl_errno($curl)) {
			curl_close($curl);
			throw new Exception (sprintf(__("Erreur CURL %d: %s",__FILE__),curl_errno($surl),curl_error($curl)));
		}

		$httpCode = curl_getinfo($curl,CURLINFO_HTTP_CODE);
		curl_close($curl);
		if (substr($httpCode,0,1) != '2') {
			$txt = $response;
			$msg = json_decode($response,true);
			if (array_key_exists('title',$msg)) {
				$txt = $msg['title'];
			}
			$txt= sprintf(__("Code retour http: %s - %s",__FILE__) , $httpCode, $txt);
			log::add("EaseeCharger","warning", "└─" . $txt);
			throw new EaseeCloudException ($txt);
		}
		log::add("EaseeCharger","debug", "| " .  __("Code retour http: ",__FILE__) . $httpCode);
		log::add("EaseeCharger","info", "└─Requête envoyée");
		return json_decode($response, true);
	}

	/*
	 * Execution d'une commande destinée au Cloud Easee
	 */
	public function execute ($cmd) {
		try {
			$charger = $cmd->getEqLogic();
			log::add("EaseeCharger","debug","┌─" . sprintf(__("%s: execution de %s",__FILE__), $this->getName() , $cmd->getLogicalId()));
			log::add("EaseeCharger","debug","| " . __("Chargeur",__FILE__) . sprintf(": %s (%s)", $charger->getName(), $charger->getSerial()));
			if (! is_a($cmd, "EaseeChargerCmd")){
				throw new Exception (sprintf(__("└─La commande %s n'est pas une commande de type %s",__FILE__),$cmd->getId(), "EaseeCharger_chargerCmd"));
			}
			
			$method = 'execute_' . $cmd->getLogicalId();
			if (!method_exists($this, $method)) {
				throw new Exception (sprintf(__("└─La méthode %s est introuvable",__FILE__),$method));
			}
			$this->$method($cmd);
			log::add("EaseeCharger","debug","└─" . __("OK",__FILE__));
			return;
		} catch (Exception $e) {
			log::add("EaseeCharger","error",$e->getMessage());
			log::add("EaseeCharger","debug","└─" . __("ERROR",__FILE__));
			throw $e;
		}
	}
	
	protected function getMapping() {
		if (isset($this->_mapping)) {
			return $this->_mapping;
		}
		$mappingFile = __DIR__ . '/../../core/config/mapping.ini';
		if (! file_exists($mappingFile)) {
			throw new Exception (sprintf(__("Le fichier %s est introuvable",__FILE__), $mappingFile));
		}
		$mapping = parse_ini_file($mappingFile,true);
		if ($mapping == false) {
			throw new Exception (sprintf(__('Erreur lors de la lecture de %s',__FILE__),$mappingFile));
		}
		$this->_mapping = $mapping['API'];
		return $this->_mapping;
	}
	
	protected function getTransforms() {
		if (isset($this->_mapping)) {
			return $this->_mapping;
		}
		$transformsFile = __DIR__ . '/../../core/config/transforms.ini';
		if (! file_exists($transformsFile)) {
			throw new Exception (sprintf(__("Le fichier %s est introuvable",__FILE__), $transformsFile));
		}
		$transforms = parse_ini_file($transformsFile,true);
		if ($transforms == false) {
			throw new Exception (sprintf(__('Erreur lors de la lecture de %s',__FILE__),$transformsFile));
		}
		$this->_transforms = $transforms;
		return $this->_transforms;
	}

	/*
	 * token
	 */
	public function setToken($_token) {
		if (array_key_exists('accessToken',$_token)) {
			$_token['accessToken'] = utils::encrypt($_token['accessToken']);
		}
		if (array_key_exists('refreshToken',$_token)) {
			$_token['refreshToken'] = utils::encrypt($_token['refreshToken']);
		}
		$lifetime = array_key_exists('expiresIn',$_token) ? $_token['expiresIn'] : 192800;
		cache::set('EaseeCharger_account:'. $this->getName(), $_token, $lifetime);
		return $this;
	}

	public function getToken($retrying = false) {
		$cache = cache::byKey('EaseeCharger_account:' . $this->getName());
		if (!is_object($cache)) {
			if ($retrying) {
				log::add("EaseeCharger","error",sprintf(__("Erreur lors de la récupération d'un token pour %s",__FILE__)$this->getName()));
				return "";
			}
			if (!$this->checkLogin()) {
				return "";
			}
			return $this->getToken(true);
		}
		$token = $cache->getValue();
		if (!array_key_exists('expiresAt',$token) || $token['expiresAt'] < time()) {
			if (!$this->checkLogin()) {
				return "";
			}
			return $this->getToken(true);
		}
		$time2renew = $token['expiresAt'] - $token['expiresIn']/2;
		if ($time2renew < time()) {
			/*
			 * TODO renew token
			 */
		}
		return $token;
	}
	
	public function getAccessToken() {
		$token = $this->getToken();
		if (is_array($token) && array_key_exists('accessToken', $token)) {
			return $token['accessToken'];
		}
		return '';
	}
			
	/*     * ******************** Exécution des commandes ********************* */

	/*
	 * refresh
	 */
	private function execute_refresh($cmd) {
		$serial = $cmd->getEqLogic()->getSerial();
		$path = 'chargers/' . $serial . '/state';
		$response = $this->sendRequest($path);
		if (!isset($this->_mapping)) { 
			$this->getMapping();
		}
		if (!isset($this->_transforms)) {
			$this->getTransforms();
		}
		foreach ($response as $key => $value) {
			if (! array_key_exists($key, $this->_mapping)) {
				log::add('EaseeCharger','debug',"|   " . sprintf(__('Pas de traitemment pour %s',__FILE__),$key));
				continue;
			}
			foreach (explode(',', $this->_mapping[$key]) as $logicalId) {
				if (array_key_exists($logicalId, $this->_transforms)) {
					$value = $this->_transforms[$logicalId][$value];
				}
				log::add("EVcharger","debug",sprintf("|   " . "LogicalId: %s, value: %s", $logicalId, $value));
				$charger->checkAndUpdateCmd($logicalId,$value);
			}
		}
	}
			
	/*
	 * cable_lock
	 */
	public function execute_cable_lock($cmd) {
		$serial = $cmd->getEqLogic()->getSerial();
		$path = 'chargers/' . $serial . '/commands/lock_stats';
		$data = array ('state' => 'true');
		$this->sednrequest($path, $data);
	}

	/*
	 * cable_unlock
	 */
	public function execute_cable_unlock($cmd) {
		$serial = $dms->getEqLogic()->getSerial();
		$path = 'chargers/' . $serial . '/commands/lock_stats';
		$dat = array ('state' => 'false');
		$this->sednrequest($path, $data);
	}

	/*     * ********************** Getteur Setteur *************************** */

	/*
	 * isEnable
	 */
	public function setIsEnable($_isEnable) {
		$this->isEnable = $_isEnable;
		return $this;
	}

	public function getIsEnable() {
		return $this->isEnable;
	}

	/*
	 * login
	 */
	public function setLogin($_login) {
		$this->login = $_login;
		return $this;
	}

	public function getLogin() {
		return $this->login;
	}

	/*
	 * _modifiedCharger
	 */
	public function setModifiedChargers( $_chargers = array()) {
		$this->_modifiedChargers = $_chargers;
	}

	public function getModifiedChargers() {
		return $this->_modifiedChargers;
	}

	/*
	 * name
	 */
	public function setName($_name) {
		$this->name = $_name;
		return $this;
	}

	public function getName() {
		return $this->name;
	}

	/*
	 * password
	 */
	public function setPassword($_password) {
		$this->password = $_password;
		return $this;
	}

	public function getPassword() {
		return $this->password;
	}

}

class EaseeCloudException extends Exception {
	private $response = "";

	public function __construct($response, $code = 0, Throwable $previous = null) {
		if (!is_array($response)) {
			$message = $response;
		} else {
			$this->response = $response;
			$message = $response['title'];
		}
		parent::__construct($message, $code, $previous);
	}

	public function getResponse() {
		return $this->response;
	}
}

//	/*
//	 * Démarre le thread du démon pour chaque account actif
//	 */
//	public static function startAllDaemonThread(){
//		foreach (EaseeCharger::byType("EaseeCharger_account_%",true) as $account) {
//			$account->startDaemonThread();
//		}
//	}
//
//
//	/*
//	 * Envoi d'un message au daemon
//	 */
//	public function send2Daemon($message) {
//		if ($this->getIsEnable() and $this::$_haveDaemon){
//			if (is_array($message)) {
//				$message = json_encode($message);
//			}
//			if (EaseeCharger::daemon_info()['state'] != 'ok'){
//				log::add('EaseeCharger','debug',__("Le démon n'est pas démarré!",__FILE__));
//				return;
//			}
//			$params['apikey'] = jeedom::getApiKey('EaseeCharger');
//			$params['id'] = $this->getId();
//			$params['message'] = $message;
//			log::add("EaseeCharger","debug",__("Envoi de message au daemon: ",__FILE__) . print_r($params,true));
//			$payLoad = json_encode($params);
//			$socket = socket_create(AF_INET, SOCK_STREAM, 0);
//			socket_connect($socket,'127.0.0.1',(int)config::byKey('daemon::port','EaseeCharger'));
//			socket_write($socket, $payLoad, strlen($payLoad));
//			socket_close($socket);
//		}
//	}
//
//	/*
//	 * Lancement d'un thread du daemon pour l'account
//	 */
//	public function startDaemonThread() {
//		if ($this->getIsEnable() and $this::$_haveDaemon){
//			log::add("EaseeCharger","info","Account " . $this->getHumanName() . ": " . __("Lancement du thread",__FILE__));
//			$message = array('cmd' => 'start_account');
//			if (method_exists($this,'msgToStartDaemonThread')){
//				$message = $this->msgToStartDaemonThread();
//			}
//			$this->send2Daemon($message);
//		}
//	}
//
//	/*
//	 * Après démarrage du thread de l'account
//	 */
//	public function daemonThreadStarted() {
//		log::add("EaseeCharger","info","Account " . $this->getHumanName() . ": " . __("Le thread est démarré",__FILE__));
//		foreach (EaseeCharger_charger::byAccount($this->getId()) as $charger) {
//			$charger->startDaemonThread();
//		}
//	}
//
//	/*
//	 * Arrêt du thread dédié au compte
//	 */
//	public function stopDaemonThread() {
//		foreach (EaseeCharger_charger::byAccount($this->getId()) as $charger){
//			if ($charger->getIsEnable()) {
//				$message = array(
//					'cmd' => 'stop',
//					'charger' => $charger->getIdentifiant(),
//				);
//				$this->send2Daemon($message);
//			}
//		}
//		$message = array('cmd' => 'stop_account');
//		$this->send2Daemon($message);
//	}
//
//}
