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

/* * *************************** Includes ********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class EaseeCharger extends eqLogic {

    //========================================================================
    //============================== ATTRIBUTS ===============================
    //========================================================================


    //========================================================================
    //========================== METHODES STATIQUES ==========================
    //========================================================================

    /*     * ******************** recherche de chargers *********************** */

    public static function byAccount($accountName, $_onlyEnable = false) {
	$chargers = self::byTypeAndSearchConfiguration(__CLASS__,'"accountName":"'.$accountName.'"');
	if (!$_onlyEnable) {
	    return $chargers;
	}
	$enabledchargers = [];
	foreach ($chargers as $charger) {
	    if ($charger->getIsEnable() == 1) {
		$enabledChargers[] = $charger;
	    }
	}
	return $enabledChargers;
    }

    public static function bySerial($serial) {
	return self::byTypeAndSearchConfiguration(__CLASS__,'"serial":"'.$serial.'"');
    }

    /*     * ********************** Gestion du daemon ************************* */

    /*
     * Info sur le daemon (function mal nommée pour le core)
     */
    public static function deamon_info() {
	return self::daemon_info();
    }

    /*
     * Info sur le daemon
     */
    public static function daemon_info() {
	$return = [];
	$return['log'] = __CLASS__;
	$return['state'] = 'nok';
	$pid_file = jeedom::getTmpFolder(__CLASS__) . '/daemon.pid';
	if (file_exists($pid_file)) {
	    if (posix_getsid(trim(file_get_contents($pid_file)))) {
		$return['state'] = 'ok';
	    } else {
		shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
	    }
	}
	$return['launchable'] = 'ok';
	return $return;
    }

    /*
     * Lancement de daemon (function mal nommée pour le core)
     */
    public static function deamon_start() {
	return self::daemon_start();
    }

    /*
     * Lancement de daemon
     */
    public static function daemon_start() {
	self::daemon_stop();
	$daemon_info = self::daemon_info();
	if ($daemon_info['launchable'] != 'ok') {
	    throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
	}

	$logLevel = log::convertLogLevel(log::getLogLevel(__CLASS__));
	if ($logLevel == 'debug' and config::bykey('extendedDebug','EaseeCharger') == 1) {
	    $logLevel = 'extendedDebug';
	}

	$path = realpath(dirname(__FILE__) . '/../../ressources/bin'); // répertoire du démon
	$cmd = 'python3 ' . $path . '/EaseeChargerd.py';
	$cmd .= ' --loglevel ' . $logLevel;
	$cmd .= ' --socketport ' . config::byKey('daemon::port', __CLASS__); // port
	$cmd .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/EaseeCharger/core/php/jeeEaseeCharger.php';
	$cmd .= ' --apikey ' . jeedom::getApiKey(__CLASS__);
	$cmd .= ' --pid ' . jeedom::getTmpFolder(__CLASS__) . '/daemon.pid';
	log::add(__CLASS__, 'info', 'Lancement démon');
	if (config::byKey('unsecurelog','EaseeCharger') != 1) {
	    $cmd .= ' --secureLog';
	    $cmd2log = preg_replace('/--apikey\s\S*/','--apikey **********',$cmd);
	} else {
	    $cmd2log = $cmd;
	}
	log::add(__CLASS__, "info", $cmd2log . ' >> ' . log::getPathToLog('EaseeCharger_daemon') . ' 2>&1 &');
	$result = exec($cmd . ' >> ' . log::getPathToLog('EaseeCharger_daemon.out') . ' 2>&1 &');
	$i = 0;
	while ($i < 20) {
	    $daemon_info = self::daemon_info();
	    if ($daemon_info['state'] == 'ok') {
		break;
	    }
	    sleep(1);
	    $i++;
	}
	if ($daemon_info['state'] != 'ok') {
	    log::add(__CLASS__, 'error', __('Impossible de lancer le démon, vérifiez le log', __FILE__), 'unableStartDaemon');
	    return false;
	}
	message::removeAll(__CLASS__, 'unableStartDaemon');
	return true;
    }

    /*
     * Arret du daemon (function mal nommée pour le core)
     */
    public static function deamon_stop() {
	return self::daemon_stop();
    }

    /*
     * Arret du daemon
     */
    public static function daemon_stop() {
	if (self::daemon_info()['state'] == 'ok') {
	    log::add("EaseeCharger","debug",__("Envoi de la commande d'arrêt au daemon",__FILE__));
	    self::send2daemon(['cmd' => 'shutdown']);
	    foreach (range(0,10) as $i) {
		if (self::daemon_info()['state'] == 'nok'){
		    log::add("EaseeCharger","debug",__("Le daemon s'est arrêté",__FILE__));
		    return;
		}
		sleep(1);
	    }
	}

	$pid_file = jeedom::getTmpFolder(__CLASS__) . '/daemon.pid';
	if (file_exists($pid_file)) {
	    $pid = intval(trim(file_get_contents($pid_file)));
	    log::add(__CLASS__, 'info', __('kill process: ',__FILE__) . $pid);
	    system::kill($pid, false);
	    foreach (range(0,15) as $i){
		if (self::daemon_info()['state'] == 'nok'){
		    break;
		}
		sleep(1);
	    }
	    return;
	}
    }

    /*
     * Envoi d'un message au daemon
     */
    public static function send2daemon($payload) {
	if (self::daemon_info()['state'] != 'ok') {
	    throw new Exception (__("Le daemon n'est pas démarré",__FILE__));
	}
	$payload['apikey'] = jeedom::getApiKey('EaseeCharger');
	$payload2log = $payload;
	if (config::byKey('unsecurelog','EaseeCharger') != 1) {
	    if (isset($payload2log['accessToken'])) {
		$payload2log['accessToken'] = "**********";
	    }
	    if (isset($payload2log['apikey'])) {
		$payload2log['apikey'] = "**********";
	    }
	}
	log::add("EaseeCharger","debug",__("Envoi d'un message au daemon: " . json_encode($payload2log),__FILE__));
	$payload = json_encode($payload);
	$socket = socket_create(AF_INET, SOCK_STREAM, 0);
	socket_connect($socket,'127.0.0.1',(int)config::byKey('daemon::port','EaseeCharger'));
	socket_write($socket, $payload, strlen($payload));
	socket_close($socket);
    }

    /*
     * Function appelée lorsque la daemon confirme sont démarrage
     */
    public static function daemon_started() {
	log::add("EaseeCharger","info",__("Le daemon est démarré",__FILE__));
	$accounts = EaseeCharger_account::all(true);
	foreach ($accounts as $account) {
	    $account->start_account_on_daemon();
	}
    }

    /*     * ************************ Les widgets **************************** */

    /*
     * template pour les widget
     */
    public static function templateWidget() {
	$return = [
	    'action' => [
		'other' => [
		    'cable_lock' => [
			'template' => 'cable_lock',
			'replace' => [
			    '#_icon_on_#' => '<i class=\'icon_green icon jeedom-lock-ferme\'></i>',
			    '#_icon_off_#' => '<i class=\'icon_orange icon jeedom-lock-ouvert\'></i>'
			]
		    ],
		    'paused' => [
			'template' => 'tmplicon',
			'replace' => [
			    '#_icon_on_#' => '<i class=\'icon fas fa-play\'></i>',
			    '#_icon_off_#' => '<i class=\'icon fas fa-pause\'></i>'
			]
		    ]
		]
	    ],
	    'info' => [
		'numeric' => [
		    'etat' => [
			'template' => 'etat',
			'replace' => [
			    '#texte_1#' =>  '{{Débranché}}',
			    '#texte_2#' =>  '{{En attente}}',
			    '#texte_3#' =>  '{{Recharge}}',
			    '#texte_4#' =>  '{{Terminé}}',
			    '#texte_5#' =>  '{{Erreur}}',
			    '#texte_6#' =>  '{{Prêt}}'
			]
		    ]
		]
	    ]
	];
	return $return;
    }

    /*     * ************************ Les crons **************************** */

    public static function cronHourly() {
	EaseeCharger_account::cronHourly();
    }

    //========================================================================
    //========================= METHODES D'INSTANCE ==========================
    //========================================================================

    /*
     * Préparation avant sauvegarde
     */
    public function preSave() {
	$this->_wasEnable = 0;
	$oldCharger = EaseeCharger::byId($this->getId());
	if (is_object($oldCharger) && $oldCharger->getIsEnable() != 0) {
	    $this->_wasEnable = 1;
	    $this->_oldserial = $oldCharger->getSerial();
	    $this->_oldHumanName = $oldCharger->getHumanName();
	    $this->_oldAccountName = $oldCharger->getAccountName();
	}
    }

    /*
     * Vérifications avant enregistrement
     */
    public function preUpdate() {
	if ($this->getAccountName() == '') {
	    throw new Exception (__("Un compte doit être sélectioné",__FILE__));
	}
	if ($this->getIsEnable() == 1) {
	    $account = $this->getAccount();
	    if (is_object($account) and $account->getIsEnable() != 1) {
		throw new Exception (__("Le chargeur ne peut pas être activé si l'account associé ne l'est pas!",__FILE__));
	    }
	}
    }

    /*
     * Ajout des cmds après création du chargeur
     */
    public function postInsert() {
	$this->createCmds();
    }

    /*
     * Gestion de daemon après sauvegarde
     */
    public function postSave() {
	if (self::daemon_info()['state'] == 'ok') {
	    if ($this->getIsEnable() == 1){
		$needDaemonRestart = false;
		if ($this->_wasEnable !=1) {
		    $needDaemonRestart = true;
		}
		if ($this->getSerial() != $this->_oldserial) {
		    $needDaemonRestart = true;
		}
		if ($this->getHumanName() != $this->_oldHumanName) {
		    $needDaemonRestart = true;
		}
		if ($this->getAccountName() != $this->_oldAccountName) {
		    $needDaemonRestart = true;
		}
		if ($needDaemonRestart){
		    log::add("EaseeCharger","debug",sprintf(__("Redémarrage du thread du daemon pour %s",__FILE__),$this->getHumanName()));
		    $this->stop_daemon_thread();
		    sleep (1);
		    $this->start_daemon_thread();
		}
	    }
	    if ($this->getIsEnable() != 1 && $this->_wasEnable ==1) {
		$this->stop_daemon_thread();
	    }
	}
    }

    public function start_daemon_thread() {
	log::add("EaseeCharger","debug",sprintf(__("Lancement du thread de daemon pour %s",__FILE__),$this->getHumanName()));
	$this->send2daemon([
	    'cmd' => 'startCharger',
	    'id' => $this->getId(),
	    'serial' => $this->getSerial(),
	    'name' => $this->getHumanName(),
	    'account' => $this->getAccountName()
	]);
    }

    public function stop_daemon_thread() {
	log::add("EaseeCharger","debug",sprintf(__("Arrêt du thread de daemon pour %s",__FILE__),$this->getHumanName()));
	$this->send2daemon([
	    'cmd' => 'stopCharger',
	    'id' => $this->getId(),
	]);
    }

    public function refresh() {
	$cmd = $this->getCmd('action', 'refresh');
	if (is_object($cmd)) {
	    $cmd->execute();
	}
    }

    /*
     * Path de l'image du chargeur
     */
    public function getPathImg() {
	$image = $this->getConfiguration('image');
	if ($image == '') {
	    $image = "/plugins/EaseeCharger/plugin_info/EaseeCharger_icon.png";
	}
	return $image;
    }

    public function configureCmd(&$cmd, $config, $secondPass=false) {
	if ($secondPass == false) {
	    $needSave = false;

	    // displayName
	    // -----------
	    if (isset($config['displayName'])) {
		if ($cmd->getDisplay('showNameOndashboard') != $config['displayName']) {
		    $cmd->setDisplay('showNameOndashboard',$config['displayName']);
		    $needSave = true;
		}
	    }

	    // display::graphStep
	    // ------------------
	    if (isset($config['display::graphStep'])) {
		if ($cmd->getDisplay('graphStep') != $config['display::graphStep']) {
		    $cmd->setDisplay('graphStep',$config['display::graphStep']);
		    $needSave = true;
		}
	    }

	    // eqLogic_id
	    // ----------
	    if ($cmd->getEqLogic_id() != $this->getId()) {
		$cmd->setEqLogic_id($this->getId());
		$needSave = true;
	    }

	    // name
	    // ----
	    if (isset($config['name'])) {
		if ($cmd->getName() != $config['name']) {
		    $cmd->setName($config['name']);
		    $needSave = true;
		}
	    } else {
		if ($cmd->getName() != $cmd->getLogicalId()) {
		    $cmd->setName($cmd->getLogicalId);
		    $needSave = true;
		}
	    }

	    // order
	    // -----
	    if (isset($config['order'])) {
		$oldOrder = $cmd->getOrder();
		if ($oldOrder != $config['order']) {
		    log::add("EaseeCharger","debug","========================");
		    log::add("EaseeCharger","debug",$config['order']);
		    foreach ($this->getCmd() as $c) {
			log::add("EaseeCharger","debug","c: " . $c->getLogicalId() . " o: " . $c->getOrder());
			if ($c->getOrder() >= $config['order']) {
			    if ($odlsOrder == 0 || $c->getOrder < $oldOrder) {
				$c->setOrder($c->getOrder()+1);
				$c->save();
			    }
			}
			log::add("EaseeCharger","debug","c: " . $c->getLogicalId() . " o: " . $c->getOrder());
		    }
		    $cmd->setOrder($config['order']);
		    $needSave = true;
		}
	    }

	    // returnAfterDisplay
	    // ------------------
	    if (isset($config['returnAfterDisplay'])) {
		if ($cmd->getDisplay('forceReturnLineAfter') != $config['returnAfterDisplay']) {
		    $cmd->setDisplay('forceReturnLineAfter', $config['returnAfterDisplay']);
		}
	    }

	    // rounding
	    // --------
	    if (isset($config['rounding'])) {
		if ($cmd->getConfiguration('historizeRound') != $config['rounding']) {
		    $cmd->setConfiguration('historizeRound',$config['rounding']);
		    $needSave = true;
		}
	    }

	    // subType
	    // -------
	    if (isset($config['subType'])) {
		if ($cmd->getSubType() != $config['subType']) {
		    $cmd->setSubType($config['subType']);
		    $needSave = true;
		}
	    } else {
		log::add("EaseeCharger","error",sprintf(__("Le subType de la commande %s n'est pas défini",__FILE__),$logicalId));
	    }

	    // template
	    // --------
	    if (isset($config['template'])) {
		if ($cmd->getTemplate('dashboard') != $config['template']) {
		    $cmd->setTemplate('dashboard',$config['template']);
		    $needSave = true;
		}
		if ($cmd->getTemplate('mobile') != $config['template']) {
		    $cmd->getTemplate('mobile',$config['template']);
		    $needSave = true;
		}
	    }

	    // type
	    // ----
	    if (isset($config['type'])) {
		if ($cmd->getType() != $config['type']) {
		    $cmd->setType($config['type']);
		    $needSave = true;
		}
	    } else {
		log::add("EaseeCharger","error",sprintf(__("Le type de la commande %s n'est pas défini",__FILE__),$logicalId));
	    }

	    // unite
	    // -----
	    if (isset($config['unite'])) {
		if ($cmd->getUnite() != $config['unite']) {
		    $cmd->setUnite($config['unite']);
		    $needSave = true;
		}
	    }

	    // visible
	    // -------
	    if (isset($config['visible'])) {
		if ($cmd->getIsVisible() != $config['visible']) {
		    $cmd->setIsVisible($config['visible']);
		    $needSave = true;
		}
	    }
	    if ($needSave) {
		$cmd->save();
	    }
	    return $cmd;
	} else {
	    $needSave = false;

	    if (isset ($config['calcul'])) {
		$calcul = $config['calcul'];
		preg_match_all('/#(.*?)#/',$calcul,$matches);
		foreach ($matches[1] as $cible) {
		    $cmdCible = $this->getCmd(null, $cible);
		    if (is_object($cmdCible)) {
			$calcul = str_replace('#' . $cible . '#','#' . $cmdCible->getId() . '#', $calcul);
		    }
		}
		if ($cmd->getConfiguration('calcul') !=  $calcul) {
		    $cmd->setConfiguration('calcul', $calcul);
		    $needSave = true;
		}
	    }

	    if (isset($config['value'])) {
		$cmdValue = $this->getCmd(null, $config['value']);
		$value = "";
		if (is_object($cmdValue)) {
		    if ($cmd->getType() == 'info') {
			$value = '#' . $cmdValue->getId() . '#';
		    } else {
			$value = $cmdValue->getId();
		    }
		}
		if ($cmd->getValue(i) != $value) {
		    $cmd->setValue($value);
		    $needSave = true;
		}
	    }
	    if ($needSave) {
		$cmd->save();
	    }
	}
    }

    public function createCmds() {
	$cfgFile = realpath (__DIR__ . '/../config/cmd.config.ini');
	log::add("EaseeCharger","debug",sprintf(__("Lecture du fichier %s ...",__FILE__),$cfgFile));
	$cmdConfigs = parse_ini_file($cfgFile,true,INI_SCANNER_RAW);
	$createdCmds = [];
	foreach ($cmdConfigs as $logicalId => $config) {
	    $cmd = cmd::byEqLogicIdAndLogicalId($this->getId(),$logicalId);
	    if (is_object($cmd)) {
		log::add("EaseeCharger","debug",sprintf(__("%s existe déjà",__FILE__),$logicalId));
		continue;
	    }
	    log::add("EaseeCharger","info",sprintf(__("Création de la commande %s...",__FILE__),$logicalId));
	    $cmd = new EaseeChargerCMD();
	    $cmd->setLogicalId($logicalId);
	    $this->configureCmd($cmd,$config);
	    $createdCmds[] = $logicalId;
	}

	foreach ($cmdConfigs as $logicalId => $config) {
	    if (!in_array($logicalId, $createdCmds)) {
		continue;
	    }
	    $cmd = $this->getCmd(null,$logicalId);
	    $this->configureCmd($cmd,$config,true);
	}
	$this->refresh();
    }

    public function getAccount() {
	return EaseeCharger_account::byName($this->getaccountName());
    }

    //========================================================================
    //=========================== GETTEUR SETTTEUR ===========================
    //========================================================================

    public function getAccountName() {
	return $this->getConfiguration('accountName');
    }

    public function setAccountName($_accountName) {
	$this->setConfiguration('accountName',$_accountName);
    }

    public function getSerial() {
	return $this->getConfiguration('serial');
    }
}

class EaseeChargerCmd extends cmd {

    public function dontRemoveCmd() {
	if ($this->getLogicalId() == 'refresh') {
	    return true;
	}
	return false;
    }

    public function preUpdate() {
	if ($this->getType() == 'info') {
	    $calcul = $this->getConfiguration('calcul');
	    if ($calcul) {
		if (strpos($calcul, '#' . $this->getId() . '#') !== false) {
		    throw new Exception(__('Vous ne pouvez appeler la commande elle-même (boucle infinie) sur',__FILE__) . ' : ' . $this->getName());
		}
		$added_value = [];
		preg_match_all('/#(\d+)#/', $calcul, $matches);
		$value = '';
		foreach ($matches[1] as $cmd_id) {
		    $cmd = cmd::byId($cmd_id);
		    if (is_object($cmd) && $cmd->getType() == 'info') {
			if (isset($added_value[$cmd_id])) {
			    continue;
			}
			$value .= '#' . $cmd_id . '#';
			$added_value[$cmd_id] = $cmd_id;
		    }
		}
		$this->setValue($value);
	    }
	}
    }

    public function execute($_options = []) {
	switch ($this->getType()) {
	case 'info':
	    $calcul = $this->getConfiguration('calcul');
	    if ($calcul != '') {
		return jeedom::evaluateExpression($calcul);
	    }
	    return $this->execCmd();

	case 'action':
	    $this->getEqLogic()->getAccount()->execute($this);
	}
    }

    public function getValueTime() {
	return DateTime::createFromFormat("Y-m-d H:i:s", $this->getValueDate())->getTimeStamp();
    }

    public function getCollectTime() {
	return DateTime::createFromFormat("Y-m-d H:i:s", $this->getCollectDate())->getTimeStamp();
    }
}

require_once __DIR__  . '/EaseeCharger_account.class.php';
