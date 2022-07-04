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

$defaultPort = config::getDefaultConfiguration('EaseeCharger')['EaseeCharger']['daemon::port'];
?>

<form class="form-horizontal">
    <div class="form-group">
	<div class='col-md-6 col-sm-12'> <!-- partie gauche -->

	    <legend ><i class="fas fa-university"></i> {{Démon}}:</legend>

	    <fieldset>
		<div class="row">
		    <label class="col-sm-3 col-md-5 col-lg-4 control-label">
			{{Port}}
			<sup><i class="fas fa-question-circle" title="{{Redémarrer le démon en cas de modification}}"></i></sup>
		    </label>
		    <input class="configKey col-sm-2 form-control" data-l1key="daemon::port" placeholder="<?= $defaultPort ?>"/>
		</div>

		<div class="row">
		    <label class="col-sm-3 col-md-5 col-lg-4 control-label">
			{{Debug étendu}}
			<sup><i class="fas fa-question-circle" title="{{Niveau debug étendu pour le démon (très verbeux)}}"></i></sup>
		    </label>
		    <span class="col-xs-12 col-sm-9 col-md-7 col-lg-8" style="padding:0 !important"><input class="configKey form-control" type="checkbox" data-l1key="extendedDebug"/> ({{Nécessite un redémarrage du démon}})</span>
		</div>
	    </fieldset>

	</div> <!-- partie gauche -->

	<div class='col-md-6 col-sm-12'> <!-- partie droite -->
	    <legend><i class="fas fa-shield-alt"></i> {{Sécurité}}:</legend>
	    <fieldset>
		<div class="row">
		    <label class="col-sm-3 col-md-5 col-lg-4 control-label">
			{{Log non sécurisés}}
			<sup><i class="fas fa-question-circle" title="{{Passwords et autres données sensibles en clair}}"></i></sup>
		    </label>
		    <span class="col-xs-12 col-sm-9 col-md-7 col-lg-8" style="padding:0 !important"><input class="configKey form-control" type="checkbox" data-l1key="unsecurelog"/> ({{Nécessite un redémarrage du démon}})</span>
		</div>
	    </fieldset>
	</div> <!-- partie droite -->
    </div>
</form>

