<?php
// vim: tabstop=4 autoindent
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
				<label class="col-sm-3 control-label">{{Nom}}:</label>
				<input type="text" class="accountAttr form-control col-sm-7 hidden" data-l1key="id"/>
				<input type="text" class="accountAttr form-control col-sm-7" data-l1key="name"/>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">{{Login}}:</label>
				<input type="text" class="accountAttr form-control col-sm-7" data-l1key="login"/>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">{{Password}}:</label>
				<div class="input-group col-sm-7" style="padding-right:0px !important; padding-left:0px !important">
					<input class="accountAttr form-control inputPassword roundedLeft" type="text" data-l1key="password"/>
					<span class="input-group-btn">
						<a class="btn btn-default form-control bt_showPass roundedRight"><i class="fas fa-eye"></i></a>
					</span>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">{{Activer}}:</label>
				<input type="checkbox" class="accountAttr form-control" data-l1key="isEnable"/>
			</div>
		</fieldset>
	</form>
</div>

<script>
"use strict"

if (typeof editEaseeAccount === "undefined") {
	var editEaseeAccount = {
		init: function(account) {
			let modal = document.getElementById(EaseeChargerFrontEnd.mdId_editAccount)
			modal.setJeeValues(account,'.accountAttr')
		},
		getAccount: function(mdId) {
            let modal = document.getElementById(EaseeChargerFrontEnd.mdId_editAccount)
            let account=modal.getJeeValues('.accountAttr')
            return account[0]
        },
		close: function() {
			document.getElementById(EaseeChargerFrontEnd.mdId_editAccount)._jeeDialog.close()
		},
	}
}

</script>
