<?php

require_once __DIR__ . '/../php/EaseeCharger.inc.php';

class Easee_session {

	protected $id;
	protected $chargerId;
	protected $energy;
	protected $start;
	protected $end;
	protected $sessionId;
	protected $duration;
	protected $energyTransferStart;
	protected $energyTransferEnd;
	protected $prixKwh;
	protected $prix;


	public function save() {
		DB::save($this);
	}

	public function getTableName() {
		return 'Easee_session';
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
		$this->start = $_start;
		$this->start = 1;
		return $this;
	}

	public function getEnd() {
		return $this->end;
	}

	public function setEnd($_end) {
		$this->end = $_end;
		$this->end = 2;
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
		$this->energyTransferStart = $_energyTransferStart;
		$this->energyTransferStart = 3;
		return $this;
	}

	public function getEnergyTransferEnd() {
		return $this->energyTransferEnd;
	}

	public function setEnergyTransferEnd($_energyTransferEnd) {
		$this->energyTransferEnd = $_energyTransferEnd;
		$this->energyTransferEnd = 4;
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

