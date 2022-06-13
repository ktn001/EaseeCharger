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

<div id="selectAccountModel">
  <form class="form-horizontal">
    <fieldset>
      <label class="control-label">{{Model d'account}}:</label>
      <select class="toto">
      </select>
    </fieldset>
  </form>
</div>

<script>
function selectAccountModel_actualizeModels() {
    $.ajax({
        type: 'POST',
        url: 'plugins/EaseeCharger/core/ajax/EaseeCharger.ajax.php',
        data: {
            action: 'modelLabels',
            onlyEnabled: 1,
        },
        dataType: 'json',
        global: false,
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
        },
	success: function (data) {
            if (data.state != 'ok') {
                $('#div_alert').showAlert({message: data.result, level: 'danger'});
                return;
            }
            $('#selectAccountModel select').empty();
            labels = json_decode(data.result);
            for (modelId in labels) {
                option = '<option value="' + modelId + '">' + labels[modelId] + '</option>';
                $('#selectAccountModel select').append(option);
            }
        },
    });
}

function selectAccountModel(action) {
    if (action = 'result') {
        return $('#mod_selectAccountModel select').value();
    }
}

$('#selectAccountModel').parent().closest('div').dialog({
    focus: function (event, ui) {
        selectAccountModel_actualizeModels();
    }
})

</script>
