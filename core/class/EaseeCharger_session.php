<?php

require_once __DIR__ . '/../../../../core/php/core.inc.php';

class Easee_session {

	protected $id;
	protected $chargerId;
	protected $energy;
	protected $start;
	protected $end;
	protected $sessionId;
	protected $duration;
	protected $energyTransfertStart;
	protected $energyTransfertEnd;
	protected $prixKwh;
	protected $prix;


	public function getTableName() {
		return 'Easee_session';
	}
}

