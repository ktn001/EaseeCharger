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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
require_once dirname(__FILE__) . '/../core/php/EaseeCharger.inc.php';

// Fonction exécutée automatiquement après l'installation du plugin
function EaseeCharger_install() {
	log::add("EaseeCharger","info","Execution de EaseeCharger_install");
	config::save('daemon::port', '34739', 'EaseeCharger');
	config::save('api', config::genKey(), 'EaseeCharger');
	config::save('api::EaseeCharger::mode', 'localhost');
	config::save('api::EaseeCharger::restricted', '1');
	try {
		EaseeCharger::createEngine();
		foreach (EaseeCharger::byType("EaseeCharger_%") as $eqLogic){
			$changed = false;
			if ($eqLogic->getIsEnable() == 0 and $eqLogic->getConfiguration('previousIsEnable',0) == 1) {
				$eqLogic->setIsEnable(1);
				$changed = true;
			}
			if ($eqLogic->getIsVisible() == 0 and $eqLogic->getConfiguration('previousIsVisible',0) == 1) {
				$eqLogic->setIsVisible(1);
				$changed = true;
			}
			if ($changed) {
				$eqLogic->save();
			}
		}
	} catch (Exception $e) {
		log::add("EaseeCharger","error",$e->getMessage());
	}
}

// Fonction exécutée automatiquement après la mise à jour du plugin
function EaseeCharger_update() {
	log::add("EaseeCharger","info","Execution de EaseeCharger_update");
}

// Fonction exécutée automatiquement après la suppression du plugin
  function EaseeCharger_remove() {
	log::add("EaseeCharger","info","Execution de EaseeCharger_remove");
	try {
		foreach (EaseeCharger::byType("EaseeCharger_%") as $eqLogic){
			$eqLogic->setConfiguration('previousIsEnable',$eqLogic->getIsEnable());
			$eqLogic->setConfiguration('previousIsVisible',$eqLogic->getIsVisible());
			$eqLogic->setIsEnable(0);
			$eqLogic->setIsVisible(0);
			$eqLogic->save();
		}
	} catch (Exception $e) {
		log::add("EaseeCharger","error",$e->getMessage());
	}
  }

?>
