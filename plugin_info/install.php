<?php
// vi: tabstop=4 autoindent

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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
require_once dirname(__FILE__) . '/../core/php/EaseeCharger.inc.php';

// update `config` set `value` = 3 WHERE `plugin` = 'EaseeCharger' and `key`= 'plugin::level'

function EaseeCharger_goto_6() {
    $filename = "/var/www/html/log/EaseeCharger_daemon.out";
    if (file_exists($filename)) {
        log::add("EaseeCharger","info",sprintf(__("Suppression de %s",__FILE__),$filename));

        unlink ($filename);
    }

    $observations = file_get_contents(__DIR__ . "/../core/config/observations.json");
    $observations = is_json($observations,$observations);
    $convert = [];
    foreach ($observations as $observation) {
        if (isset($observation['old_logicalId'])){
            $convert[$observation['old_logicalId']] = $observation['id'];
        }
    }
	$chargers = EaseeCharger::byType('EaseeCharger');
    foreach ($chargers as $charger) {
        log::add("EaseeCharger","info",sprintf(__("upgrade du chargeur %s",__FILE__),$charger->getName()));
        $cmds = $charger->getCmd();
        foreach ($cmds as $cmd) {
            if (isset($convert[$cmd->getLogicalId()])) {
                $cmd->setLogicalId($convert[$cmd->getLogicalId()]);
                $cmd->save();
            }
            if ($cmd->getConfiguration('calcul')) {
                $cmd->setConfiguration('calcul',null);
                $cmd->save();
            }
        }
    }
}

function EaseeCharger_goto_5() {
	$chargers = EaseeCharger::byType('EaseeCharger');
	foreach ($chargers as $charger) {
		$cmd = $charger->getCmd('info','power');
		if (is_object($cmd)) {
			$cmd->setTemplate('dashboard','core::gauge');
			$cmd->setTemplate('mobile','core::gauge');
			$cmd->save();
		}
	}
}

function EaseeCharger_goto_4() {
	$accounts = EaseeAccount::all();
	$mapNames = array ();
	foreach ($accounts as $account) {
		if ($account->getId() == '') {
			$account->save();
		}
		config::remove('account::' . $account->getName(), 'EaseeCharger');
		$mapNames[$account->getName()] = $account->getId();
		$cache=cache::byKey('EaseeAccount:' . $this->getName());
		if (is_object($cache)) {
			$token = $cache->getValue();
			cache::set("EaseeAccount:" . $this->getId(), $token, 192800);
			$cache->remove();
		}
	}
	$chargers = EaseeCharger::byType('EaseeCharger');
	foreach ($chargers as $charger) {
		$accountName = $charger->getConfiguration('accountName');
		if (array_key_exists($accountName,$mapNames)) {
			$charger->setAccountId ($mapNames[$accountName]);
			$charger->setConfiguration ('accountName',null);
			$charger->save();
		}
	}
}

function EaseeCharger_goto_3() {
	$chargers = EaseeCharger::byType('EaseeCharger');
	foreach ($chargers as $charger) {
		$charger->createCmds();
	}
}

function EaseeCharger_goto_2() {
	config::save('heartbeat::delay::EaseeCharger',15);
	config::save('heartbeat::restartDeamon::EaseeCharger',1);
}

function EaseeCharger_goto_1() {
	$chargers = EaseeCharger::byType('EaseeCharger');
	foreach ($chargers as $charger) {
		$charger->createCmds();
	}
}

function EaseeCharger_upgrade() {
	$packagesFile = __DIR__ . '/packages.json';
	if (file_exists($packagesFile)) {
		unlink($packagesFile);
	}
	if (is_dir(__DIR__ . '/../resources')) {
		system('rm -rf ' . __DIR__ . '/../resources', $retval);
	}

	$lastLevel = 6;
	$pluginLevel = config::byKey('plugin::level','EaseeCharger', 0);
	for ($level = 1; $level <= $lastLevel; $level++) {
		if ($pluginLevel  < $level) {
			$function = 'EaseeCharger_goto_' . $level;
			if (function_exists($function)) {
				log::add('EaseeCharger','info','execution de ' . $function . '()');
				$function();
			}
			config::save('plugin::level',$level,'EaseeCharger');
			$pluginLevel = $level;
			log::add('EaseeCharger','info','pluginlevel: ' . $pluginLevel);
		}
	}
}

// Fonction exécutée automatiquement après l'installation du plugin
function EaseeCharger_install() {
	log::add("EaseeCharger","info","Execution de EaseeCharger_install");
	config::save('api', config::genKey(), 'EaseeCharger');
	config::save('api::EaseeCharger::mode', 'localhost');
	config::save('api::EaseeCharger::restricted', '1');
	EaseeCharger_upgrade();
}

// Fonction exécutée automatiquement après la mise à jour du plugin
function EaseeCharger_update() {
	log::add("EaseeCharger","info","Execution de EaseeCharger_update");
	EaseeCharger_upgrade();
}

?>
