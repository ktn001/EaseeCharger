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

class EaseeCharger_charger extends EaseeCharger {
    /*     * ************************* Attributs ****************************** */

    /*     * *********************** Methode static *************************** */

	public static function byAccountId($accountId) {
		return self::byTypeAndSearchConfiguration(__CLASS__,'"accountId":"'.$accountId.'"');
	}

	public static function byModelAndIdentifiant($modelId, $identifiant) {
		$identKey = model::getIdentifiantCharger($modelId);
		$searchConf = sprintf('"%s":"%s"',$identKey,$identifiant);
		$chargers = array();
		foreach (self::byTypeAndSearchConfiguration(__CLASS__,$searchConf) as $charger){
			if ($charger->getConfiguration('modelId') == $modelId){
				$chargers[] = $charger;
			}
		}
		return $chargers;

	}

    /*     * *********************Méthodes d'instance************************* */

    // Création/mise à jour des commande prédéfinies
	public function updateCmds($options = array()) {
		$createOnly = false;
		if (array_key_exists('createOnly', $options)) {
			$createOnly = $options['createOnly'];
		}
		$updateOnly = false;
		if (array_key_exists('updateOnly', $options)) {
			$updateOnly = $options['updateOnly'];
		}
		$ids = array();
		log::add("EaseeCharger","debug",sprintf(__("%s: (re)création des commandes",__FILE__),$this->getHumanName()));
		$model = $this->getModel();
		foreach ($model->commands() as $logicalId => $config) {
			$cmd = $this->getCmd(null,$logicalId);
			if (!is_object($cmd)){
				if ($updateOnly) {
					continue;
				}
				log::add("EaseeCharger","debug","  " . sprintf(__("Création de la commande %s",__FILE__), $logicalId));
				$cmd = new EaseeCharger_chargerCMD();
				$cmd->setEqLogic_id($this->getId());
				$cmd->setLogicalId($logicalId);
				if ($createOnly and array_key_exists('order',$config)) {
					foreach (cmd::byEqLogicId($this->getId()) as $otherCmd) {
						if ($otherCmd->getOrder() >= $config['order']) {
							$otherCmd->setOrder($otherCmd->getOrder()+1);
							$otherCmd->save();
						}
					}
					if ($cmd->getOrder() != $config['order']){
						log::add("EaseeCharger","debug","  " . sprintf(__("%s: Mise à jour de 'order'",__FILE__), $logicalId));
						$cmd->setOrder($config['order']);
					}
				}
			} elseif ($createOnly) {
				continue;
			}

			if ($cmd->getConfiguration('destination') != $config['destination']) {
				log::add("EaseeCharger","debug","  " . sprintf(__("%s: Mise à jour de 'destination'",__FILE__), $logicalId));
				$cmd->setConfiguration('destination',$config['destination']);
			}
			if (array_key_exists('display::graphStep', $config)) {
				if ($cmd->getDisplay('graphStep') != $config['display::graphStep']) {
					log::add("EaseeCharger","debug","  " . sprintf(__("%s: Mise à jour de 'display::graphStep'",__FILE__), $logicalId));
					$cmd->setDisplay('graphStep', $config['display::graphStep']);
				}
			}
			if (array_key_exists('displayName', $config)) {
				if ($cmd->getDisplay('showNameOndashboard') !=  $config['displayName']){
					log::add("EaseeCharger","debug","  " . sprintf(__("%s: Mise à jour de 'display::NameOndashboard'",__FILE__), $logicalId));
					$cmd->setDisplay('showNameOndashboard', $config['displayName']);
				}
				if ($cmd->getDisplay('showNameOnmobile') !=  $config['displayName']){
					log::add("EaseeCharger","debug","  " . sprintf(__("%s: Mise à jour de 'display::NameOnmobile'",__FILE__), $logicalId));
					$cmd->setDisplay('showNameOndashboard', $config['displayName']);
				}
			}
			if ($cmd->getName() != $config['name']){
				log::add("EaseeCharger","debug","  " . sprintf(__("%s: Mise à jour de 'name'",__FILE__), $logicalId));
				$cmd->setName($config['name']);
			}
			if ($cmd->getOrder() != $config['order']){
				log::add("EaseeCharger","debug","  " . sprintf(__("%s: Mise à jour de 'order'",__FILE__), $logicalId));
				$cmd->setOrder($config['order']);
			}
			if ($cmd->getConfiguration('required') != $config['required']) {
				log::add("EaseeCharger","debug","  " . sprintf(__("%s: Mise à jour de 'required'",__FILE__), $logicalId));
				$cmd->setConfiguration('required',$config['required']);
			}
			if (array_key_exists('rounding', $config)) {
				if ($cmd->getConfiguration('historizeRound') != $config['rounding']) {
					log::add("EaseeCharger","debug","  " . sprintf(__("%s: Mise à jour de 'roundig'",__FILE__), $logicalId));
					$cmd->setConfiguration('historizeRound', $config['rounding']);
				}
			}
			if ($cmd->getConfiguration('source') != $config['source']) {
				log::add("EaseeCharger","debug","  " . sprintf(__("%s: Mise à jour de 'source'",__FILE__), $logicalId));
				$cmd->setConfiguration('source',$config['source']);
			}
			if ($cmd->getSubType() != $config['subType']) {
				log::add("EaseeCharger","debug","  " . sprintf(__("%s: Mise à jour de 'subType'",__FILE__), $logicalId));
				$cmd->setSubType($config['subType']);
			}
			if (array_key_exists('template', $config)) {
				if ($cmd->getTemplate('dashboard') != $config['template']) {
					log::add("EaseeCharger","debug","  " . sprintf(__("%s: Mise à jour de 'template::dashboard'",__FILE__), $logicalId));
					$cmd->setTemplate('dashboard',$config['template']);
				}
				if ($cmd->getTemplate('mobile') != $config['template']) {
					log::add("EaseeCharger","debug","  " . sprintf(__("%s: Mise à jour de 'template::mobile'",__FILE__), $logicalId));
					$cmd->setTemplate('mobile',$config['template']);
				}
			}
			if ($cmd->getType() != $config['type']) {
				log::add("EaseeCharger","debug","  " . sprintf(__("%s: Mise à jour de 'type'",__FILE__), $logicalId));
				$cmd->setType($config['type']);
			}
			if (array_key_exists('unite', $config)) {
				if ($cmd->getUnite() != $config['unite']) {
					log::add("EaseeCharger","debug","  " . sprintf(__("%s: Mise à jour de 'unite'",__FILE__), $logicalId));
					$cmd->setUnite($config['unite']);
				}
			}
			if (array_key_exists('visible', $config)) {
				if ($cmd->getIsVisible() != $config['visible']) {
					log::add("EaseeCharger","debug","  " . sprintf(__("%s: Mise à jour de 'visible'",__FILE__), $logicalId));
					$cmd->setIsVisible($config['visible']);
				}
			}

			if ($cmd->getType() == 'action' and $cmd->getConfiguration('destination') == 'cmd'){
				$cmd->setConfiguration('destId','-');
			}
			$cmd->save();
		}
		foreach ($model->commands() as $logicalId => $config) {
			$cmd = $this->getCmd(null,$logicalId);
			$needSave = false;
			if (array_key_exists('calcul',$config)){
				$calcul = $config['calcul'];
				if (!is_object($cmd)){
					log::add("EaseeCharger","error",(sprintf(__("Commande avec logicalIs=%s introuvable",__FILE__),$logicalId)));
					continue;
				}
				preg_match_all('/#(.+?)#/',$calcul,$matches);
				foreach ($matches[1] as $logicalId) {
					$id = $this->getCmd(null, $logicalId)->getId();
					$calcul = str_replace('#' . $logicalId . '#', '#' . $id . '#', $calcul);
				}
				if ($cmd->getConfiguration('calcul') !=  $calcul) {
					log::add("EaseeCharger","debug","  " . sprintf(__("%s: Mise à jour de 'calcul'",__FILE__), $logicalId));
					$cmd->setConfiguration('calcul', $calcul);
					$needSave = true;
				}
			}
			if (array_key_exists('value',$config)){
				if (!is_object($cmd)){
					log::add("EaseeCharger","error",(sprintf(__("Commande avec logicalId = %s introuvable",__FILE__),$logicalId)));
					continue;
				}
				$cmdValue = $this->getCmd(null, $config['value']);
				if (! $cmdValue) {
					log::add("EaseeCharger","error",sprintf(__("La commande '%s' pour la valeur de '%s' est introuvable",__FILE__),$config['value'],$cmd->getLogicalId()));
				} else {
					if ($cmd->getType() == 'info') {
						$value = '#' . $cmdValue->getId() . '#';
					} else {
						$value = $cmdValue->getId();
					}
					if ($cmd->getValue() != $value) {
						log::add("EaseeCharger","debug","  " . sprintf(__("%s: Mise à jour de 'value' (%s)",__FILE__), $logicalId,$value));
						$cmd->setValue($value);
						$needSave = true;
					}
				}
			}
			if ($needSave) {
				$cmd->save();
			}
		}
	}

    // Fonction exécutée automatiquement avant la sauvegarde de l'équipement
	public function preSave() {
		if ($this->getIsEnable()) {
			if ($this->getAccountId() == '') {
				throw new Exception (__("Le compte n'est pas défini",__FILE__));
			}
			if ($this->getConfiguration('latitude') == '' or $this->getConfiguration('longitude') == '') {
				throw new Exception (__('Les coordonnées GPS ne sont pas définies!',__FILE__));
			}
		}
		$accountId = $this->getAccountId();
		if ($accountId != '') {
			$account = EaseeCharger_account::byId($accountId);
			if (! is_a($account, "EaseeCharger_account")) {
				throw new Exception (sprintf(__("L'account %s est introuvable!",__FILE__), $accountId));
			}
		}

	}

    // Fonction exécutée automatiquement avant la création de l'équipement
	public function preInsert() {
		$this->setConfiguration('image',$this->getModel()->images('charger')[0]);
	}

    // Fonction exécutée automatiquement après la création de l'équipement
	public function postInsert() {
		$this->updateCmds(false);
	}

    // Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement
	public function postSave() {
		$this->checkListeners();
		if ($this->getIsEnable()) {
			$this->startDaemonThread();
		} else {
			$this->stopDaemonThread();
		}
	}

    // Création des listeners
	public function checkListeners() {
		if ($this->getIsEnable() == 0){
			return;
		}
		log::add("EaseeCharger","info",__("vérification des listeners pour le chargeur ",__FILE__). $this->getHumanName());
		$logicalIds = array(
			'connected' => 'EaseeChargerEventHandler',
		);
		foreach ($logicalIds as $logicalId => $function) {
			$listener = listener::byClassAndFunction('EaseeCharger', $function);
			if (!is_object($listener)) {
				$listener = new listener();
				$listener->setClass('EaseeCharger');
				$listener->setFunction($function);
			}
			$changed = false;
			$cmds = cmd::byEqLogicIdAndLogicalId($this->getId(),$logicalId,true);
			if (! is_array($cmds)){
				continue;
			}
			foreach ($cmds as $cmd) {
				$listener->addEvent($cmd->getId());
				$changed = true;
			}
		}
		if ($changed){
			$listener->save();
		}
	}

    // Fonction exécutée automatiquement après la sauvegarde de l'eqLogid ET des commandes si sauvegarde lancée via un AJAX
	public function postAjax() {
		if ($this->getAccountId() == '') {
			return;
		}
		$cmd_refresh = $this->getCmd(null,'refresh');
		if (!is_object($cmd_refresh)) {
			return;
		}
		$cmd_refresh->execute();
		return;
	}

	public function getPathImg() {
		$image = $this->getConfiguration('image');
		if ($image == '') {
			$image = "/plugins/EaseeCharger/plugin_info/EaseeCharger_icon.png";
		}
		return $image;
	}

	public function getAccount() {
		return EaseeCharger_account::byId($this->getAccountId());
	}

	public function getIdentifiant() {
		$modelId = $this->getConfiguration('modelId');
		$configName = model::getIdentifiantCharger($modelId);
		return $this->getConfiguration($configName);
	}

	public function startDaemonThread() {
		if (! $this->getIsEnable()) {
			return;
		}
		log::add("EaseeCharger","info","Charger " . $this->getHumanName() . ": " . __("Lancement de thread",__FILE__));
		$message = array(
			'cmd' => 'start_charger_thread',
			'chargerId' => $this->id,
			'identifiant' => $this->getIdentifiant()
		);
		$this->getAccount()->send2Daemon($message);
	}

	public function stopDaemonThread() {
		$message = array(
			'cmd' => 'stop_charger_thread',
			'chargerId' => $this->id,
			'identifiant' => $this->getIdentifiant()
		);
		if ($this->getAccountId()) {
			EaseeCharger_account::byId($this->getAccountId())->send2Daemon($message);
		}
	}

	public function isConnected() {
		$connectedCmd = $this->getCmd('info','connected');
		if (! is_object($connectedCmd)) {
			return null;
		}
		$connected = $connectedCmd->execCmd();
		if ($connected == 1) {
			return true;
		}
		return false;
	}

	public function getConnectionTime() {
		$connectedCmd = $this->getCmd('info','connected');
		if ($connectedCmd->execCmd() != 1) {
			return 0;
		}
		return $connectedCmd->getValueTime();
	}

	public function distanceTo ($lat, $lgt) {
		$myLat = $this->getConfiguration('latitude');
		$myLgt = $this->getConfiguration('longitude');
		return EaseeCharger::distance($lat,$lgt,$myLat,$myLgt);
	}

	public function getVehicleId() {
		$vehicleCmd = $this->getCmd('info','vehicle');
		if (is_object($vehicleCmd)) {
			return $vehicleCmd->execCmd();
		}
		return 0;
	}

	public function getModel() {
		return model::byId($this->getConfiguration('modelId'));
	}

	public function getLatitude() {
		return $this->getConfiguration("latitude");
	}

	public function getLongitude() {
		return $this->getConfiguration("longitude");
	}

	public function searchConnectedVehicle() {
		if (! $this->isConnected()) {
			log::add("EaseeCharger","debug",sprintf(__("Déconnection du chargeur %s",__FILE__), $this->getHumanName()));
			$vehicleId = $this->getCmd('info','vehicle')->execCmd();
			$vehicle = EaseeCharger_vehicle::byId($vehicleId);
			if (is_object($vehicle)) {
				$vehicle->checkAndUpdateCmd('charger',0);
			}
			$this->checkAndUpdateCmd("vehicle",0);
			$vehicles = EaseeCharger_vehicle::byType("EaseeCharger_vehicle",true);
			foreach ($vehicles as $vehicle) {
				$vehicle->refresh();
			}
			return;
		}
		log::add("EaseeCharger","debug",sprintf(__("Recherche d'un véhicule pour %s",__FILE__),$this->getHumanName()));
		$connectionTime = $this->getConnectionTime();
		$maxPlugDelay = config::byKey("maxPlugDelay","EaseeCharger");
		$maxDistance = config::byKey("maxDistance","EaseeCharger");
		$latitude = $this->getLatitude();
		$longitude = $this->getLongitude();
		$vehicles = EaseeCharger_vehicle::byType("EaseeEaseeEaseeEaseeEaseeCehicle",true);
		$candidateVehicles = array();
		foreach ($vehicles as $vehicle) {
			log::add("EaseeCharger","debug","  " . $vehicle->getHumanName());
			$isConnected = $vehicle->isConnected();
			if ($isConnected === false) {
				log::add("EaseeCharger","debug","    " . sprintf(__("%s n'est pas connecté",__FILE__),$vehicle->getHumanName()));
				$vehicle->refresh();
				continue;
			}
			if ($isConnected === true) {
				if (abs($connectionTime - $vehicle->getConnectionTime()) > $maxPlugDelay) {
					log::add("EaseeCharger","debug","    " . sprintf(__("%s pas de connection récente",__FILE__),$vehicle->getHumanName()));
					$vehicle->refresh();
					continue;
				}
				$chargerId = $vehicle->getChargerId();
				if ($chargerId != '' and $chargerId != 0 and $chargerId != $this->getId()){
					$charger = EaseeCharger_vehicle::byId($chargerId);
					if (is_object($charger)){
						$chargerName = $charger->getHumanName();
					} else {
						$chargerName = $chargerId;
					}
					log::add("EaseeCharger","debug","    " . sprintf(__("Le véhicule %s est connecté au chargeur %s",__FILE__),$vehicle->getHumanName(),$chargerName));
					$vehicle->refresh();
					continue;
				}
			}
			if ($latitude != null and $longitude != null) {
				$distance = $vehicle->distanceTo($latitude, $longitude);
				if ($distance > $maxDistance) {
					log::add("EaseeCharger","debug","    " . sprintf(__("%s est à %s mètres de %s",__FILE__),$vehicle->getHumanName(),$distance,$this->getHumanName()));
					$vehicle->refresh();
					continue;
				}
			}
			$candidateVehicles[] = $vehicle;
		}
		if (count($candidateVehicles) == 0) {
			log::add("EaseeCharger","debug",__("Pas de chargeur trouvé!",__FILE__));
		} elseif (count($candidateVehicles) == 1) {
			$candidateVehicles[0]->checkAndUpdateCmd('charger',$this->getId());
			$this->checkAndUpdateCmd('vehicle',$candidateVehicles[0]->getId());
		} else {
			log::add("EaseeCharger","debug","  " . __("Trop de véhicules possibles:",__FILE__));
			foreach ($candidateVehicles as $vehicle) {
				log::add("EaseeCharger","debug","   " . $vehicle->getHumanName());
			}
		} 
	}

    /*     * **********************Getteur Setteur*************************** */

	public function getAccountId() {
		return $this->getConfiguration('accountId');
	}

	public function setAccountId($_accountId§) {
		$this->setConfiguration('accountId',$_accountId);
		return $this;
	}

	public function getImage() {
		$image = $this->getConfiguration('image');
		if ($image == '') {
			return "/plugins/EaseeCharger/plugin_info/EaseeCharger_icon.png";
		}
		return $image;
	}

	public function setImage($_image) {
		$this->setConfiguration('image',$_image);
		return $this;
	}

}

class EaseeCharger_chargerCmd extends EaseeChargerCmd  {
    /*     * *************************Attributs****************************** */

    /*
	public static $_widgetPossibility = array();
    */

    /*     * ***********************Methode static*************************** */

    /*     * *********************Methode d'instance************************* */

	public function dontRemoveCmd() {
		if ($this->getConfiguration('required') == 'yes') {
			return true;
		}
		return false;
	}

	public function preUpdate() {
		if ($this->getLogicalId() == 'refresh') {
                        return;
                }
		if ($this->getType() == 'info') {
			if ($this->getConfiguration('source') == 'calcul') {
				if ($this->getConfiguration('calcul') == '') {
					throw new Exception (sprintf(__("La formule de calcul pour la commande %s n'est pas définie!",__FILE__),$this->getLogicalId()));
				}
			} else if ($this->getConfiguration('source') == 'info') {
				if ($this->getConfiguration('calcul') == '') {
					throw new Exception (sprintf(__("L'info source pour la commande %s n'est pas définie!",__FILE__),$this->getLogicalId()));
				}
			}
			$calcul = $this->getConfiguration('calcul');
			if ($calcul != '') {
				if (strpos($calcul, '#' . $this->getId() . '#') !== false) {
					throw new Exception(__('Vous ne pouvez appeler la commande elle-même (boucle infinie) sur',__FILE__) . ' : '.$this->getName());
				}
				$added_value = [];
				preg_match_all("/#([0-9]+)#/", $calcul, $matches);
				$value = '';
				foreach ($matches[1] as $cmd_id) {
					$cmd = self::byId($cmd_id);
					if (is_object($cmd) && $cmd->getType() == 'info') {
						if(isset($added_value[$cmd_id])) {
							continue;
						}
						$value .= '#' . $cmd_id . '#';
						$added_value[$cmd_id] = $cmd_id;
					}
				}
				preg_match_all("/variable\((.*?)\)/",$calcul, $matches);
				foreach ($matches[1] as $variable) {
					if(isset($added_value['#variable(' . $variable . ')#'])){
						continue;
					}
					$value .= '#variable(' . $variable . ')#';
					$added_value['#variable(' . $variable . ')#'] = '#variable(' . $variable . ')#';
				}
				$this->setValue($value);
			}
		} else if ($this->getType() == 'action') {
			if ($this->getConfiguration('destination') == 'cmd') {
				if ($this->getConfiguration('destId') == '') {
					throw new Exception (sprintf(__("La destination de %s n'est pas définie!",__FILE__),$this->getLogicalId()));
				}
				if ($this->getConfiguration('destId') == '-') {
					$this->setConfiguration('destId','');
				}
			}
		}
	}

	public function postSave() {
		if ($this->getType() == 'info' && $this->getConfiguration('calcul') != '') {
			$this->event($this->execute());
		}
	}

    // Exécution d'une commande
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

    /*     * **********************Getteur Setteur*************************** */
}
