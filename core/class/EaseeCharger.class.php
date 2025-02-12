<?php
// vim: tabstop=4 autoindent
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

	const PYTHON_PATH = __DIR__ . '/../../ressources/venv/bin/python3';
	public static $_widgetPossibility = array(
		'custom' => true,
		'parameters' => array(
			'hiddenSignal' => array(
				'name' => '{{Ne pas afficher le signal de communication wifi ou cellulaire (0 ou 1)}}',
				'allow_displayType' => true,
				'default' => '0',
				'type' => 'input',
			),
			'hiddenCable' => array(
				'name' => '{{Ne pas afficher la tuile "Cable" (0 ou 1)}}',
				'allow_displayType' => true,
				'default' => '0',
				'type' => 'input',
			),
			'hiddenCharge' => array(
				'name' => '{{Ne pas afficher la tuile "Charge" (0 ou 1)}}',
				'allow_displayType' => true,
				'default' => '0',
				'type' => 'input',
			),
			'hiddenAlimentation' => array(
				'name' => '{{Ne pas afficher la tuile "Alimentation" (0 ou 1)}}',
				'allow_displayType' => true,
				'default' => '0',
				'type' => 'input',
			),
		),
	);

	//========================================================================
	//========================== METHODES STATIQUES ==========================
	//========================================================================

	/*     * ******************** recherche de chargers *********************** */

	public static function byAccount($accountId, $_onlyEnable = false) {
		$chargers = self::byTypeAndSearchConfiguration(__CLASS__,'"accountId":"'.$accountId.'"');
		if (!$_onlyEnable) {
			return $chargers;
		}
		$enabledChargers = [];
		foreach ($chargers as $charger) {
			if ($charger->getIsEnable() == 1) {
				$enabledChargers[] = $charger;
			}
		}
		return $enabledChargers;
	}

	/*     * ********************* Gestion des dependances ************************* */

	private static function pythonRequirementsInstalled(string $pythonPath, string $requirementsPath) {
		if (!file_exists($pythonPath) || !file_exists($requirementsPath)) {
			return false;
		}
		exec("{$pythonPath} -m pip --no-cache-dir  freeze", $packages_installed);
		$packages = join("||", $packages_installed);
		exec("cat {$requirementsPath}", $packages_needed);
		foreach ($packages_needed as $line) {
			if (preg_match('/([^\s]+)[\s]*([>=~]=)[\s]*([\d+\.?]+)$/', $line, $need) === 1) {
				if (preg_match('/' . $need[1] . '==([\d+\.?]+)/', $packages, $install) === 1) {
					if ($need[2] == '==' && $need[3] != $install[1]) {
						return false;
					} elseif (version_compare($need[3], $install[1], '>')) {
						return false;
					}
				} else {
					return false;
				}
			}
		}
		return true;
	}

	public static function dependancy_info() {
		$return = array();
		$return['log'] = log::getPathToLog(__CLASS__ . '_update');
		$return['progress_file'] = jeedom::getTmpFolder(__CLASS__) . '/dependance';
		$return['state'] = 'ok';
		if (file_exists(jeedom::getTmpFolder(__CLASS__) . '/dependance')) {
			$return['state'] = 'in_progress';
		} elseif (!self::pythonRequirementsInstalled(self::PYTHON_PATH, __DIR__ . '/../../ressources/requirements.txt')) {
			$return['state'] = 'nok';
		}
		return $return;
	}

	public static function dependancy_install() {
		log::remove(__CLASS__ . '_update');
		return array('script' => __DIR__ . '/../../ressources/install_#stype#.sh', 'log' => log::getPathToLog(__CLASS__ . '_update'));
	}


	/*     * ********************* Gestion du daemon ************************* */
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
		$cmd = self::PYTHON_PATH . " {$path}/EaseeChargerd.py";
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
		$accounts = EaseeAccount::all(true);
		foreach ($accounts as $account) {
			$account->register_account_on_daemon();
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
							'#_icon_on_#' => "<i class='icon_green icon jeedom-lock-ferme'></i>",
							'#_icon_off_#' => "<i class='icon_orange icon jeedom-lock-ouvert'></i>"
						]
					],
					'paused' => [
						'template' => 'tmplicon',
						'replace' => [
							'#_icon_on_#' => "<i class='icon fas fa-play' style='font-size:15px;margin-top:20px'></i>",
							'#_icon_off_#' => "<i class='icon fas fa-pause ' style='font-size:15px;margin-top:20px'></i>"
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
				],
			]
		];
		return $return;
	}

	public static function pluginGenericTypes() {
		$generics = array(
			'CURRENT' => array(
				'name' => __('Courant', __FILE__),
				'familyid' => 'Electricity',
				'family' => __('Electricité',__FILE__),
				'type' => 'info',
				'subtype' => array('numeric')
			)
		);
		return $generics;
	}

	/*     * ************************ Les crons **************************** */

	public static function cron30() {
		EaseeAccount::cron30();
	}

	//========================================================================
	//========================= METHODES D'INSTANCE ==========================
	//========================================================================

	function toHtml($_version = 'dashboard') {
		log::add("EaseeCharger","debug",$this->getName() . ":  widget_perso = " . $this->getConfiguration('widget_perso',1));
		if ($this->getConfiguration('widget_perso',1) == '0') {
		   return parent::toHtml($_version);
		}

		$replace = $this->preToHtml($_version);
		if (!is_array($replace)) {
			return $replace;
		}
		
		$version = jeedom::versionAlias($_version);

		$replace['#version#'] = $_version;
		$replace['#eqLogic_id#'] = $this->getId();

		foreach (['#hiddenSignal#', '#hiddenCable#', '#hiddenCharge#', '#hiddenAlimentation#'] as $param) {
			if ($replace[$param] == 0) {
				$replace[$param] = '';
			} else {
				$replace[$param] = 'hidden';
			}
		}

		$template = getTemplate('core', $version, 'EaseeCharger', 'EaseeCharger');

		foreach ($this->getCmd(null,null,true) as $cmd) {
			$logicalId = $cmd->getLogicalId();
			$replace['#' . $logicalId . '_widget#'] = $cmd->toHtml();
			$replace['#' . $logicalId . '_id#'] = $cmd->getId();
			$replace['#' . $logicalId . '_name#'] = $cmd->getName();
			$replace['#' . $logicalId . '_history#'] = '';
			$replace['#' . $logicalId . '_hide_history#'] = 'hidden';
			$replace['#' . $logicalId . '_unite#'] = $cmd->getUnite();
			$replace['#' . $logicalId . '_minValue#'] = $cmd->getConfiguration('minValue', 0);
			$replace['#' . $logicalId . '_maxValue#'] = $cmd->getConfiguration('maxValue', 100);
			$replace['#' . $logicalId . '_uid#'] = $cmd->getId() . eqLogic::UIDDELIMITER . mt_rand() . eqLogic::UIDDELIMITER;
			$replace['#' . $logicalId . '_generic_type#'] = $cmd->getGeneric_type();
			$replace['#' . $logicalId . '_value_history#'] = '';

			if ($cmd->getIsVisible() == 1) {
				$replace['#' . $logicalId . '_hidden#'] = '';
			} else {
				$replace['#' . $logicalId . '_hidden#'] = 'hidden';
			}
			if ($cmd->getDisplay('showNameOn' . $_version, 1) == 0) {
				$replace['#' . $logicalId . '_hide_name#'] = 'hidden';
			} else {
				$replace['#' . $logicalId . '_hide_name#'] = '';
			}

			if ($cmd->getDisplay('showIconAndName' . $_version, 0) == 1) {
				$replace['#' . $logicalId . '_name_display#'] = $cmd->getDisplay('icon') . ' ' . $cmd->getName();
			} else {
				$replace['#' . $logicalId . '_name_display#'] = ($cmd->getDisplay('icon') != '') ? $cmd->getDisplay('icon') : $cmd->getName();
			}

			if ($cmd->getType() == 'info') {
				$replace['#' . $logicalId . '_state#'] = $cmd->execCmd();

				if ($cmd->getSubType() == 'binary' && $cmd->getDisplay('invertBinary') == 1) {
					$replace['#' . $logicalId . '_state#'] = ($replace['#' . $logicalId . '_state#'] == 1) ? 0 : 1;
				} else if ($cmd->getSubType() == 'numeric' && trim($replace['#' . $logicalId . '_state#']) === '') {
					$replace['#' . $logicalId . '_state#'] = 0;
				}
				if ($cmd->getSubType() == 'numeric' && trim($replace['#' . $logicalId . '_unite#']) != '') {
					if ($cmd->getConfiguration('historizeRound') !== '' && is_numeric($cmd->getConfiguration('historizeRound')) && $cmd->getConfiguration('historizeRound') >= 0) {
						$round = $cmd->getConfiguration('historizeRound');
					} else {
						$round = 99;
					}
					$valueInfo = $cmd->autoValueArray($replace['#' . $logicalId . '_state#'], $round, $replace['#' . $logicalId . '_unite#']);
					$replace['#' . $logicalId . '_state#'] = $valueInfo[0];
					$replace['#' . $logicalId . '_unite#'] = $valueInfo[1];
				}
				if (method_exists($cmd, 'formatValueWidget')) {
					$replace['#' . $logicalId . '_state#'] = $cmd->formatValueWidget($replace['#' . $logicalId . '_state#']);
				}

				$replace['#' . $logicalId . '_state#'] = str_replace(array("\'", "'", "\n"), array("'", "\'", '<br/>'), $replace['#' . $logicalId . '_state#']);
				$replace['#' . $logicalId . '_collectDate#'] = $cmd->getCollectDate();
				$replace['#' . $logicalId . '_valueDate#'] = $cmd->getValueDate();
				$replace['#' . $logicalId . '_alertLevel#'] = $cmd->getCache('alertLevel', 'none');
				if ($cmd->getIsHistorized() == 1) {
					$replace['#' . $logicalId . '_history#'] = 'history cursor';
					if (config::byKey('displayStatsWidget') == 1 && strpos($template, '#' . $logicalId . '_hide_history#') !== false && $cmd->getDisplay('showStatsOn' . $_version, 1) == 1) {
						$startHist = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' -' . config::byKey('historyCalculPeriod') . ' hour'));
						$replace['#' . $logicalId . '_hide_history#'] = '';
						$historyStatistique = $cmd->getStatistique($startHist, date('Y-m-d H:i:s'));
						if ($historyStatistique['avg'] == 0 && $historyStatistique['min'] == 0 && $historyStatistique['max'] == 0) {
							$replace['#' . $logicalId . '_averageHistoryValue#'] = round($replace['#' . $logicalId .'_state#'], 1);
							$replace['#' . $logicalId . '_minHistoryValue#'] = round($replace['#' . $logicalId .'_state#'], 1);
							$replace['#' . $logicalId . '_maxHistoryValue#'] = round($replace['#' . $logicalId .'_state#'], 1);
						} else {
							$replace['#' . $logicalId . '_averageHistoryValue#'] = round($historyStatistique['avg'], 1);
							$replace['#' . $logicalId . '_minHistoryValue#'] = round($historyStatistique['min'], 1);
							$replace['#' . $logicalId . '_maxHistoryValue#'] = round($historyStatistique['max'], 1);
						}
						$startHist = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' -' . config::byKey('historyCalculTendance') . ' hour'));
						$tendance = $cmd->getTendance($startHist, date('Y-m-d H:i:s'));
						if ($tendance > config::byKey('historyCalculTendanceThresholddMax')) {
							$replace['#' . $logicalId . '_tendance#'] = 'fas fa-arrow-up';
						} else if ($tendance < config::byKey('historyCalculTendanceThresholddMin')) {
							$replace['#' . $logicalId . '_tendance#'] = 'fas fa-arrow-down';
						} else {
							$replace['#' . $logicalId . '_tendance#'] = 'fas fa-minus';
						}
					}
				}
			}
		}
		$template = template_replace($replace, $template);
		$template = translate::exec($template, 'plugings,EaseeCharger/core/template/' . $version . '/EaseeCharger.html');
		return $this->postToHtml($_version, $template);
	}

	/*
	 * Config, avant première sauvegarde, des valeurs par défaut
	 */
	public function preInsert() {
		$this->setConfiguration('widget_perso', 1);
	}

	/*
	 * Préparation avant sauvegarde
	 */
	public function preSave() {
		$this->_wasEnable = 0;
		$oldCharger = EaseeCharger::byId($this->getId());
		if (is_object($oldCharger) && $oldCharger->getIsEnable() != 0) {
			$this->_wasEnable = 1;
			$this->_oldLogicalId = $oldCharger->getLogicalId();
			$this->_oldHumanName = $oldCharger->getHumanName();
			$this->_oldAccountId = $oldCharger->getAccountId();
		}
	}

	/*
	 * Vérifications avant enregistrement
	 */
	public function preUpdate() {
		if ($this->getAccountId() == '') {
			throw new Exception (__("Un compte doit être sélectioné",__FILE__));
		}
	}

	/*
	 * Ajout des cmds après création du chargeur
	 */
	public function postInsert() {
		$this->createOrUpdateCmds();
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
			'serial' => $this->getLogicalId(),
			'name' => $this->getHumanName(),
			'accountId' => $this->getAccountId()
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

	public function createOrUpdateCmds() {
		function purgeDash ($array) {
			$return = array();
			foreach($array as $key => $value) {
				if (is_array($value)) {
					$return[$key] = purgeDash($value);
					continue;
				}
				if (strpos($value,'#') === false) {
					$return[$key] = $value;
				}
			}
			return $return;
		}

		$cmdsFile = realpath(__DIR__ . '/../config/cmds.json');
		$cmds = json_decode(translate::exec(file_get_contents($cmdsFile),$cmdsFile),true);
		foreach($cmds as $cmdConfig) {
			$purgedCmd = purgeDash($cmdConfig);
			$cmd = $this->getCmd(null,$purgedCmd['logicalId']);
			if (! is_object($cmd)) {
				log::add("EaseeCharger","info",sprintf(__('Création de la commande %s',__FILE__),$purgedCmd['logicalId']));
				$cmd = new EaseeChargerCmd();
				$cmd->setEqLogic_id($this->getId());
				utils::a2o($cmd,$purgedCmd);
			} else {
				utils::a2o($cmd,$purgedCmd);
				if ($cmd->getChanged()) {
					log::add("EaseeCharger","info", sprintf(__('La commande %s a été modifiée',__FILE__),$purgedCmd['logicalId']));
					$cmd->save();
				}
			}
		}
		foreach($cmds as $cmdConfig) {
			$json = json_encode($cmdConfig);
			preg_match_all("/#(\S*?)#/", $json, $matches);
			$replace = array();
			foreach ($matches[1] as $match) {
				if (isset($replace[$match])) {
					continue;
				}
				$cmd = $this->getCmd(null,$match);
				if (is_object($cmd)) {
					$replace['#' . $match . '#'] = '#' . $cmd->getId() . '#';
				}
			}
			$resolvedCmd = json_decode(str_replace(array_keys($replace), $replace, $json),true);
			$cmd = $this->getCmd(null,$resolvedCmd['logicalId']);
			if (! is_object($cmd)) {
				$cmd = new EaseeChargerCmd();
				$cmd->setEqLogic_id($this->getId());
			}
			utils::a2o($cmd,$resolvedCmd);
			if ($cmd->getChanged()) {
				log::add("EaseeCharger","info", sprintf(__('La commande %s a été modifiée',__FILE__),$purgedCmd['logicalId']));
				$cmd->save();
			}
		}
	}

	public function getAccount() {
		return EaseeAccount::byId($this->getaccountId());
	}

	//=======================================================================
	//=========================== GETTEUR SETTEUR ===========================
	//=======================================================================

	public function getAccountId() {
		return $this->getConfiguration('accountId');
	}

	public function setAccountId($_accountId) {
		$this->setConfiguration('accountId',$_accountId);
	}

}

class EaseeChargerCmd extends cmd {

	public static $_widgetPossibility = array ("custom" => true);

	public function dontRemoveCmd() {
		if ($this->getLogicalId() == 'refresh') {
			return true;
		}
		return false;
	}

	public function toHtml($_version = 'dashboard', $options = '') {
		if ($this->getTemplate($_version) == 'EaseeCharger::phase') {
			if ($options == '') {
				$options = array();
			}
			$options = is_json($options,$options);
			if ($this->getConfiguration('phaseId') != '') {
				$options['phaseId'] = $this->getConfiguration('phaseId');
			}
			if ($this->getConfiguration('widgetTitle') != '') {
				$options['widgetTitle'] = $this->getConfiguration('widgetTitle');
			}
			return parent::toHtml($_version, $options);
		}
		if ($this->getLogicalId() == 'WIFI') {
			if ($this->getEqLogic()->getConfiguration('widget_perso',1) == '1') {
			} else {
				return parent::toHtml($_version, $options);
			}
		}
		return parent::toHtml($_version, $options);
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

}

require_once __DIR__  . '/EaseeAccount.class.php';
