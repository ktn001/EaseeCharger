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

<div id="mod_vehicleName">
  <form class="form-horizontal">
    <fieldset>
      <div class="form-group">
        <label class="control-label col-sm-3">{{Nom}}:</label>
        <input class="eqLogicAttr col-sm-8" data-l1key='name' type="text" placeholder="{{Nom}}"></input>
      </div>
    </fieldset>
  </form>
</div>

<script>
function mod_vehicleName(action) {
    if (action = 'result') {
        return $('#mod_vehicleName').getValues('.eqLogicAttr');
    }
}

</script>
