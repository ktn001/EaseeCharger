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

try {
	require_once __DIR__ . '/../../../../core/php/core.inc.php';
	require_once __DIR__ . '/../php/EaseeCharger.inc.php';

	include_file('core', 'authentification', 'php');

	if (!isConnect('admin')) {
		throw new Exception(__('401 - Accès non autorisé', __FILE__));
	}

	$action = init('action');
	log::add("EaseeCharger","debug","  Ajax EaseeCharger: action: " . $action);

	if ($action == 'createAccount') {
		try {
			$name = init('name');
			log::add("EaseeCharger", "debug", sprintf (__('Création de compte %s',__FILE__),$name));
			EaseeCharger_account::create($name);
			ajax::success();
		} catch (Exception $e){
			ajax::error(displayException($e), $e->getCode());
		}
	}

	if ($action == 'getAccount') {
		$name = init('name');
		if ($name == '') {
			throw new Exception(__("Le nom de l'account n'est pas défini",__FILE__));
		}
		$account = EaseeCharger_account::byName($name);
		if (!is_object($account)) {
			throw new Exception(sprintf(__("Le compte %s est introuvable",__FILE__),$name));
		}
		ajax::success(json_encode(utils::o2a($account)));
	}

	if ($action == 'saveAccount') {
		$data = init('account');
		if ($data == '') {
			throw new Exception(__("Pas de données pour la sauvegarde du compte",__FILE__));
		}
		$data = json_decode($data,true);
		$account = EaseeCharger_account::byName($data['name']);
		utils::a2o($account,$data);
		$account->save();
		$return = array();
		$return['account'] = utils::o2a(EaseeCharger_account::byName($data['name']));
		$return['modifiedChargers'] = $account->getModifiedChargers();
		ajax::success(json_encode($return));
	}

	if ($action == 'removeAccount') {
		$name = init('name');
		if ($name == '') {
			throw new Exception(__("Le nom du compte à supprimer n'est pas défini",__FILE__));
		}
		$account = EaseeCharger_account::byName($name);
		if (!is_object($account)){
			throw new Exception(sprintf(__("Le compte à supprimer (%s) est intouvable",__FILE__),$name));
		}
		if ($account->remove()) {
			ajax::success();
		}
		ajax::error(sprintf(__("La suppression du compte %s n'a pas fonctionné correctement",__FILE__),$name));
	}

	if ($action == 'createCmds' || init('action') == 'updateCmds')  {
		$id = init('id');
		if ($id == ''){
			throw new Exception(__("L'Id du chargeur n'est pas indiqué",__FILE__));
		}
		$charger = EaseeCharger::byId($id);
		if (!is_object($charger)){
			throw new Exception(sprintf(__("Chargeur %s introuvable.",__FILE__),$id));
		}
		try {
			$option = array();
			if (init('action') == 'createCmds') {
				$options["createOnly"] = true;
			} elseif (init('action') == 'updateCmds')  {
				$options["updateOnly"] = true;
			}
			$charger->updateCmds($options);
			ajax::success();
		} catch (Exception $e){
			ajax::error(displayException($e), $e->getCode());
		}
	}
		
	throw new Exception(__("Aucune méthode correspondante à : ", __FILE__) . init('action'));

	/*     * *********Catch exeption*************** */
} catch (Exception $e) {
	ajax::error(displayException($e), $e->getCode());
}

