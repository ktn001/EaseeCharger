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
	config::save('api', config::genKey(), 'EaseeCharger');
	config::save('api::EaseeCharger::mode', 'localhost');
	config::save('api::EaseeCharger::restricted', '1');
	prepare_db();
}

// Fonction exécutée automatiquement après la mise à jour du plugin
function EaseeCharger_update() {
	log::add("EaseeCharger","info","Execution de EaseeCharger_update");
	prepare_db();
}

function table_exists ($table) {
	$result = DB::getConnection()->query('SHOW TABLES like "' . $table . '"')->fetch();
	return is_array($result);
}

function prepare_db () {
	$table_name = 'Easee_session';
	$db = DB::getConnection();
	if (!table_exists($table_name)) {
		log::add("EaseeCharger","info","La table doit être créée");
		$sql =    'CREATE TABLE ' . $table_name . '('
			. '    id                  INT         NOT NULL AUTO_INCREMENT,'
			. '    chargerId           varchar(10) NOT NULL,'
                        . '    sessionId           INT         NOT NULL,'
			. '    start               DATETIME    NOT NULL,'
			. '    end                 DATETIME    NOT NULL,'
			. '    duration            INT,'
			. '    energyTransferStart DATETIME,'
			. '    energyTransferEnd   DATETIME,'
			. '    energy              REAL       NOT NULL,'
			. '    prixKwh             REAL        NOT NULL,'
			. '    prix                REAL       NOT NULL,'
			. '  PRIMARY KEY(id),'
			. '  UNIQUE KEY (chargerId, sessionId)'
			. ')';
		if ($db->exec($sql) === false) {
			log::add("EaseeCharger","error","Error creating table '" . $table_name . "':  " . $db->errorInfo()[2]);
		}
		config::save('DB::version',1,'EaseeCharger');
	}
}

?>
