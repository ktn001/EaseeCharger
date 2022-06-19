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

<div id="editAccount">
	<form class="form-horizontal">
		<fieldset>
			<div class="form-group">
				<label class="col-sm-3 control-label">{{Login}}:</label>
				<input type="text" class="config form-control col-sm-7" data-l1key="login"/>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">{{Password}}:</label>
				<div class="input-group col-sm-7" style="display:flex; padding-right:0px !important; padding-left:0px !important">
					<input type="password" class="config form-control" data-l1key="password"/>
					<button class="btn btn-outline-secondary show-txt" type="button"><i class="fas fa-eye"></i></button>
					<button class="btn btn-outline-secondary hide-txt" type="button" style="display:none"><i class="fas fa-eye-slash"></i></button>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">{{Activer}}:</label>
				<input type="checkbox" class="config form-control" data-l1key="isEnable"/>
			</div>
		</fieldset>
	</form>
</div>

<script>

$('#editAccount .show-txt').off().on('click', function() {
	$(this).closest('.input-group').find('input[type=password]').attr('type','text');
	$(this).hide();
	$(this).closest('.input-group').find('button.hide-txt').show();
})

$('#editAccount .hide-txt').off().on('click', function() {
	$(this).closest('.input-group').find('input[type=text]').attr('type','password');
	$(this).hide();
	$(this).closest('.input-group').find('button.show-txt').show();
})


</script>
