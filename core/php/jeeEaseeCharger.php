<?php
try {
	require_once __DIR__ . '/../../../../core/php/core.inc.php';
	require_once __DIR__ . '/../class/EaseeCharger.class.php';
	require_once __DIR__ . '/../php/EaseeCharger.inc.php';

	function process_daemon_payload($payload) {
		if ($payload['message'] == 'started'){
			EaseeCharger::daemon_started();
		}
	}

	function process_account_payload($payload) {
		if (isset($payload['message']) && $payload['message'] == 'started') {
			if (!isset($payload['accountId'])){
				log::add("EaseeCharger","warning",__("Le nom du compte démarré n'est pas fourni",__FILE__));
				return;
			}
			$account = EaseeAccount::byId($payload['accountId']);
			if (!is_object($account)) {
				log::add("EaseeCharger","error",sprintf(__("Le compte %s est introuvable",__FILE__),$payload['account']));
				return;
			}
			$account->account_on_daemon_started();
		}
	}

	function process_charger_payload($payload) {
		if ($payload['info'] == 'closed'){
			log::add('EaseeCharger','info','[jeeEaseeCharger] [' . $payload['modelId'] . '][' . $payload['charger'] . __('Connection du démon fermée',__FILE__));
		}
	}

	function process_cmd_payload($payload) {
		if (!array_key_exists('charger',$payload)) {
			log::add('EaseeCharger','error',"[jeeEaseeCharger] " .  __("Message du demon de modèle <cmd> mais sans identifiant de chargeur!",__FILE__));
		}
		if (!array_key_exists('logicalId',$payload)) {
			log::add('EaseeCharger','error',"[jeeEaseeCharger] " . __("Message du demon de modèle <cmd> mais sans <logicalId>!",__FILE__));
		}
		$charger = EaseeCharger::byId($payload['charger']);
		if (!is_object($charger)) {
			log::add('EaseeCharger','error',sprintf("[jeeEaseeCharger] " . __("Message du daemon de modèle <cmd> pour le chargeur %s qui est introuvable",__FILE__),$payload['charger']));
		}
		$charger->checkAndUpdateCmd($payload['logicalId'],$payload['value']);
		return;
	}

	if (!jeedom::apiAccess(init('apikey'), 'EaseeCharger')) {
		echo __('Vous n\'êtes pas autorisé à effectuer cette action', __FILE__);
		die();
	}
	if (init('test') != '') {
		echo 'ok';
		die();
	}

	$payload = json_decode(file_get_contents("php://input"), true);
	if (!is_array($payload)) {
		die();
	}
	log::add("EaseeCharger","debug","[jeeEaseeCharger] Message reçu du démon: " . print_r($payload,true));

	if (!array_key_exists('object',$payload)){
		log::error('EaseeCharger','error','[jeeEaseeCharger] Message reçu du daemon sans champ "object"');
		die();
	}
	switch ($payload['object']) {
	case 'daemon':
		process_daemon_payload($payload);
		break;
	case 'account':
		process_account_payload($payload);
		break;
	case 'charger':
		process_charger_payload($payload);
		break;
	case 'cmd':
		process_cmd_payload($payload);
		break;
	}


} catch (Exception $e) {
	log::add('EaseeCharger','error', "[jeeEaseeCharger] " . displayException($e));
}

?>
