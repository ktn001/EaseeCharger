<?php

require_once __DIR__ . '/../php/EaseeCharger.inc.php';

class Easee_session {

	private $id;
	private $chargerId;
	private $energy;
	private $start;
	private $end;
	private $sessionId;
	private $duration;
	private $energyTransferStart;
	private $energyTransferEnd;
	private $prixKwh;
	private $prix;
	private static $_easeeArrayMaping = array (
		'chargerId' => 'setChargerId',
		'sessionEnergy' => 'setEnergy',
		'kiloWattHours' => 'setEnergy',
		'sessionStart' => 'setStart',
		'carConnected' => 'setStart',
		'sessionEnd' => 'setEnd',
		'carDisconnected' => 'setEnd',
		'sessionId' => 'setSessionId',
		'id' => 'setSessionId',
		'chargeDurationInSeconds' => 'setDuration',
		'actualDurationSeconds' => 'setDuration',
		'firstEnergyTransferPeriodStart' => 'setEnergyTransferStart',
		'firstEnergyTransferPeriodStarted' => 'setEnergyTransferStart',
		'lastEnergyTransferPeriodEnd' => 'setEnergyTransferEnd',
		'pricePrKwhIncludingVat' => 'setPrixKwh',
		'costIncludingVat' => 'setPrix',
	);

	public static function byId($_id) {
		$value = array(
			'id' => $_id,
		);
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
			FROM Easee_session
			WHERE id=:id';
		return DB::Prepare($sql, $value, DB::FETCH_TYPE_ROW, PDO::FETCH_CLASS, __CLASS__);
	}

	public static function byChargerIdAndSessionId($_chargerId, $_sessionId) {
		$value = array(
			'chargerId' => $_chargerId,
			'sessionId' => $_sessionId,
		);
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
			FROM Easee_session
			WHERE chargerId=:chargerId
			  AND sessionId=:sessionId';
		return DB::Prepare($sql, $value, DB::FETCH_TYPE_ROW, PDO::FETCH_CLASS, __CLASS__);
	}

	public static function byChargerId($_chargerId) {
		$value = array(
			'chargerId' => $_chargerId,
		);
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
			FROM Easee_session
			WHERE chargerId=:chargerId
			ORDER BY end';
		return DB::Prepare($sql, $value, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__);
	}

	public static function fromEaseeArray ($_easeeArray) {
		if (!is_array($_easeeArray)) {
			throw new Exception (__("$_easeeArray n'est pas un tableau",__FILE__));
		}
		if (!array_key_exists('chargerId',$_easeeArray)) {
			log::add("EaseeCharger","debug","fromEaseeArray: chargerId " . __("introuvable dans ",__FILE__) . print_r($_easeeArray,true));
			throw new Exception ("chargerId " . __("non défini", __FILE__));
		}
		if (!(array_key_exists('sessionId',$_easeeArray) or array_key_exists('id',$_easeeArray))) {
			log::add("EaseeCharger","debug","fromEaseeArray: sessionId " . __("introuvable dans ",__FILE__) . print_r($_easeeArray,true));
			throw new Exception ("sessionId " . __("non défini", __FILE__));
		}
		$sessionId = array_key_exists('sessionId',$_easeeArray) ? $_easeeArray['SessionId'] : $_easeeArray['id'];
		$session = self::byChargerIdAndSessionId($_easeeArray['chargerId'],$sessionId);
		if (!is_object($session)){
			$session = new Easee_session();
		}
		foreach(self::$_easeeArrayMaping as $key => $function) {
			if (array_key_exists($key, $_easeeArray)) {
				$session->$function($_easeeArray[$key]);
			}
		}
		return $session;
	}

	public static function purge ($_chargerId, $_expirationDate){
		$values = array (
			'chargerId' => $_chargerId,
			'dateExpiration' => $_expirationDate,
		);
		$sql = 'DELETE FROM Easee_session
			WHERE chargerId=:chargerId
			  AND end < :dateExpiration';
		DB::Prepare($sql, $values, DB::FETCH_TYPE_ROW);

	}

	public function getTableName() {
		return 'Easee_session';
	}

	public function save() {
		if (!$this->id) {
			$existingSession = $this::byChargerIdAndSessionId($this->chargerId,$this->sessionId);
			if (is_object($existingSession)) {
				$egal = true;
				log::add("EaseeCharger","debug",sprintf(__("Une session id %s pour le chageur %s a été trouvée", __FILE__), $this->sessionId, $this->chargerId));
				foreach (['start', 'end', 'duration', 'energyTransferStart', 'energyTransferEnd', 'energy', 'prixKwh', 'prix'] as $var) {
					if ((string)$existingSession->$var != (string)$this->$var) {
						$egal = false;
						log::add("EaseeCharger","debug",sprintf(__("  %s diffère ", __FILE__),$var) . $existingSession->$var . ' => ' . $this->$var);
					}
				}
				if ($egal) {
					return;
				} else {
					$this->id = $existingSession->id;
				}
			}
		}
		DB::save($this);
	}


	/*    * *******************Getteur Setteur********************** */

	public function getId() {
		return $this->id;
	}

	public function setId($_id) {
		$this->id = $_id;
		return $this;
	}

	public function getChargerId() {
		return $this->chargerId;
	}

	public function setChargerId($_chargerId) {
		$this->chargerId = $_chargerId;
		return $this;
	}

	public function getEnergy() {
		return $this->energy;
	}

	public function setEnergy($_energy) {
		$this->energy= $_energy;
		return $this;
	}

	public function getStart() {
		return $this->start;
	}

	public function setStart($_start) {
		$this->start = date("Y-m-d H:i:s", strtotime($_start));
		return $this;
	}

	public function getEnd() {
		return $this->end;
	}

	public function setEnd($_end) {
		$this->end = date("Y-m-d H:i:s", strtotime($_end));
		return $this;
	}

	public function getSessionId() {
		return $this->sessionId;
	}

	public function setSessionId($_sessionId) {
		$this->sessionId = $_sessionId;
		return $this;
	}

	public function getDuration() {
		return $this->duration;
	}

	public function setDuration($_duration) {
		$this->duration = $_duration;
		return $this;
	}

	public function getEnergyTransferStart() {
		return $this->energyTransferStart;
	}

	public function setEnergyTransferStart($_energyTransferStart) {
		$this->energyTransferStart = date("Y-m-d H:i:s", strtotime($_energyTransferStart));
		return $this;
	}

	public function getEnergyTransferEnd() {
		return $this->energyTransferEnd;
	}

	public function setEnergyTransferEnd($_energyTransferEnd) {
		$this->energyTransferEnd = date("Y-m-d H:i:s", strtotime($_energyTransferEnd));
		return $this;
	}

	public function getPrixKwh() {
		return $this->prixKwh;
	}

	public function setPrixKwh($_prixKwh) {
		$this->prixKwh = $_prixKwh;
		return $this;
	}

	public function getPrix() {
		return $this->prix;
	}

	public function setPrix($_prix) {
		$this->prix = $_prix;
		return $this;
	}
}

