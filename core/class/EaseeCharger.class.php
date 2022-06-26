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

	/*     * ************************ engine ********************************* */

	/* L'équipement "engine est créé lors de la promière activation du plugin et
	 * supprimé lors de la désinstallation du plugin. C'est le "moteur" du plugin.
	 */
	public static function createEngine() {
		$engine = self::getEngine();
		if (is_object($engine)) {
			return;
		}
		try {
			$engine = new self();
			$engine->setEqType_name("EaseeCharger");
			$engine->setName('engine');
			$engine->setLogicalId('engine');
			$engine->setIsEnable(1);
			$engine->save();
		} catch (Exception $e) {
			log::add("EaseeCharger","error","CreateEngine: " . $e->getMessage());
		}
	}

	public static function getEngine() {
		return self::byLogicalId('engine','EaseeCharger');
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
//		EaseeCharger_xaccount::_cron();
//	}
//
//	public static function cron5() {
//		EaseeCharger_xaccount::_cron5();
//	}
//
//	public static function cron10() {
//		EaseeCharger_xaccount::_cron10();
//	}
//
//	public static function cron15() {
//		EaseeCharger_xaccount::_cron15();
//	}
//
//	public static function cron30() {
//		EaseeCharger_xaccount::_cron30();
//	}
//
//	public static function cronHourly() {
//		EaseeCharger_xaccount::_cronHourly();
//	}
//
//	public static function cronDaily() {
//		EaseeCharger_xaccount::_cron15();
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
			$account = EaseeCharger_account::byName($this->getConfiguration('accountName'));
			if ($account->getIsEnable() != 1) {
				throw new Exception (__("Le chargeur ne peut pas être activé si l'account associé ne l'est pas!",__FILE__));
			}
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

	/*
	 * Surcharge de getLinkToConfiguration() pour forcer les options "m" et "p"
	 * à "EaseeCharger" même pour les classes héritiaires.
	 */
	public function getLinkToConfiguration() {
		if (isset($_SESSION['user']) && is_object($_SESSION['user']) && !isConnect('admin')) {
			return '#';
		}
		return 'index.php?v=d&p=EaseeCharger&m=EaseeCharger&id=' . $this->getId();
	}

	/*
	 * La suppression de l'équipement "engine" se fait uniquement lors de
	 * la désinstallation du plugin. On en profite pour supprimer les équipement
	 * de classes héritières car le core Jeedom ne le fait pas.
	 */
	public function preRemove() {
		if ($this->getLogicalId() != 'engine') {
			return true;
		}
		$eqLogics = EaseeCharger::byType("EaseeCharger_%");
		if (is_array($eqLogics)) {
			foreach ($eqLogics as $eqLogic) {
				try {
					$eqLogic->remove();
				} catch (Exception $e) {
				} catch (Error $e) {
				}
			}
		}
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

}
class EaseeChargerCmd extends cmd {
	public function getValueTime() {
		return DateTime::createFromFormat("Y-m-d H:i:s", $this->getValueDate())->getTimeStamp();
	}

	public function getCollectTime() {
		return DateTime::createFromFormat("Y-m-d H:i:s", $this->getCollectDate())->getTimeStamp();
	}
}

require_once __DIR__  . '/model.class.php';
require_once __DIR__  . '/EaseeCharger_xaccount.class.php';
require_once __DIR__  . '/EaseeCharger_account.class.php';
require_once __DIR__  . '/EaseeCharger_charger.class.php';
