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

	log::add("EaseeCharger","debug","┌─Ajax Account: action: " . init('action'));

	if (init('action') == 'images') {
		$modelId = init('modelId');
		if (modelId == '') {
			throw new Exception(__("Le model de compte n'est pas indiqué",__FILE__));
		}
		$modelId = model::byId($modelId);
		ajax::success(json_encode($modelId->images('account')));
	}

	if (init('action') == 'getAccountToSelect') {
		$result = array();
		foreach (EaseeCharger_account::byModel(init('modelId')) as $account) {
			$data = array(
				'id' => $account->getId(),
				'value' => $account->getHumanName(),
			);
			$result[] = $data;
		}
		log::add("EaseeCharger","debug","└─Ajax Account: SUCCESS");
		ajax::success(json_encode($result));
	}

	throw new Exception(__('Aucune méthode correspondante à : ', __FILE__) . init('action'));

	/*     * *********Catch exeption*************** */
} catch (Exception $e) {
	ajax::error(displayException($e), $e->getCode());
}
