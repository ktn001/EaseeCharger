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
include_file('core', 'authentification', 'php');
if (!isConnect()) {
  include_file('desktop', '404', 'php');
  die();
}
include_file('core', 'EaseeCharger', 'class', 'EaseeCharger');
sendVarToJS('defaultTagColor', config::getDefaultConfiguration('EaseeCharger')['EaseeCharger']['defaultTagColor']);
sendVarToJS('defaultTagTextColor', config::getDefaultConfiguration('EaseeCharger')['EaseeCharger']['defaultTextTagColor']);

$defaultPort = config::getDefaultConfiguration('EaseeCharger')['EaseeCharger']['daemon::port'];
$defaultMaxPlugDelay = config::getDefaultConfiguration('EaseeCharger')['EaseeCharger']['maxPlugDelay'];
$defaultMaxDistance = config::getDefaultConfiguration('EaseeCharger')['EaseeCharger']['maxDistance'];
?>

<form class="form-horizontal">
  <fieldset>
    <div class="form-group">
      <div class='col-sm-6'> <!-- partie gauche -->

        <legend class="col-sm-12"><i class="fas fa-university"></i> {{Démon}}:</legend>

        <label class="col-sm-2 control-label">
          {{Port}}
          <sup><i class="fas fa-question-circle" title="{{Redémarrer le démon en cas de modification}}"></i></sup>
        </label>
        <input class="configKey form-control col-sm-2" data-l1key="daemon::port" placeholder="<?php echo $defaultPort ?>"/>

	<label class="col-sm-4 control-label">
	  {{Debug étendu}}
	  <sup><i class="fas fa-question-circle" title="{{Niveau debug étendu pour le démon (très verbeux)}}"></i></sup>
	</label>
	<input class="configKey form-control" type="checkbox" data-l1key="extendedDebug"/>

      </div> <!-- partie gauche -->

    </div>
  </fieldset>
</form>

