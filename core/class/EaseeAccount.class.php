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

class EaseeAccount {

    private static $_mapping = null;
    private static $_transforms = null;
    private static $_httpCodes = null;

    private $id = '';
    private $name = '';
    private $userName = '';
    private $password = '';
    private $_site = 'https://api.easee.com/api/';
    private $_modifiedChargers = [];

    /*     * ********************************************************************** */
    /*     * *************************** Méthodes Static ************************** */
    /*     * ********************************************************************** */

    /*
     * Retourne un nouvel ID libre pour un account
     */
    public static function nextId() {
        $nextId = config::byKey('nextAccountId','EaseeCharger',1);
        config::save('nextAccountId',$nextId+1,'EaseeCharger');
        return $nextId;
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
     * byId
     */
    public static function byId($id) {
        $key = 'account::' . $id;
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
    public static function all() {
        $configs = config::searchKey('account::%', 'EaseeCharger');
        $accounts = [];
        foreach ($configs as $config) {
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
    public static function cron5() {
        $accounts = self::all(true);
        foreach ($accounts as $account) {
            log::add("EaseeCharger","debug",sprintf(__("Vérification du token pour l'account %s",__FILE__),$account->getName()));
            $token = $account->getToken();
            if (is_array($token)){
                log::add("EaseeCharger","debug","  " . sprintf(__("Token pour %s valide jusqu'à %s",__FILE__),$account->getName(),date('d/m/Y H:i:s', $token['expiresAt'])));
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

    /*
     * La liste des codes HTTP
     */
    public static function getHttpCode($code = null) {
        if (self::$_httpCodes == null) {
            $httpCodesFileName = __DIR__ . "/../config/httpCodes.json";
            $httpCodes = translate::exec(file_get_contents($httpCodesFileName),$httpCodesFileName);
            self::$_httpCodes = json_decode($httpCodes,true);
        }
        if (!is_array(self::$_httpCodes)) {
            log::add("EaseeCharger","error", "httpCodes " . __("introuvables",__FILE__));
            return null;
        }
        if ($code) {
            return self::$_httpCodes[$code] ?? null;
        }
        return self::$_httpCodes;
    }

    /*     * ********************************************************************** */
    /*     * ********************** Méthodes d'instance *************************** */
    /*     * ********************************************************************** */

    /*
     * Enregistrement du compte
     */
    public function save() {
        if (!$this->getName()) {
            throw new Exception (__("Le nom de l'account doit être défini!",__FILE__));
        }
        $accounts = static::all();
        foreach ($accounts as $account) {
            if (($account->getId() != $this->getId()) and ($account->getName() == $this->getName())) {
                throw new Exception (__("Ce nom est déjà utilisé pour un autre compte!",__FILE__));
            }
        }
        if ($this->getId() == '') {
            $this->setId(static::nextId());
        }
        if (!$this->getUserName()) {
            throw new Exception (__("Le nom du compte doit être défini!",__FILE__));
        }
        $value = utils::o2a($this);
        $value['password'] = utils::encrypt($value['password']);
        $value = json_encode($value);
        $key = 'account::' . $this->id;
        config::save($key, $value, 'EaseeCharger');
        $this->login();

        if (EaseeCharger::daemon_info()['state'] == 'ok') {
            $this->register_account_on_daemon();
        }
        return $this;
    }

    /*
     * Suppression du compte
     */
    public function remove() {
        $chargers = EaseeCharger::byAccount($this->getId(), false);
        if (count($chargers) > 0) {
            throw new Exception (sprintf(__("Le compte %s est utilisé pour le chargeur %s",__FILE__), $this->name, $chargers[0]->getName()));
        }
        $message = [
            'cmd' => 'unregisterAccount',
        ];
        $this->send2daemon ($message);
        $cache = cache::byKey('EaseeAccount:' . $this->getId());
        if ($cache) {
            $cache->remove();
        }
        $key = 'account::' . $this->id;
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
        $payload['accountId'] = $this->getId();
        $payload['accountName'] = $this->getName();
        EaseeCharger::send2daemon($payload);
    }

    /*
     * Lancement du thread du daemon
     */
    public function register_account_on_daemon() {
        $token = $this->getToken();
        if ($token === false) {
            throw Exception(__("Token introuvable",__FILE__));
        }
        $message = [
            'cmd' => 'registerAccount',
            'accessToken' => $token['accessToken'],
            'expiresAt' => $token['expiresAt'],
            'expiresIn' => $token['expiresIn']
        ];
        $this->send2daemon ($message);
    }

    public function account_on_daemon_started() {
        foreach (EaseeCharger::byAccount($this->getId(),true) as $charger){
            $charger->register_on_daemon();
        }
    }

    /*
     * login avec UserName et password pour obtention d'un token
     */
    public function login() {
        $data = [
            'userName' => $this->getUserName(),
            'password' => $this->getPassword(),
        ];
            $result = $this->sendRequest('accounts/login', $data);
            if (isset($result['accessToken'])) {
                $token = [
                    'accessToken' => $result['accessToken'],
                    'expiresIn' => $result['expiresIn'],
                    'refreshToken' => $result['refreshToken']
                ];
                $this->saveToken($token);
            }
        return 'ok';
    }

    /*
     * Envoi d'une requête API au cloud Easee
     */
    private function sendRequest($path, $data = '', $token = null) {
        log::add("EaseeCharger","debug","┌─" .__("Easee: envoi d'une requête au cloud", __FILE__));

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

        $header = [
            "Accept: application/json",
            "Content-Type: application/json"
        ];

        if ($path !== "accounts/login") {
            $accessToken = $token ?? $this->getAccessToken();
            if ($accessToken) {
                $header[] = 'Authorization: Bearer ' . $accessToken;
            }
        }

        log::add("EaseeCharger", "debug", "│       data: " . json_encode($data2log));
        log::add("EaseeCharger", "debug", "│        URL: " . $this->_site . $path);
        log::add("EaseeCharger", "debug", "│     Method: " . $method);
        log::add("EaseeCharger", "debug", "│     Header: " . json_encode($header));

        if (is_array($data)) {
            $data = json_encode($data);
        }

        $curl = curl_init();
        if ($curl === false) {
            throw new Exception (__("└─Erreur lors l'initialisation de CURL",__FILE__));
        }
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $this->_site . $path,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_POSTFIELDS => $data,
        ]);

        $response = curl_exec($curl);
        if ($response === false) {
            log::add("EaseeCharger","error",sprintf(__("└─Erreur lor de la requête CURL %d: %s",__FILE__),curl_errno($surl),curl_error($curl)));
            return false;
        }
        if (curl_errno($curl)) {
            log::add("EaseeCharger","error",sprintf(__("└─Erreur CURL %d: %s",__FILE__),curl_errno($surl),curl_error($curl)));
        }

        $httpCode = curl_getinfo($curl,CURLINFO_HTTP_CODE);
        if (substr($httpCode,0,1) != '2') {
            $response = is_json($response,$response);
            if (is_array($response)) {
                $txt = sprintf(__("Code HTTP: %s - [%s]%s",__FILE__), $httpCode, $response['errorCode'], $response['title']);
                log::add("EaseeCharger","error", "└─" . $txt);
                throw new EaseeCloudException ($response);
            } else {
                if (!$response) {
                    $infos = self::getHttpCode($httpCode);
                    if (is_array($infos)) {
                        $response = sprintf("%s - %s", $infos['intitule'] ,$infos['signification']);
                    }
                }
                $txt = sprintf("%s: %s", $httpCode, $response);
                log::add("EaseeCharger","error", "└─" . $txt);
                throw new Exception ($txt);
            }
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
        log::add("EaseeCharger","info","┌─" . sprintf(__("%s: execution de %s",__FILE__), $this->getName() , $cmd->getLogicalId()));
        log::add("EaseeCharger","debug","│ " . __("Chargeur",__FILE__) . sprintf(": %s (%s)", $charger->getName(), $charger->getLogicalId()));
        if (! is_a($cmd, "EaseeChargerCmd")){
            throw new Exception (sprintf(__("└─La commande %s n'est pas une commande de type %s",__FILE__),$cmd->getId(), "EaseeChargerCmd"));
        }

        $method = 'execute_' . $cmd->getLogicalId();
        if (!method_exists($this, $method)) {
            $msg = sprintf(__("La méthode %s est introuvable",__FILE__),$method);
            log::add("EaseeCharger","error","└─" . $msg);
            return;
        }
        $this->$method($cmd);
        log::add("EaseeCharger","debug","└─" . __("OK",__FILE__));
        return;
    }

    /*
     * token
     */
    public function saveToken($_token) {
        $payload = array (
            'cmd' => 'newToken',
            'expiresIn' => $_token['expiresIn'],
            'expiresAt' => $token['expiresAt'],
            'expiresIn' => $token['expiresIn']
        );
        if (array_key_exists('accessToken',$_token)) {
            $payload['accessToken'] = $_token['accessToken'];
            $_token['accessToken'] = utils::encrypt($_token['accessToken']);
        }
        if (array_key_exists('refreshToken',$_token)) {
            $_token['refreshToken'] = utils::encrypt($_token['refreshToken']);
        }
        if (! array_key_exists('expireAt',$_token)) {
            $_token['expiresAt'] = time() + $_token['expiresIn'];
            $payload['expiresAt'] = $_token['expiresAt'];
        }
        $lifetime = 192800;
        cache::set('EaseeAccount:'. $this->getId(), json_encode($_token,JSON_UNESCAPED_UNICODE), $lifetime);
        $this->send2daemon($payload);
        return $this;
    }

    public function readToken() {
        $cache = cache::byKey('EaseeAccount:' . $this->getId());
        if (!is_object($cache)) {
            log::add("EaseeCharger","debug","   Pas de token en cache.");
            return false;
        }
        $token = $cache->getValue();
        $token = is_json($token,$token);
        if (!is_array($token)) {
            return false;
        }
        $token['accessToken'] = utils::decrypt($token['accessToken']);
        $token['refreshToken'] = utils::decrypt($token['refreshToken']);
        return $token;
    }

    public function getToken($retry = false) {
        if ($retry) {
            log::add("EaseeCharger","debug","getToken (retrying) ....");
        } else {
            log::add("EaseeCharger","debug","getToken ....");
        }
        $token = $this->readToken();
        if ($token === false or !isset($token['accessToken']) or $token['accessToken'] == '') {
            if ($retry) {
                return false;
            }
            log::add("EaseeCharger","debug","   Pas de token en cache. Tentative de login...");
            if (!$this->login()) {
                return False;
            }
            log::add("EaseeCharger","debug","   ok");
            return $this->getToken(true);
        }
        log::add("EaseeCharger","debug","   token trouvé en cache.");
        $time2renew = $token['expiresAt'] - $token['expiresIn']/2;
        if (time() >= $time2renew) {
            if ($retry) {
                return false;
            }
            log::add("EaseeCharger","info","   Le token expire bientôt, tentative de renouvellement...");
            $data = [
                'refreshToken' => $token['refreshToken']
            ];
            try {
                $result = $this->sendRequest('accounts/refresh_token',$data,$token['accessToken']);
            } catch (EaseeCloudException $e) {
                $result = $e->getResponse();
                log::add("EaseeCharger","warning",sprintf("%s (%s) - %s", $result['errorCodeName'], $result['errorCode'], $result['title']));
                $this->login();
                return $this->getToken(true);
            }
            if (isset($result['accessToken'])) {
                $token = [
                    'accessToken' => $result['accessToken'],
                    'expiresIn' => $result['expiresIn'],
                    'refreshToken' => $result['refreshToken']
                ];
                $this->saveToken($token);
            }
        }
        return $token;
    }

    public function getAccessToken() {
        $token = $this->getToken();
        return $token['accessToken'];
    }
    /*     * ******************** Exécution des commandes ********************* */

    /*
     * refresh
     */
    private function execute_refresh($cmd) {
        $charger = $cmd->getEqLogic();
        $serial = $charger->getLogicalId();
        $path = 'chargers/' . $serial . '/state';
        $response = $this->sendRequest($path);
        foreach ($response as $key => $value) {
            $logicalIds = $this->mapCmd($key);
            if (count($logicalIds) == 0) {
                log::add('EaseeCharger','debug',"│   " . sprintf(__('Pas de traitemment pour %s (value: %s)',__FILE__),$key, print_r($value,true)));
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
        $serial = $cmd->getEqLogic()->getLogicalId();
        $path = 'chargers/' . $serial . '/commands/lock_state';
        $data = ['state' => true];
        $this->sendrequest($path, $data);
    }

    /*
     * cable_unlock
     */
    public function execute_cable_unlock($cmd) {
        $serial = $cmd->getEqLogic()->getLogicalId();
        $path = 'chargers/' . $serial . '/commands/lock_state';
        $data = ['state' => false];
        $this->sendrequest($path, $data);
    }

    /*
     * pause ON
     */
    public function execute_pause_ON($cmd) {
        $serial = $cmd->getEqLogic()->getLogicalId();
        $path = 'chargers/' . $serial . '/commands/pause_charging';
        $this->sendrequest($path, 'POST');
    }

    /*
     * pause OFF
     */
    public function execute_pause_OFF($cmd) {
        $serial = $cmd->getEqLogic()->getLogicalId();
        $path = 'chargers/' . $serial . '/commands/resume_charging';
        $this->sendrequest($path, 'POST');
    }

    /*     * ********************** Getteur Setteur *************************** */

    /*
     * id
     */
    public function setId($_id) {
        $this->id = $_id;
        return $this;
    }

    public function getId() {
        if (isset($this->id)) {
            return $this->id;
        }
        return '';
    }

    /*
     * login
     */
    public function setUserName($_userName) {
        $this->userName = $_userName;
        return $this;
    }

    public function getUserName() {
        return $this->userName;
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
        $this->response = $response;
        if (!is_array($response)) {
            $message = $response;
        } else {
            $message = $response['title'] ?? $message;
        }
        parent::__construct($message, $code, $previous);
    }

    public function getResponse() {
        return $this->response;
    }
}
