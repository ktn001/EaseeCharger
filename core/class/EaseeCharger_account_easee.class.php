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


class EaseeCharger_account_easee extends EaseeCharger_account {

	protected static $_haveDaemon = true;

	public function decrypt() {
		$this->setConfiguration('password', utils::decrypt($this->getConfiguration('password')));
	}

	public function encrypt() {
		$this->setConfiguration('password', utils::encrypt($this->getConfiguration('password')));
	}

	private function sendRequest($path, $data = '', $token='' ) {
		log::add("EaseeCharger","info",__("Easee: envoi d'une requête au cloud", __FILE__));
		if (! $token) {
			$token = $this->getToken();
		}
		$header = [
			'Authorization: Bearer ' . $token,
			"Accept: application/json",
			"Content-Type: application/*+json"
		];
		if (is_array($data)) {
			$data = json_encode($data);
		}

		$curl = curl_init();
		curl_setopt_array($curl, [
			CURLOPT_URL => $this->getUrl() . $path,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => $data == "" ? 'GET' : 'POST',
			CURLOPT_HTTPHEADER => $header,
			CURLOPT_POSTFIELDS => $data,
		]);
		$reponse = curl_exec($curl);
		$httpCode = curl_getinfo($curl,CURLINFO_HTTP_CODE);
		$err = curl_error($curl);
		curl_close($curl);
		if ($err) {
			log::add("EaseeCharger","error", "CURL Error : " . $err);
			throw new Exception($err);
		}
		log::add("EaseeCharger","debug", "  " . __("Requête: URL: ",__FILE__) . $this->getUrl() . $path);
		log::add("EaseeCharger","debug", "  " . "Header: " . print_r($header,true));
		$data = json_decode($data,true);
		if (array_key_exists('password',$data) and ($data['password'] != '')) {
			$data['password'] = "**********";
		}
		$data = json_encode($data);
		log::add("EaseeCharger","debug", "           " . __("data:",__FILE__) . $data);
		if (substr($httpCode,0,1) != '2') {
			$txt = $reponse;
			$msg = json_decode($reponse,true);
			if (array_key_exists('title',$msg)) {
				$txt = $msg['title'];
			}
			$txt= sprintf(__("Code retour http: %s - %s",__FILE__) , $httpCode, $txt);
			log::add("EaseeCharger","warning", $txt);
			throw new Exception ($txt);
		}
		log::add("EaseeCharger","debug", "  " . __("Code retour http: ",__FILE__) . $httpCode);
		log::add("EaseeCharger","info", "Requête envoyée");
		return json_decode($reponse, true);
	}

	protected function msgToStartDaemonThread() {
		$message = array(
			'cmd' => 'start_account',
			'url' => $this->getUrl(),
			'token' => $this->getToken()
		);
		return $message;
	}

	public function preSave() {
		if ($this->getisEnable()) {
			if ($this->getConfiguration('login') == '') {
				throw new Exception (__("Le login n'est pas défini!",__FILE__));
			}
			if ($this->getConfiguration('password') == '') {
				throw new Exception (__("Le password n'est pas défini!",__FILE__));
			}
			if ($this->getConfiguration('url') == '') {
				throw new Exception (__("L'URL n'est pas définie!",__FILE__));
			}
			$this->getToken(true);
		}
	}

	private function getToken ($getNew = false) {
		$changed = false;
		if (! $getNew){
			$token = $this->getCache('token');
			log::add("EaseeCharger","debug",__("Token en cache: ",__FILE__) . $token);
			if ($token == '') {
				$getNew = true;
			} else {
				$token = json_decode($token,true);
				if ($token['expiresAt'] < time() ) {
					log::add("EaseeCharger","debug",__("Le token a expiré",__FILE__));
					$getNew = true;
				} else if (($token['expiresAt'] - 12*3600) < time() ) {
					log::add("EaseeCharger","debug",__("Renouvellement du token",__FILE__));
					$data = array(
						'accessToken' => $token['accessToken'],
						'refreshToken' => $token['refreshToken']
					);
					try {
						$token = $this->sendRequest('/api/accounts/refresh_token', $data, $token['accessToken']);
						$token['expiresAt'] = time() + $token['expiresIn'];
						$this->setCache('token',json_encode($token));
						$changed = true;
					} catch (Exception $e) {
						$getNew = true;
					}
				}
			}
		}
		if ($getNew) {
			log::add("EaseeCharger","debug",__("Obtention d'un nouveau token",__FILE__));
			$data = array(
				'userName' => $this->getConfiguration('login'),
				'password' => $this->getConfiguration('password')
			);
			try {
				$token = $this->sendRequest('/api/accounts/login', $data, "Token pas necessaire");
				$token['expiresAt'] = time() + $token['expiresIn'];
				$this->setCache('token',json_encode($token));
				$changed = true;
			} catch (Exception $e) {
				$txt = $e->getMessage();
				$msg = json_decode($e->getMessage(),true);
				if (array_key_exists('errorCode',$msg)) {
					if ($msg['errorCode'] == '100') {
						$txt = __("Login ou password invalide!",__FILE__);
					} else {
						$txt = $msg['title'];
					}
				}
				throw new Exception($txt);
			}
		}
		if ($changed) {
			log::add("EaseeCharger","debug",__("Relance du thread",__FILE__));
			$this->stopDaemonThread();
			$this->startDaemonThread();
		}
		return $token['accessToken'];
	}

	public function setUrl($_url) {
		return $this->setConfiguration('url',$_url);
	}

	public function getUrl() {
		return $this->getConfiguration('url');
	}

	public function execute_refresh($cmd){
		$charger = EaseeCharger_charger::byId($cmd->getEqLogic()->getId());
		$serial = $cmd->getEqLogic()->getConfiguration("serial");
		$path = '/api/chargers/' . $serial . '/state';
		$response = $this->sendRequest($path);
		$mapping = $this->getMapping();
		$transforms = $this->getTransforms();
		foreach (array_keys($response) as $key){
			log::add('EaseeCharger','debug',sprintf(__("Traitement de : %s",__FILE__),$key));
			if ( ! array_key_exists($key,$mapping)){
				continue;
			}
			foreach (explode(',',$mapping[$key]) as $logicalId){
				$value = $response[$key];
				if (array_key_exists($logicalId, $transforms)) {
					$value = $transforms[$logicalId][$value];
				}
				log::add("EaseeCharger","info",sprintf("  LogicalId: %s, value: %s", $logicalId, $value));
				$charger->checkAndUpdateCmd($logicalId,$value);
			}
		}
	}

	public function execute_cable_lock($cmd) {
		$serial = $cmd->getEqLogic()->getConfiguration("serial");
		$path = '/api/chargers/' . $serial . '/commands/lock_state';
		$data = array ( 'state' => 'true');
		$this->sendRequest($path, $data);
	}

	public function execute_cable_unlock($cmd) {
		$serial = $cmd->getEqLogic()->getConfiguration("serial");
		$path = '/api/chargers/' . $serial . '/commands/lock_state';
		$data = array ( 'state' => 'false');
		$this->sendRequest($path, $data);
	}
}
class EaseeCharger_account_easeeCmd extends EaseeCharger_accountCmd  {

}
