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
		$value['password'] = utils::decrypt($value['password']);
		$value = is_json($value,$value);
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

	public function save() {
		$value = utils::o2a($this);
		$value['password'] = utils::encrypt($value['password']);
		$value = json_encode($value);
		$key = 'account::' . $this->name;
		config::save($key, $value, 'EaseeCharger');
		return $this;
	}

	public function remove() {
		$key = 'account::' . $this->name;
		return config::remove($key,'EaseeCharger');
	}

	/*     * ********************** Getteur Setteur *************************** */

	public function setIsEnable($_isEnable) {
		$this->isEnable = $_isEnable;
		return $this;
	}

	public function getIsEnable() {
		return $this->isEnable;
	}

	public function setLogin($_login) {
		$this->login = $_login;
		return $this;
	}

	public function getLogin() {
		return $this->login;
	}

	public function setName($_name) {
		$this->name = $_name;
		return $this;
	}

	public function getName() {
		return $this->name;
	}

	public function setPassword($_password) {
		$this->password = $_password;
		return $this;
	}

	public function getPassword() {
		return $this->password;
	}

}

//	/*
//	 * Démarre le thread du démon pour chaque account actif
//	 */
//	public static function startAllDaemonThread(){
//		foreach (EaseeCharger::byType("EaseeCharger_xaccount_%",true) as $account) {
//			$account->startDaemonThread();
//		}
//	}
//
//	private function sendRequest($path, $data = '', $token='' ) {
//		log::add("EVcharger","info",__("Easee: envoi d'une requête au cloud", __FILE__));
//		if (! $token) {
//			$token = $this->getToken();
//		}
//		$header = [
//			'Authorization: Bearer ' . $token,
//			"Accept: application/json",
//			"Content-Type: application/*+json"
//		];
//		if (is_array($data)) {
//			$data = json_encode($data);
//		}
//
//		$curl = curl_init();
//		curl_setopt_array($curl, [
//			CURLOPT_URL => $this->getUrl() . $path,
//			CURLOPT_RETURNTRANSFER => true,
//			CURLOPT_ENCODING => "",
//			CURLOPT_MAXREDIRS => 10,
//			CURLOPT_TIMEOUT => 30,
//			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//			CURLOPT_CUSTOMREQUEST => $data == "" ? 'GET' : 'POST',
//			CURLOPT_HTTPHEADER => $header,
//			CURLOPT_POSTFIELDS => $data,
//		]);
//		$reponse = curl_exec($curl);
//		$httpCode = curl_getinfo($curl,CURLINFO_HTTP_CODE);
//		$err = curl_error($curl);
//		curl_close($curl);
//		if ($err) {
//			log::add("EVcharger","error", "CURL Error : " . $err);
//			throw new Exception($err);
//		}
//		log::add("EVcharger","debug", "  " . __("Requête: URL: ",__FILE__) . $this->getUrl() . $path);
//		log::add("EVcharger","debug", "  " . "Header: " . print_r($header,true));
//		$data = json_decode($data,true);
//		if (is_array($data) and  array_key_exists('password',$data) and ($data['password'] != '')) {
//			$data['password'] = "**********";
//		}
//		$data = json_encode($data);
//		log::add("EVcharger","debug", "           " . __("data:",__FILE__) . $data);
//		if (substr($httpCode,0,1) != '2') {
//			$txt = $reponse;
//			$msg = json_decode($reponse,true);
//			if (array_key_exists('title',$msg)) {
//				$txt = $msg['title'];
//			}
//			$txt= sprintf(__("Code retour http: %s - %s",__FILE__) , $httpCode, $txt);
//			log::add("EVcharger","warning", $txt);
//			throw new Exception ($txt);
//		}
//		log::add("EVcharger","debug", "  " . __("Code retour http: ",__FILE__) . $httpCode);
//		log::add("EVcharger","info", "Requête envoyée");
//		return json_decode($reponse, true);
//	}
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
//		foreach (EaseeCharger_charger::byAccountId($this->getId()) as $charger) {
//			$charger->startDaemonThread();
//		}
//	}
//
//	/*
//	 * Arrêt du thread dédié au compte
//	 */
//	public function stopDaemonThread() {
//		foreach (EaseeCharger_charger::byAccountId($this->getId()) as $charger){
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
//	protected function getMapping() {
//		$mappingFile = __DIR__ . '/../../core/config/mapping.ini';
//		if (! file_exists($mappingFile)) {
//			return false;
//		}
//		$mapping = parse_ini_file($mappingFile,true);
//		if ($mapping == false) {
//			throw new Exception (sprintf(__('Erreur lors de la lecture de %s',__FILE__),$mappingFile));
//		}
//		return $mapping['API'];
//	}
//
//	protected function getTransforms() {
//		$transformsFile = __DIR__ . '/../../core/config/transforms.ini';
//		if (! file_exists($transformsFile)) {
//			return false;
//		}
//		$transforms = parse_ini_file($transformsFile,true);
//		if ($transforms == false) {
//			throw new Exception (sprintf(__('Erreur lors de la lecture de %s',__FILE__),$transformsFile));
//		}
//		return $transforms;
//	}
//
//	public function execute ($cmd_charger) {
//		try {
//			log::add("EaseeCharger","debug","┌─" . sprintf(__("%s: execution de %s",__FILE__), $this->getHumanName() , $cmd_charger->getLogicalId()));
//			if (! is_a($cmd_charger, "EaseeCharger_chargerCmd")){
//				throw new Exception (sprintf(__("| La commande %s n'est pas une commande de type %s",__FILE__),$cmd_charger->getId(), "EaseeCharger_chargerCmd"));
//			}
//			if ($cmd_charger->getConfiguration('destination') == 'charger') {
//				$method = 'execute_' . $cmd_charger->getLogicalId();
//				if ( ! method_exists($this, $method)){
//					throw new Exception ("| " . sprintf(__("%s: pas de méthode < %s::%s >",__FILE__),$this->getHumanName(), get_class($this), $method));
//				}
//				$this->$method($cmd_charger);
//				log::add("EaseeCharger","debug","└─" . __("OK",__FILE__));
//				return;
//			} else if ($cmd_charger->getConfiguration('destination') == 'cmd') {
//				log::add("EaseeCharger","debug","| " . __("Transfert vers une CMD",__FILE__));
//				$cmds = explode('&&', $cmd_charger->getConfiguration('destId'));
//				if (is_array($cmds)) {
//					foreach ($cmds as $cmd_id) {
//						$cmd = cmd::byId(str_replace('#', '', $cmd_id));
//						if (is_object($cmd)) {
//							$cmd->execCmd();
//						}
//					}
//					return;
//				} else {
//					$cmd = cmd::byId(str_replace('#', '', $cmd_id));
//					$cmd->execCmd();
//				}
//			} else {
//				throw new Exception (sprintf(__("La destination de la commande %s est inconnue!",__FILE__),$cmd_charger->getLogicalId()));
//			}
//		} catch (Exception $e) {
//			log::add("EaseeCharger","error",$e->getMessage());
//			log::add("EaseeCharger","debug","└─" . __("ERROR",__FILE__));
//			return;
//		}
//	}
//
//}
