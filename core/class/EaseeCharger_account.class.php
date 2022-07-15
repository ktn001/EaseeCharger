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

    private static $_mapping = null;
    private static $_transforms = null;

    private $name = '';
    private $login = '';
    private $password = '';
    private $isEnable = 0;
    private $_site = 'https://api.easee.cloud/api/';
    private $_modifiedChargers = [];

    /*     * ********************************************************************** */
    /*     * *************************** Méthodes Static ************************** */
    /*     * ********************************************************************** */

    /*
     * Création d'un account
     */
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

    /*
     * byName
     */
    public static function byName($name) {
	$key = 'account::' . $name;
	$value = config::byKey($key, 'EaseeCharger');
	if ($value == '') {
	    return null;
	}
	$value = is_json($value,$value);
	if (isset($value['password'])) {
	    $value['password'] = utils::decrypt($value['password']);
	} else {
	    $value['password'] = '';
	}
	$account = new self();
	utils::a2o($account,$value);
	return $account;
    }

    /*
     * Tous les accounts
     */
    public static function all($_onlyEnable = false) {
	$configs = config::searchKey('account::%', 'EaseeCharger');
	$accounts = [];
	foreach ($configs as $config) {
	    if ($_onlyEnable) {
		if (!isset($config['value']['isEnable']) || $config['value']['isEnable'] == 0 || $config['value']['isEnable'] == '') {
		    continue;
		}
	    }
	    if (isset($config['value']['password'])) {
		$config['value']['password'] = utils::decrypt($config['value']['password']);
	    } else {
		$config['value']['password'] = '';
	    }
	    $account = new self();
	    utils::a2o($account,$config['value']);
	    $accounts[] = $account;
	}
	return $accounts;
    }

    /*     * ******************************** cron ******************************** */

    /*
     * cron Hourly
     */
    public static function cronHourly() {
	$accounts = self::all(true);
	foreach ($accounts as $account) {
	    log::add("EaseeCharger","debug",sprintf(__("Vérification du token pour l'account %s",__FILE__),$account->getName()));
	    $token = $account->getToken();
	    if (is_array($token)){
		log::add("EaseeCharger","debug","  " . sprintf(__("Token valide jusqu'à %s",__FILE__),date('d/m/Y H:i:s', $token['expiresAt'])));
	    } else {
		log::add("EaseeCharger","error", sprintf(__("Pas de token valid pour l'account %s",__FILE__),$account->getName()));
	    }
	}
    }

    private static function mapCmd($cmd) {
	if (!isset(self::$_mapping)) {
	    $mappingFile = __DIR__ . '/../../core/config/mapping.ini';
	    if (! file_exists($mappingFile)) {
		throw new Exception (sprintf(__("Le fichier %s est introuvable",__FILE__), $mappingFile));
	    }
	    $mapping = parse_ini_file($mappingFile,true);
	    if ($mapping == false) {
		throw new Exception (sprintf(__('Erreur lors de la lecture de %s',__FILE__),$mappingFile));
	    }
	    self::$_mapping = $mapping['API'];
	}
	if (isset (self::$_mapping[$cmd])) {
	    $logicalIds = explode(',', self::$_mapping[$cmd]);
	} else {
	    $logicalIds = [];
	}
	return $logicalIds;
    }

    private static function transforms($logicalId, $value) {
	if (!isset(self::$_transforms)) {
	    $transformsFile = __DIR__ . '/../../core/config/transforms.ini';
	    if (! file_exists($transformsFile)) {
		throw new Exception (sprintf(__("Le fichier %s est introuvable",__FILE__), $transformsFile));
	    }
	    $transforms = parse_ini_file($transformsFile,true);
	    if ($transforms == false) {
		throw new Exception (sprintf(__('Erreur lors de la lecture de %s',__FILE__),$transformsFile));
	    }
	    self::$_transforms = $transforms;
	}
	if (!isset(self::$_transforms[$logicalId])) {
	    return $value;
	}
	if (isset(self::$_transforms[$logicalId][$value])) {
	    return self::$_transforms[$logicalId][$value];
	}
	if (isset(self::$_transforms[$logicalId]['default'])) {
	    return self::$_transforms[$logicalId]['default'];
	}
	return $value;
    }

    /*     * ********************************************************************** */
    /*     * ********************** Méthodes d'instance *************************** */
    /*     * ********************************************************************** */

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
	    if ($wasEnable == 0 || !is_object($oldAccount) || $oldAccount->getLogin() != $this->getLogin() || $oldAccount->getPassword() != $this->getPassword()) {
		if (!$this->checkLogin()) {
		    throw new Exception(__("Login ou password incorrect",__FILE__));
		}
	    }
	}
	$value = utils::o2a($this);
	$value['password'] = utils::encrypt($value['password']);
	$value = json_encode($value);
	$key = 'account::' . $this->name;

	# Désactivation de l'account
	config::save($key, $value, 'EaseeCharger');
	if ($this->getIsEnable() != 1 && ($wasEnable == 1)) {
	    $chargers = EaseeCharger::byAccount($this->getName());
	    $chargerIds = [];
	    foreach ($chargers as $charger) {
		$charger->setIsEnable(0);
		$charger->save();
		$chargerIds[] = $charger->getId();
	    }
	    $this->setModifiedChargers($chargerIds);
	    $this->stop_account_on_daemon();
	}

	# Activation de l'account
	if ($this->getIsEnable() == 1 && $wasEnable !=1) {
	    if (EaseeCharger::daemon_info()['state'] == 'ok') {
		$this->start_account_on_daemon();
	    }
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

    /*     * ******************************* Daemon ******************************* */

    /*
     * Envoi d'un message au daemon
     */
    public function send2daemon ($message) {
	$payload = is_json($message,$message);
	if (!is_array($payload)) {
	    $payload = [
		'message' => $payload,
	    ];
	}
	if (!isset($payload['object'])) {
	    $payload['object'] = 'account';
	}
	$payload['account'] = $this->getName();
	EaseeCharger::send2daemon($payload);
    }

    /*
     * Lancement du thread du daemon
     */
    public function start_account_on_daemon() {
	$token = $this->getToken();
	$message = [
	    'cmd' => 'startAccount',
	    'account' => $this->getName(),
	    'accessToken' => $token['accessToken'],
	    'refreshToken' => $token['refreshToken'],
	    'expiresAt' => $token['expiresAt'],
	    'expiresIn' => $token['expiresIn']
	];
	EaseeCharger::send2daemon ($message);
    }

    public function account_on_daemon_started() {
	foreach (EaseeCharger::byAccount($this->getName(),true) as $charger){
	    $charger->start_daemon_thread();
	}
    }
    /*
     * Arrêt du thread du daemon
     */
    public function stop_account_on_daemon() {
	$message = [
	    'object' => 'daemon',
	    'cmd' => 'stopAccount',
	    'accessToken' => $this->getAccessToken(),
	];
	$this->send2daemon ($message);
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
	$data = [
	    'userName' => $login,
	    'password' => $password
	];
	try {
	    $result = $this->sendRequest('accounts/login', $data);
	    if (isset($result['accessToken'])) {
		$token = [
		    'accessToken' => $result['accessToken'],
		    'expiresIn' => $result['expiresIn'],
		    'expiresAt' => time() + $result['expiresIn'],
		    'refreshToken' => $result['refreshToken']
		];
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

	$method = $data == '' ? 'GET' : 'POST';
	if ($data == 'POST') {
		$method = 'POST';
		$data = '';
	}
	$data2log = $data;
	if (config::byKey('unsecurelog','EaseeCharger') != 1) {
	    if (is_array($data2log)) {
		if ( isset($data2log['password'])) {
		    $data2log['password'] = "**********";
		}
		if ( isset($data2log['token'])) {
		    $data2log['token'] = "**********";
		}
		if ( isset($data2log['accessToken'])) {
		    $data2log['accessToken'] = "**********";
		}
		if ( isset($data2log['refreshToken'])) {
		    $data2log['refreshToken'] = "**********";
		}
		if ( isset($data2log['refreshToken'])) {
		    $data2log['refreshToken'] = "**********";
		}
	    }
	}
	log::add("EaseeCharger","debug", "│       data: " . print_r($data2log,true));

	$header = [
	    "Accept: application/json",
	    "Content-Type: application/*+json"
	];

	if (!$accessToken) {
	    if (!isset($data['userName']) && !isset($data['password'])) {
		$accessToken = $this->getAccessToken();
	    }
	}
	if ($accessToken) {
	    $header[] = 'Authorization: Bearer ' . $accessToken;
	} else {
	    /*
	     * TODO
	     * Traitement si pas de login et password dans data
	     */
	}

	log::add("EaseeCharger","debug","│        URL: " . $this->_site . $path);
	log::add("EaseeCharger","debug","│     Method: " . $method);
	log::add("EaseeCharger","debug","│     Header: " . print_r($header,true));

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
	    CURLOPT_CUSTOMREQUEST => $method,
	    CURLOPT_HTTPHEADER => $header,
	    CURLOPT_POSTFIELDS => $data,
	]);
	    // CURLOPT_CUSTOMREQUEST => $data == "" ? 'GET' : 'POST',

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
	    $msg = is_json($response,$response);
	    if (isset($msg['title'])) {
		$txt = $msg['title'];
	    }
	    $txt= sprintf(__("Code retour http: %s - %s",__FILE__) , $httpCode, $txt);
	    log::add("EaseeCharger","warning", "└─" . $txt);
	    throw new EaseeCloudException ($txt);
	}
	log::add("EaseeCharger","debug", "│ " .  __("Code retour http: ",__FILE__) . $httpCode);
	log::add("EaseeCharger","info", "└─Requête envoyée");
	return json_decode($response, true);
    }

    /*
     * Execution d'une commande destinée au Cloud Easee
     */
    public function execute ($cmd) {
	$charger = $cmd->getEqLogic();
	log::add("EaseeCharger","debug","┌─" . sprintf(__("%s: execution de %s",__FILE__), $this->getName() , $cmd->getLogicalId()));
	log::add("EaseeCharger","debug","│ " . __("Chargeur",__FILE__) . sprintf(": %s (%s)", $charger->getName(), $charger->getSerial()));
	if (! is_a($cmd, "EaseeChargerCmd")){
	    throw new Exception (sprintf(__("└─La commande %s n'est pas une commande de type %s",__FILE__),$cmd->getId(), "EaseeChargerCmd"));
	}

	$method = 'execute_' . $cmd->getLogicalId();
	if (!method_exists($this, $method)) {
	    $msg = sprintf(__("La méthode %s est introuvable",__FILE__),$method);
	    log::add("EaseeCharger","debug","└─" . $msg);
	    throw new Exception ($msg);
	}
	$this->$method($cmd);
	log::add("EaseeCharger","debug","└─" . __("OK",__FILE__));
	return;
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
	$lifetime = isset($_token['expiresIn']) ? $_token['expiresIn'] : 192800;
	cache::set('EaseeCharger_account:'. $this->getName(), $_token, $lifetime);
	return $this;
    }

    public function getToken($retrying = false) {
	if ($retrying) {
	    log::add("EaseeCharger","debug","getToken (retrying) ....");
	} else {
	    log::add("EaseeCharger","debug","getToken....");
	}
	$cache = cache::byKey('EaseeCharger_account:' . $this->getName());
	if (!is_object($cache)) {
	    log::add("EaseeCharger","debug","   Pas de token en cache.");
	    if ($retrying) {
		log::add("EaseeCharger","error",sprintf(__("Erreur lors de la récupération d'un token pour %s",__FILE__),$this->getName()));
		return "";
	    }
	    log::add("EaseeCharger","debug","   Test de login.");
	    if (!$this->checkLogin()) {
		return "";
	    }
	    log::add("EaseeCharger","debug","   ok");
	    return $this->getToken(true);
	}
	log::add("EaseeCharger","debug","   token trouvé en cache.");
	$token = $cache->getValue();
	$token['accessToken'] = utils::decrypt($token['accessToken']);
	$token['refreshToken'] = utils::decrypt($token['refreshToken']);
	if (!isset($token['expiresAt']) || $token['expiresAt'] < time()) {
	    log::add("EaseeCharger","debug","   Token et refresh token expirés => testLogin");
	    if (!$this->checkLogin()) {
		return "";
	    }
	    log::add("EaseeCharger","debug","   ok");
	    return $this->getToken(true);
	}
	$time2renew = $token['expiresAt'] - $token['expiresIn']/2;
	if ($time2renew <= time()) {
	    $data = [
		'accessToken' => $token['accessToken'],
		'refreshToken' => $token['refreshToken']
	    ];
	    $result = $this->sendRequest('accounts/refresh_token',$data,$token['accessToken']);
	    if (isset($result['accessToken'])) {
		$token = [
		    'accessToken' => $result['accessToken'],
		    'expiresIn' => $result['expiresIn'],
		    'expiresAt' => time() + $result['expiresIn'],
		    'refreshToken' => $result['refreshToken']
		];
		$this->setToken($token);
		log::add("EaseeCharger","info",sprintf(__("Token renouvelé. Valable jusqu'à %s",__FILE__),date('d/m/Y H:i:s', $token['expiresAt'])));
	    }
	}
	return $token;
    }

    public function getAccessToken() {
	$token = $this->getToken();
	if (isset($token['accessToken'])) {
	    return $token['accessToken'];
	}
	return '';
    }

    /*     * ******************** Exécution des commandes ********************* */

    /*
     * refresh
     */
    private function execute_refresh($cmd) {
	$charger = $cmd->getEqLogic();
	$serial = $charger->getSerial();
	$path = 'chargers/' . $serial . '/state';
	$response = $this->sendRequest($path);
	foreach ($response as $key => $value) {
	    $logicalIds = $this->mapCmd($key);
	    if (count($logicalIds) == 0) {
		log::add('EaseeCharger','debug',"│   " . sprintf(__('Pas de traitemment pour %s (value: %s)',__FILE__),$key, $value));
		continue;
	    }
	    foreach ($logicalIds as $logicalId) {
		$finalValue = $this->transforms($logicalId,$value);
		log::add("EaseeCharger","debug",sprintf("│   " . "%s value: %s => %s, value: %s", $key, $value, $logicalId, $finalValue));
		$charger->checkAndUpdateCmd($logicalId,$finalValue);
	    }
	}
    }

    /*
     * cable_lock
     */
    public function execute_cable_lock($cmd) {
	$serial = $cmd->getEqLogic()->getSerial();
	$path = 'chargers/' . $serial . '/commands/lock_state';
	$data = ['state' => 'true'];
	$this->sendrequest($path, $data);
    }

    /*
     * cable_unlock
     */
    public function execute_cable_unlock($cmd) {
	$serial = $cmd->getEqLogic()->getSerial();
	$path = 'chargers/' . $serial . '/commands/lock_state';
	$data = ['state' => 'false'];
	$this->sendrequest($path, $data);
    }

    /*
     * pause ON
     */
    public function execute_pause_ON($cmd) {
	$serial = $cmd->getEqLogic()->getSerial();
	$path = 'chargers/' . $serial . '/commands/pause_charging';
	$this->sendrequest($path, 'POST');
    }

    /*
     * pause OFF
     */
    public function execute_pause_OFF($cmd) {
	$serial = $cmd->getEqLogic()->getSerial();
	$path = 'chargers/' . $serial . '/commands/resume_charging';
	$this->sendrequest($path, 'POST');
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
    public function setModifiedChargers( $_chargers = []) {
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
