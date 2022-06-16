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

	log::add("EaseeCharger","debug","  Ajax EaseeCharger: action: " . init('action'));

	if (init('action') == 'images') {
		$modelId = init('modelId');
		if ($modelId == '') {
			throw new Exception(__("2 Le modèle de chargeur n'est pas indiqué",__FILE__));
		}
		$model = model::byId($modelId);
		ajax::success(json_encode($model->images('charger')));
	}

	if (init('action') == 'ParamsHtml') {
		$model = init('modelId');
		if ($model == '') {
			throw new Exception(__("1 Le modèle de chargeur n'est pas indiqué",__FILE__));
		}
		$object = init('object');
		if ($object == '') {
			throw new Exception(__("L'objet n'est pas indiqué",__FILE__));
		}
		$file = realpath (__DIR__.'/../../desktop/php/'.$model.'/' . $object . '_params.inc.php');
		if (file_exists($file)) {
			ob_start();
			require_once $file;
			$content = translate::exec(ob_get_clean(), $file);
			ajax::success($content);
		}
		ajax::success();
	}

	if (init('action') == 'createCmds' || init('action') == 'updateCmds')  {
		$id = init('id');
		if ($id == ''){
			throw new Exception(__("L'Id du chargeur n'est pas indiqué",__FILE__));
		}
		$charger = EaseeCharger::byId($id);
		if (!is_object($charger)){
			throw new Exception(sprintf(__("Chargeur %s introuvable.",__FILE__),$id));
			ajax::error();
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

	if (init('action') == 'createAccount') {
		try {
			$name = init('name');
			log::add("EaseeCharger", "debug", sprintf (__('Création de compte %s',__FILE__),$name));
			EaseeCharger_account::create($name);
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

