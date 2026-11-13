<?php
try {
    require_once __DIR__ . '/../../../../core/php/core.inc.php';
    require_once __DIR__ . '/../class/EaseeCharger.class.php';
    require_once __DIR__ . '/../php/EaseeCharger.inc.php';

    /**
     ** Traitement des messages relatifs au daemon
     **/
    function process_daemon_message($message) {
        if ($message['message'] == 'started'){
            EaseeCharger::daemon_started();
        }
    }

    /**
     ** Traitement des messages relatifs aux accounts
     **/
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

    /**
     ** Traitement des messages relatifs aux chargers
     **/
    function process_charger_message($message) {
        if ($message['info'] == 'closed'){
            log::add('EaseeCharger','info','[jeeEaseeCharger] [' . $message['modelId'] . '][' . $message['charger'] . __('Connection du démon fermée',__FILE__));
        }
    }

    /**
     ** Traitement des messages relatifs aux commandes
     **/
    function process_cmd_message($message) {
        log::add("EaseeCharger","debug",__("Traitement du message: ",__FILE__) . json_encode($message));
        if (!isset($message['mid'])) {
            return;
        }
        $charger = EaseeCharger::byLogicalId($message['mid'],'EaseeCharger');
        if (!is_object($charger)) {
            log::add("EaseeCharger","warning",sprintf(__("Chargeur %s introuvable",__FILE__),$message['mid']));
            return;
        }
        if (!isset($message['id'])) {
            return;
        }
        $cmd = $charger->getCmd('info',$message['id']);
        if (isset($message['timestamp'])){
            $message['timestamp'] = date('Y-m-d H:i:s', strtotime($message['timestamp']));
        }
        if (is_object($cmd)) {
            $charger->checkAndUpdateCmd($message['id'],$message['value'], $message['timestamp'] ?? '');
        } else {
            $charger->createAndUpdateCmd($message);
        }
    }


      ###############################
     #                                #
    #  #    #    ##       #    #    #  #
    #  ##  ##   #  #      #    ##   #  #
    #  # ## #  #    #     #    # #  #  #
    #  #    #  ######     #    #  # #  #
    #  #    #  #    #     #    #   ##  #
    #  #    #  #    #     #    #    #  #
     #                                #
      ###############################
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
            log::add('EaseeCharger','error','[jeeEaseeCharger] Message reçu du daemon sans champ "object"');
            continue;
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
