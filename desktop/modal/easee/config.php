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

if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
?>

<div id="easee_config">
  <form class="form-horizontal">
    <fieldset>
      <div class="form-group">
        <legend class"cal-sm-12"><i class="fas fa-university"></i> {{Démon}}:</legend>
        <label class="control-label col-sm-5">{{log complet}}:</label>
        <input class="configKey" data-l1key='TOTO' type="checkbox"></input>
        <sup><i class="fas fa-question-circle" title="{{Le daemon log aussi la communication avec le cloud (très verbeux en mode debug)}}"></i></sup>
      </div>
    </fieldset>
  </form>
</div>

<script>

$("#easee_config sup i").tooltipster();

</script>
