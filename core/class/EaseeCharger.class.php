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

	public static function byAccount($accountName) {
		return self::byTypeAndSearchConfiguration(__CLASS__,'"accountName":"'.$accountName.'"');
	}

	public static function bySerial($serial) {
		return self::byTypeAndSearchConfiguration(__CLASS__,'"serial":"'.$serial.'"');
	}
		
	/*     * ********************** Gestion du daemon ************************* */

	/*
	 * Info sur le daemon
	 */
	public static function deamon_info() {
		return self::daemon_info();
	}

	public static function daemon_info() {
		$return = array();
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
	 * Lancement de daemon
	 */
	public static function deamon_start() {
		return self::daemon_start();
	}

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
		log::add(__CLASS__, "info", $cmd . ' >> ' . log::getPathToLog('EaseeCharger_daemon') . ' 2>&1 &');
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
	 * Arret du daemon
	 */
	public static function deamon_stop() {
		return self::daemon_stop();
	}

	public static function daemon_stop() {
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

	/*     * ************************ Les widgets **************************** */

	/*
	 * template pour les widget
	 */
	public static function templateWidget() {
		$return = array(
			'action' => array(
				'other' => array(
					'cable_lock' => array(
						'template' => 'cable_lock',
						'replace' => array(
							'#_icon_on_#' => '<i class=\'icon_green icon jeedom-lock-ferme\'><i>',
							'#_icon_off_#' => '<i class=\'icon_orange icon jeedom-lock-ouvert\'><i>'
						)
					)
				)
			),
			'info' => array(
				'numeric' => array(
					'etat' => array(
						'template' => 'etat',
						'replace' => array(
							'#texte_1#' =>  '{{Débranché}}',
							'#texte_2#' =>  '{{En attente}}',
							'#texte_3#' =>  '{{Recharge}}',
							'#texte_4#' =>  '{{Terminé}}',
							'#texte_5#' =>  '{{Erreur}}',
							'#texte_6#' =>  '{{Prêt}}'
						)
					)
				)
			)
		);
		return $return;
	}

	/*     * ********************* Les utilitaires ************************* */

	public static function distance($lat1, $lng1, $lat2, $lng2 ) {
		$earth_radius = 6378137;   // Terre = sphère de 6378km de rayon
		$rlo1 = deg2rad($lng1);
		$rla1 = deg2rad($lat1);
		$rlo2 = deg2rad($lng2);
		$rla2 = deg2rad($lat2);
		$dlo = ($rlo2 - $rlo1) / 2;
		$dla = ($rla2 - $rla1) / 2;
		$a = (sin($dla) * sin($dla)) + cos($rla1) * cos($rla2) * (sin($dlo) * sin($dlo));
		$d = 2 * atan2(sqrt($a), sqrt(1 - $a));
		return round($earth_radius * $d);
	}

	/*     * ************************ Les crons **************************** */

//	public static function cron() {
//		EaseeCharger_account::_cron();
//	}
//
//	public static function cron5() {
//		EaseeCharger_account::_cron5();
//	}
//
//	public static function cron10() {
//		EaseeCharger_account::_cron10();
//	}
//
//	public static function cron15() {
//		EaseeCharger_account::_cron15();
//	}
//
//	public static function cron30() {
//		EaseeCharger_account::_cron30();
//	}
//
//	public static function cronHourly() {
//		EaseeCharger_account::_cronHourly();
//	}
//
//	public static function cronDaily() {
//		EaseeCharger_account::_cron15();
//	}

    //========================================================================
    //========================= METHODES D'INSTANCE ==========================
    //========================================================================

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
	 * Path de l'image du chargeur
	 */
	public function getPathImg() {
		$image = $this->getConfiguration('image');
		if ($image == '') {
			$image = "/plugins/EaseeCharger/plugin_info/EaseeCharger_icon.png";
		}
		return $image;
	}

	public function createCmds() {
		$cfgFile = realpath (__DIR__ . '/../config/cmd.config.ini');
		log::add("EaseeCharger","debug",sprintf(__("Lecture du fichier %s ...",__FILE__),$cfgFile));
		$cmdConfigs = parse_ini_file($cfgFile,true,INI_SCANNER_RAW);
		foreach ($cmdConfigs as $logicalId => $config) {
			$cmd = cmd::byEqLogicIdAndLogicalId($this->getId(),$logicalId);
			if (is_object($cmd)) {
				log::add("EaseeCharger","debug",sprintf(__("%s existe déjà",__FILE__),$logicalId));
				continue;
			}
			log::add("EaseeCharger","info",sprintf(__("Création de la commande %s...",__FILE__),$logicalId));

			$cmd = new EaseeChargerCMD();

			// displayName
			// -----------
			if (array_key_exists('displayName',$config)) {
				$cmd->setDisplay('showNameOndashboard',$config['displayName']);
			}

			// display::graphStep
			// ------------------
			if (array_key_exists('display::graphStep',$config)) {
				$cmd->setDisplay('graphStep',$config['display::graphStep']);
			}

			// eqLogic_id
			// ----------
			$cmd->setEqLogic_id($this->getId());

			// logicalId
			// ---------
			$cmd->setLogicalId($logicalId);

			// name
			// ----
			if (array_key_exists('name',$config)) {
				$cmd->setName($config['name']);
			} else {
				$cmd->setName($logicalId);
			}

			// order
			// -----
			if (array_key_exists('order',$config)) {
				$cmd->setOrder($config['order']);
			}

			// rounding
			// --------
			if (array_key_exists('rounding',$config)) {
				$cmd->setConfiguration('historizeRound',$config['rounding']);
			}

			// subType
			// -------
			if (array_key_exists('subType',$config)) {
				$cmd->setSubType($config['subType']);
			} else {
				log::add("EaseeCharger","error",sprintf(__("Le subType de la commande %s n'est pas défini",__FILE__),$logicalId));
			}

			// template
			// --------
			if (array_key_exists('template',$config)) {
				$cmd->setTemplate('dashboard',$config['template']);
				$cmd->setTemplate('mobile',$config['template']);
			}

			// type
			// ----
			if (array_key_exists('type',$config)) {
				$cmd->setType($config['type']);
			} else {
				log::add("EaseeCharger","error",sprintf(__("Le type de la commande %s n'est pas défini",__FILE__),$logicalId));
			}

			// unite
			// -----
			if (array_key_exists('unite',$config)) {
				$cmd->setUnite($config['unite']);
			}

			// visible
			// -------
			if (array_key_exists('visible',$config)) {
				$cmd->setIsVisible($config['visible']);
			}

			$cmd->save();
		}
		foreach ($cmdConfigs as $logicalId => $config) {
			$cmd = $this->getCmd(null,$logicalId);
			$needSave = false;

			if (array_key_exists('calcul',$config)) {
				$calcul = $config['calcul'];
				preg_match_all('/#(.*?)#/',$calcul,$matches);
				foreach ($matches[1] as $cible) {
					$cmdCible = $this->getCmd(null, $cible);
					if (is_object($cmdCible)) {
						$calcul = str_replace('#' . $cible . '#','#' . $cmdCible->getId() . '#', $calcul);
					}
				}
				$cmd->setConfiguration('calcul', $calcul);
				$needSave = true;
			}

			if (array_key_exists('value',$config)) {
				$cmdValue = $this->getCmd(null, $config['value']);
				if (is_object($cmdValue)) {
					if ($cmd->getType() == 'info') {
						$value = '#' . $cmdValue->getId() . '#';
					} else {
						$value = $cmdValue->getId();
					}
					$cmd->setValue($value);
				}
				$needSave = true;
			}
			if ($needSave) {
				$cmd->save();
			}
		}
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
	public function execute($_options = array()) {
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
require_once __DIR__  . '/EaseeCharger_charger.class.php';
