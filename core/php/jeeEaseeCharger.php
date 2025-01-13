<?php
// vi: tabstop=4 autoindent
try {
	require_once __DIR__ . '/../../../../core/php/core.inc.php';
	require_once __DIR__ . '/../class/EaseeCharger.class.php';
	require_once __DIR__ . '/../php/EaseeCharger.inc.php';

	function process_daemon_message($message) {
		if ($message['message'] == 'started'){
			EaseeCharger::daemon_started();
		}
	}

	function process_account_message($message) {
		if (isset($message['message']) && $message['message'] == 'started') {
			if (!isset($message['accountId'])){
				log::add("EaseeCharger","warning",__("Le nom du compte démarré n'est pas fourni",__FILE__));
				return;
			}
			$account = EaseeAccount::byId($message['accountId']);
			if (!is_object($account)) {
				log::add("EaseeCharger","error",sprintf(__("Le compte %s est introuvable",__FILE__),$message['account']));
				return;
			}
			$account->account_on_daemon_started();
		}
	}

	function process_charger_message($message) {
		if ($message['info'] == 'closed'){
			log::add('EaseeCharger','info','[jeeEaseeCharger] [' . $message['modelId'] . '][' . $message['charger'] . __('Connection du démon fermée',__FILE__));
		}
	}

	function process_cmd_message($message) {
		if (!array_key_exists('charger',$message)) {
			log::add('EaseeCharger','error',"[jeeEaseeCharger] " .  __("Message du demon de modèle <cmd> mais sans identifiant de chargeur!",__FILE__));
		}
		if (!array_key_exists('logicalId',$message)) {
			log::add('EaseeCharger','error',"[jeeEaseeCharger] " . __("Message du demon de modèle <cmd> mais sans <logicalId>!",__FILE__));
		}
		$charger = EaseeCharger::byId($message['charger']);
		if (!is_object($charger)) {
			log::add('EaseeCharger','error',sprintf("[jeeEaseeCharger] " . __("Message du daemon de modèle <cmd> pour le chargeur %s qui est introuvable",__FILE__),$message['charger']));
		}
		$charger->checkAndUpdateCmd($message['logicalId'],$message['value']);
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

	$payload = json_decode(file_get_contents("php://input"),true);
	$payload = is_json($payload,$payload);
	if (!is_array($payload)) {
		die();
	}
	log::add("EaseeCharger","debug","[jeeEaseeCharger] Message reçu du démon: " . print_r($payload,true));

	$messages = array();
	if (array_key_exists('cmds',$payload)) {
		foreach ($payload['cmds']  as $charger) {
			foreach ($charger as $cmd ) {
				$messages[] = is_json($cmd,$cmd);
			}
		}
	} else {
		$messages = array($payload);
	}

	foreach ($messages as $message) {
		if (!array_key_exists('object',$message)){
			log::error('EaseeCharger','error','[jeeEaseeCharger] Message reçu du daemon sans champ "object"');
			die();
		}
		switch ($message['object']) {
		case 'daemon':
			process_daemon_message($message);
			break;
		case 'account':
			process_account_message($message);
			break;
		case 'charger':
			process_charger_message($message);
			break;
		case 'cmd':
			process_cmd_message($message);
			break;
		}
	}


} catch (Exception $e) {
	log::add('EaseeCharger','error', "[jeeEaseeCharger] " . displayException($e));
}

?>
