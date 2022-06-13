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

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';
require_once __DIR__ . '/../php/EaseeCharger.inc.php';

class model {

	private $modelId;
	private $label;
	private $configuration;
	private static $_modelsFile = __DIR__ . "/../config/models.ini";

    /*     * *********************** Constructeur ***************************** */

	function __construct($modelId) {
		$this->modelId = $modelId;
		$models = parse_ini_file(self::$_modelsFile,true);
		if ($models == false) {
			$msg = sprintf(__('Erreur lors de la lecture de %s',__FILE__),self::$_modelsFile);
			// log::add("EaseeCharger","error",$msg);
			throw new Exception($msg);
		}
		if (!array_key_exists($modelId,$models)) {
			throw new Exception (sprintf(__('%s est introuvable dans %s',__FILE__),$modelId, self::$_modelsFile));
		}
		if (array_key_exists('label',$models[$modelId])) {
			$this->label = translate::exec($models[$modelId]['label'],__FILE__);
		} else {
			$this->label = $modelId;
		}
		$this->configuration = config::byKey('model::' . $modelId, 'EaseeCharger', array());
	}

    /*     * *********************** Methodes static ************************** */

	public static function all($onlyEnabled = true ) {
		$models = parse_ini_file(self::$_modelsFile,true);
		if ($models == false) {
			$msg = sprintf(__('Erreur lors de la lecture de %s',__FILE__),self::$_modelsFile);
			log::add("EaseeCharger","error",$msg);
			throw new Exception($msg);
		}
		$result = array();
		foreach (array_keys($models) as $modelId){
			$model = new self($modelId);
			if ($onlyEnabled == false or $model->isEnabled()) {
				$result[$modelId] = $model;
			}
		}
		return $result;
	}

	public static function byId($modelId) {
		return new self($modelId);
	}

	public static function labels($onlyEnabled = true) {
		$labels = array();
		foreach (model::all($onlyEnabled) as $modelId => $model) {
			$labels[$modelId] = $model->getLabel();
		}
		return $labels;
	}

	public static function allUsed() {
		$used = array();
		foreach (EaseeCharger_account::byType('EaseeCharger_account_%') as $account) {
			$used[$account->getModelId()] = 1;
		}
		return array_keys($used);
	}

	private static function getConfigs($model) {
		return parse_ini_file(__DIR__ . '/../config/' . $model . '/config.ini' ,true);
	}

	public static function getIdentifiantCharger($model) {
		return model::getConfigs($model)['charger']['identifiant'];
	}

    /*     * *********************** Méthodes d'instance ********************** */

	public function save() {
		config::save('model::' . $this->modelId, $this->configuration, 'EaseeCharger');
	}

	public function isEnabled() {
		return $this->getConfiguration('enabled',0);
	}

	public function images($objet) {
		$images = array();
		$path = realpath(__DIR__ . '/../../desktop/img/' . $this->modelId);
		if ($dir = opendir($path)){
			while (($fileName = readdir($dir)) !== false){
				if (preg_match('/^' . $objet . '.*\.png$/', $fileName)){
					$images[] = strchr($path.'/'.$fileName, '/plugins/');
				}
			}
		}
		if (count($images) == 0){
			$images[] = strchr(realpath(__DIR__.'/../../desktop/img/'.$objet.'.png'),'/plugins/');
		}
		sort ($images);
		return $images;
	}

	public function commands($requiredOnly = false) {

		$parameters = array(
			'calcul',
			'destination',
			'display::graphStep',
			'displayName',
			'group',
			'name',
			'order',
			'required',
			'rounding',
			'source',
			'subType',
			'template',
			'type',
			'unite',
			'value',
			'visible'
		);

		/*
		 *  Lecture des fichiers de définition des commandes
		 */
		$configPath = __DIR__ . '/../config';
		$configFile = 'cmd.config.ini';

		$globalConfigs = parse_ini_file($configPath . "/" . $configFile,true, INI_SCANNER_RAW);
		$modelConfigs = parse_ini_file($configPath.'/' . $this->modelId . '/' . $configFile,true, INI_SCANNER_RAW);

		$sections = array();
		foreach (array_keys($globalConfigs) as $section) {
			$sections[$section] = array();
		}
		foreach (array_keys($modelConfigs) as $section) {
			$sections[$section] = array();
		}

		foreach (array_keys($sections) as $section) {
			if (array_key_exists($section,$globalConfigs)) {
				$sections[$section] = $globalConfigs[$section];
				if (array_key_exists($section,$modelConfigs)) {
					foreach ($parameters as $parameter) {
						if (array_key_exists($parameter,$modelConfigs[$section])) {
							$sections[$section][$parameter] = $modelConfigs[$section][$parameter];
						}
					}
				}
			} else {
				$sections[$section] = $modelConfigs[$section];
			}
		}

		$groupConfigs = array();
		$cmdConfigs = array();
		foreach (array_keys($sections) as $section) {
			if (strpos($section, 'group:') === 0) {
				$group = substr($section,6);
				$groupConfigs[$group] = $sections[$section];
			} else {
				$cmdConfigs[$section] = $sections[$section];
			}
		}

		foreach (array_keys($cmdConfigs) as $cmd) {
			if (array_key_exists('group',$cmdConfigs[$cmd])) {
				$group = $cmdConfigs[$cmd]['group'];
				if (! array_key_exists($group,$groupConfigs)) {
					throw new Exception (sprintf(__("Le groupe %s utilisé dans la définition de la commande %s est introuvable.",__FILE__), $group, $cmd));
				}
				foreach ($parameters as $parameter) {
					if (array_key_exists($parameter,$groupConfigs[$group])) {
						if (! array_key_exists($parameter,$cmdConfigs[$cmd])){
							$cmdConfigs[$cmd][$parameter] = $groupConfigs[$group][$parameter];
						}
					}
				}
			}
		}

		foreach (array_keys($cmdConfigs) as $cmd) {
			if (! array_key_exists('required',$cmdConfigs[$cmd])) {
				throw new Exception (sprintf(__("Le paramètre 'required' n'est pas défini pour la commande %s!",__FILE__),$cmd));
			}
			if ($cmdConfigs[$cmd]['required'] == 'no') {
				unset ($cmdConfigs[$cmd]);
			} elseif ($cmdConfigs[$cmd]['required'] == 'optional') {
				if ( $requiredOnly) {
					unset ($cmdConfigs[$cmd]);
				}
			} elseif ($cmdConfigs[$cmd]['required'] != 'yes') {
				throw new Exception (sprintf(__("Le paramètre 'required' a une valeur non reconnue (%s) pour la commande %s!",__FILE__),$cmd['required'],$cmd));
			}
		}
		foreach (array_keys($cmdConfigs) as $cmd) {
			if (! array_key_exists('name',$cmdConfigs[$cmd])) {
				throw new Exception (sprintf(__("Le nom n'est pas défini pour la commande %s!",__FILE__),$cmd));
			}
			if (! array_key_exists('order',$cmdConfigs[$cmd])) {
				throw new Exception (sprintf(__("Le classement n'est pas défini pour la commande %s!",__FILE__),$cmd));
			}
			if (! array_key_exists('subType',$cmdConfigs[$cmd])) {
				throw new Exception (sprintf(__("Le sous-type n'est pas défini pour la commande %s!",__FILE__),$cmd));
			}
			if (! array_key_exists('type',$cmdConfigs[$cmd])) {
				throw new Exception (sprintf(__("Le type n'est pas défini pour la commande %s!",__FILE__),$cmd));
			}
			if (! array_key_exists('source',$cmdConfigs[$cmd]) and $cmdConfigs[$cmd]['type'] == 'info' ) {
				throw new Exception (sprintf(__("La source de la commande %s n'est pas définie!",__FILE__),$cmd));
			}
		}
		return $cmdConfigs;
	}

	public function getHumanName($_tag = false, $_prettify = false) {
		if ($_tag) {
			if ($_prettify) {
				if ($this->getConfiguration('customColor') == 1) {
					$backgroundColor = $this->getConfiguration('tagColor');
					$textColor = $this->getConfiguration('tagTextColor');
				} else {
					$backgroundColor = config::getDefaultConfiguration('EaseeCharger')['EaseeCharger']['defaultTagColor'];
					$textColor = config::getDefaultConfiguration('EaseeCharger')['EaseeCharger']['defaultTextTagColor'];
				}
				return '<span class="label labelModelHuman" style="background-color:' . $backgroundColor . ';color:' . $textColor . '">(' . $this->getLabel() . ')</span>';
			} else {
				return $this->getLabel();
			}
		} else {
			return '[' . $this->getLabel() . ']';
		}
	}

    /*     * *********************** Getteur Setteur ************************** */

	public function getConfiguration($_key = '', $_default = '') {
		if ($_key == ''){
			return is_array($this->configuration) ? $this->configuration : array();
		}
		$configuration = $this->configuration;
		if (array_key_exists($_key,$configuration)) {
			return $configuration[$_key];
		}
		return $_default;
	}

	public function setConfiguration($_key, $_value) {
		$this->configuration[$_key] = $_value;
	}

	public function getLabel() {
		return $this->label;
	}

	public function getId() {
		return $this->modelId;
	}
}
