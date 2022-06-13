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

	public static $_fdQueue = null;
	public static $_fdRun = null;

    //========================================================================
    //========================== METHODES STATIQUES ==========================
    //========================================================================

	/*
	 * Surcharge de la function "byType" pour permettre de chercher les
	 * eqLogics du classe et des classes héritières.
	 *
	 * "byType('EaseeCharger_account_%')" par exemple pour avoir tous les
	 * accounts quelque soit le modèle
	 */
	public static function byType($_eqType_name, $_onlyEnable = false) {
		if (strpos($_eqType_name, '%') === false) {
			return parent::byType($_eqType_name, $_onlyEnable);
		}
		$values = array(
			'eqType_name' => $_eqType_name,
		);
		$sql =  'SELECT DISTINCT eqType_name';
		$sql .= '   FROM eqLogic';
		$sql .= '   WHERE eqType_name like :eqType_name';
		$eqTypes = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
		$eqLogics = array ();
		foreach ($eqTypes as $eqType) {
			 $eqLogics = array_merge($eqLogics,parent::byType($eqType['eqType_name'], $_onlyEnable));
		}
		return $eqLogics;
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

	/*     * ************************ events ********************************* */

	public static function getFileDescriptorQueueLock() {
		if (self::$_fdQueue == null) {
			self::$_fdQueue = fopen(jeedom::getTmpFolder() . '/EaseeCharger_queue_lock', 'w');
			@chmod(jeedom::getTmpFolder() . '/EaseeCharger_queue_lock', 0777);
		}
		return self::$_fdQueue;
	}

	public static function getFileDescriptorRunLock() {
		if (self::$_fdRun == null) {
			self::$_fdRun = fopen(jeedom::getTmpFolder() . '/EaseeCharger_run_lock', 'w');
			@chmod(jeedom::getTmpFolder() . '/EaseeCharger_run_lock', 0777);
		}
		return self::$_fdRun;
	}

	private static function nextEvent() {
		$fdQueue = self::getFileDescriptorQueueLock();
		if (flock($fdQueue, LOCK_EX)) {
			$cache = cache::byKey('EaseeCharger_queue');
			$values = json_decode($cache->getValue('[]'),true);
			$value = array_shift($values);
			$cache->setValue(json_encode($values));
			$cache->save();
			flock($fdQueue, LOCK_UN);

		} else {
			log::add("EaseeCharger","error",__("Erreur lors de l'obtention du lock pour le cache des events",__FILE__));
			$value = null;
		}
		return $value;
	}

	public static function EaseeChargerEventHandler($_options) {
		$fdQueue = self::getFileDescriptorQueueLock();
		$_options = is_json($_options,$_options);
		if (! is_array($_options)) {
			log::add("EaseeCharger","error",__("Récection d'un event ne pouvant pas être traité",__FILE__));
			return;
		}
		$_options['_time'] = time();
		if (flock($fdQueue, LOCK_EX)) {

			/** Enregistrement du nouvel event **/
			$cache = cache::byKey('EaseeCharger_queue');
			$value = json_decode($cache->getValue('[]'),true);
			$value[] = is_json($_options,$_options);
			$cache->setValue(json_encode($value));
			$cache->save();

			/** Check si un autre handler est en cours d'exécution **/
			$fdRun = self::getFileDescriptorRunLock();
			if (flock($fdRun, LOCK_EX | LOCK_NB)) {
				flock($fdQueue, LOCK_UN);
				while ($event = self::nextEvent()){
					log::add("EaseeCharger","debug","Listener event: " . print_r($event,true));
					$delaiMax = 60;
					if (time() - $event['_time'] > $delaiMax) {
						log::add("EaseeCharger","error",sprintf(__("Event de plus de %d secondes! Il ne sera pas traité",__FILE__),$delaiMax));
					}
					$cmd = cmd::byId($event['event_id']);
					if (!is_object($cmd)){
						throw new Exception (sprintf(__("EaseeChargerEventHandler: Commande %s introuvable!",__FILE__),$event['event_id']));
					}
					if ($cmd->getEqlogic()->getEqType_name() == 'EaseeCharger_vehicle') {
						$vehicle = $cmd->getEqlogic();
						$vehicle->searchConnectedCharger();
					} elseif ($cmd->getEqlogic()->getEqType_name() == 'EaseeCharger_charger') {
						$charger = $cmd->getEqlogic();
						$charger->searchConnectedVehicle();
					}
				}
			} else {
				flock($fdQueue, LOCK_UN);
			}
		} else {
			log::add("EaseeCharger","error",__("Erreur lors de l'obtention du lock",__FILE__));
		}
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

	public static function vehiclePlugged($options){
		log::add("EaseeCharger","info","vehiclePlugged: " . print_r($options,true));
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
require_once __DIR__  . '/EaseeCharger_account.class.php';
require_once __DIR__  . '/EaseeCharger_charger.class.php';
require_once __DIR__  . '/EaseeCharger_vehicle.class.php';
