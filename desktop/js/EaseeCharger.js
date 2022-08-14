
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

/*
 * Permet la réorganisation des commandes dans l'équipement et des accounts
 */
$('#table_cmd').sortable({
	axis: 'y',
	cursor: 'move',
	items: '.cmd',
	placeholder: 'ui-state-highlight',
	tolerance: 'intersect',
	forcePlaceholderSize: true
});

$('#table_cmd').on('sortupdate',function(event,ui){
	modifyWithoutSave = true;
});

/*
 * Construction d'une accountCard
 */
function buildAccountCard(account) {
	displayAsTable = '';
	hiddenAsCard = 'hidden';
	if (getCookie('jeedom_displayAsTable') == 'true' || jeedom.theme.theme_displayAsTable == 1) {
		displayAsTable = 'displayAsTable';
		hiddenAsCard = '';
	}
	opacity = 'disableCard ';
	if (account['isEnable'] == 1){
		opacity = '';
	}
	card =  '<div class="accountDisplayCard cursor ' + opacity + displayAsTable + '" data-account_name="' + account.name + '">';
	card += '<img src="/plugins/EaseeCharger/desktop/img/account.png" style="width:unset !important"/>';
	card += '<br>';
	card += '<span class="name">' + account['name'] + '</span>';
	card += '<span class="displayTableRight hiddenAsCard ' + hiddenAsCard + '">{{Login}}: <strong class="accountLogin">' + account['login'] + '</strong></span>';
	card += '</div>';
	return card;
}

/*
 * Chargement de la config d'un account
 */
function loadAccount(accountName) {
	$.ajax({
		type: 'POST',
		url: 'plugins/EaseeCharger/core/ajax/EaseeCharger.ajax.php',
		data: {
			action: 'getAccount',
			name: accountName,
		},
		dataType: 'json',
		global: false,
		error: function(request, status, error) {
			handleAjaxError(request, status, error);
		},
		success: function(data) {
			if (data.state != 'ok') {
				$.fn.showAlert({message: data.result, level: 'danger'});
				return;
			}
			return json_decode(data.result);
		}
	})
}

/*
 * Edition d'un compte
 */
function editAccount(name) {
	if ($('#modContainer_editAccount').length == 0) {
		$('body').append('<div id="modContainer_editAccount"></dev');
		jQuery.ajaxSetup({async: false});
		$('#modContainer_editAccount').load('index.php?v=d&plugin=EaseeCharger&modal=editAccount');
		jQuery.ajaxSetup({async: true});
		$('#modContainer_editAccount').dialog({
			closeText: '',
			autoOpen: false,
			modal: true,
			height: 260,
			width: 400
		});
	}
	$.ajax({
		type: 'POST',
		url: 'plugins/EaseeCharger/core/ajax/EaseeCharger.ajax.php',
		data: {
			action: 'getAccount',
			name: name,
		},
		dataType: 'json',
		global: false,
		error: function(request, status, error) {
			handleAjaxError(request, status, error);
		},
		success: function(data) {
			if (data.state != 'ok') {
				$.fn.showAlert({message: data.result, level: 'danger'});
				return;
			}
			$('#modContainer_editAccount').setValues(json_decode(data.result),'.accountAttr');
			$('#modContainer_editAccount').dialog({title: '{{Compte}}: ' + name});
			$('#modContainer_editAccount').dialog('option', 'buttons', [{
				text: "{{Annuler}}",
				click: function() {
					$(this).dialog("close");
				}
			},
			{
				text: "{{Supprimer}}",
				class: "btn-delete",
				click: function() {
					$.ajax({
						type: 'POST',
						url: 'plugins/EaseeCharger/core/ajax/EaseeCharger.ajax.php',
						data: {
							action : 'removeAccount',
							name : name,
						},
						dataType: 'json',
						global: false,
						error: function(request, status, error) {
							handleAjaxError(request, status, error);
						},

						success: function(data) {
							if (data.state != 'ok') {
								$.fn.showAlert({message: data.result, level: 'danger'});
								return;
							}
							$('.accountDisplayCard[data-account_name=' + name + ']').remove();
							$('#selectAccount option[value=' + name + ']').remove();
						}
					});
					$(this).dialog("close");
				}
			},
			{
				text: "{{Valider}}",
				click: function() {
					account = $('#modContainer_editAccount').getValues('.accountAttr')[0];
					$.ajax({
						type: 'POST',
						url: 'plugins/EaseeCharger/core/ajax/EaseeCharger.ajax.php',
						data: {
							action : 'saveAccount',
							account : json_encode(account),
						},
						dataType: 'json',
						global: false,
						error: function(request, status, error) {
							handleAjaxError(request, status, error);
						},

						success: function(data) {
							if (data.state != 'ok') {
								$.fn.showAlert({message: data.result, level: 'danger'});
								return;
							}
							data = json_decode(data.result);
							console.log(data);

							// Traitement de la Card			
							card = $('.accountDisplayCard[data-account_name=' + data['account']['name'] + ']');
							if (card.length == 1) {
								// La card existe, on la met à jour
								if (data['account']['isEnable'] == 1) {
									card.removeClass('disableCard');
								} else {
									card.addClass('disableCard');
								}
								card.find('.accountLogin').html(data['account']['login']);
							} else {
								// Création d'une nouvelle Card
								card = buildAccountCard(data['account']);
								cards = $('.eqLogicThumbnailContainer[data-type=account] .accountDisplayCard');
								nbCards = cards.length;
								if (nbCards == 0) {
									$('.eqLogicThumbnailContainer[data-type=account]').append(card);
								} else {
									for (let i=0; i<nbCards; i++) {
										n = $(cards[i]).attr('data-account_name');
										if ( name.toLowerCase() < n.toLowerCase() ) {
											$(cards[i]).before(card);
											break;
										}
										if (i == (nbCards -1)) {
											$(cards[i]).after(card);
										}
									}
								}
							}

							// Traitement du sélecteur de compte pour les chargeurs
							options = $('#selectAccount option');
							nbOptions = options.length;
							option = "<option value='" + account['name'] + "'>" + account['name'] + "</option>";
							if (nbOptions == 0) {
								$('#selectAccount').append(option)
							} else {
								for (let i=0; i<nbOptions; i++) {
									n = $(options[i]).attr('value');
									if ( name.toLowerCase() < n.toLowerCase() ) {
										$(options[i]).before(option);
										break;
									}
									if (i == (nbOptions -1)) {
										$(options[i]).after(option);
									}
								}
							}

							// Traitement de chargeur désactivés avec le compte
							chargerIds = data['modifiedChargers'];
							for (chargerId of chargerIds) {
								$('.eqLogicDisplayCard[data-eqLogic_id=' + chargerId + ']').addClass('disableCard');
							}
						}
					});
					$(this).dialog("close");
				}
			}]);
			$('#modContainer_editAccount').dialog('open');
		}
	})
}

/*
 * Action du bouton d'ajout d'un compte
 */
$('.accountAction[data-action=add').off('click').on('click',function () {
	bootbox.prompt('{{Nom du compte}}', function(result) {
		if (result !== null) {
			$.ajax({
				type: 'POST',
				url: 'plugins/EaseeCharger/core/ajax/EaseeCharger.ajax.php',
				data: {
					action: 'createAccount',
					name: result
				},
				dataType : 'json',
				global : false,
				error: function (request, status, error) {
					handleAjaxError(request, status, error)
				},
				success: function (data) {
					if (data.state != 'ok') {
						$.fn.showAlert({message: data.result, level:'danger'});
						return
					}
					editAccount(result);
				}
			})
		}
	})
});

/*
 * Action sur AccountDisplayCard
 */
$('.eqLogicThumbnailContainer[data-type=account]').delegate('.accountDisplayCard','click',function() {
	editAccount($(this).data('account_name'));
})

/*
 * Action sur modification d'image d'un chargeur
 */
$('#selectChargerImg').on('change',function(){
	if ($(this).value() != '') {
		$('#charger_icon_visu').attr('src', $(this).value());
	} else {
		$('#charger_icon_visu').attr('src', '/plugins/EaseeCharger/desktop/img/charger.png');
	}
});

/*
 * mise à jour ou création des commandes
 */
function updateCmds ( action = "") {
	$.ajax({
		type: 'POST',
		url: 'plugins/EaseeCharger/core/ajax/EaseeCharger.ajax.php',
		data: {
			action: action,
			id:  $('.eqLogicAttr[data-l1key=id]').value(),
		},
		dataType : 'json',
		global:false,
		error: function (request, status, error) {
			handleAjaxError(request, status, error);
		},
		success: function (data) {
			if (data.state != 'ok') {
				$.fn.showAlert({message: data.result, level: 'danger'});
				return;
			}
			modifyWithoutSave = false
			let vars = getUrlVars()
			let url = 'index.php?'
			for (let i in vars) {
				if (i != 'saveSuccessFull' && i != 'removeSuccessFull') {
					url += i + '=' + vars[i] + '&'
				}
			}
			url += 'saveSuccessFull=1' + document.location.hash
			loadPage(url)
		}
	})
}

/*
 * Action sur recréation des commandes
 */
$('.cmdAction[data-action=createMissing]').on('click',function() {
	if (checkPageModified()) {
		return;
	}
	updateCmds ('createCmds')
})

/*
 * Action sur configuration des commandes
 */
$('.cmdAction[data-action=reconfigure]').on('click',function() {
	if (checkPageModified()) {
		return;
	}
	updateCmds ('updateCmds')
})

$('#table_cmd').delegate('.listEquipementAction', 'click', function(){
	var el = $(this)
	var type = $(this).closest('.cmd').find('.cmdAttr[data-l1key=type]').value()
	jeedom.cmd.getSelectModal({cmd: {type: type}}, function(result) {
		var calcul = el.closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=' + el.attr('data-input') + ']')
		//calcul.atCaret('insert',result.human)
		calcul.value(result.human)
	})
})

$('#table_cmd').delegate('.listEquipementInfo', 'click', function(){
	var el = $(this)
	jeedom.cmd.getSelectModal({cmd: {type: 'info'}},function (result) {
		var calcul = el.closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=' + el.data('input') + ']')
		calcul.atCaret('insert',result.human)
	})
})

/*
* Fonction permettant l'affichage des commandes dans l'équipement
*/
function addCmdToTable(_cmd) {
	if (!isset(_cmd)) {
		var _cmd = {configuration: {}}
	}
	if (!isset(_cmd.configuration)) {
		_cmd.configuration = {}
	}
	if (init(_cmd.logicalId) == 'refresh') {
		return
	}
	let isStandard = false;
	let  tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
	tr += '<td class="hidden-xs">';
	tr += '<span class="cmdAttr" data-l1key="id"></span>';
	tr += '</td>';
	tr += '<td>';
	tr += '  <input class="cmdAttr form-control input-sm" data-l1key="name" placeholder="{{Nom de la commande}}" style="margin-bottom:3px">';
	tr += '  <input class="cmdAttr form-control input-sm" data-l1key="logicalId" style="margin-top:5px" disabled>';
	tr += '</td>';
	tr += '<td>';
	tr += '  <a class="cmdAction btn btn-default btn-sm" data-l1key="chooseIcon"><i class="fas fa-flag"></i> {{Icône}}</a>';
	tr += '  <span class="cmdAttr" data-l1key="display" data-l2key="icon" style="margin-left : 10px;"></span>';
	if (_cmd.type == 'action') {
		tr += '  <input class="cmdAttr form-control input-sm" data-l1key="value" disabled style="margin-top:5px" title="{{Commande information liée}}">';
	}
	tr += '</td>';
	tr += '<td>';
	tr += '<input class="cmdAttr form-control input-sm" data-l1key="type" style="width:100%; margin-bottom:3px" disabled>';
	tr += '<input class="cmdAttr form-control input-sm" data-l1key="subType" style="width:100%; margin-top:5px" disabled>';
	tr += '</td>';
	tr += '<td>';
	if (_cmd.type == 'info') {
		if (_cmd.configuration.hasOwnProperty('calcul')) {
			tr += '<textarea class="cmdAttr form-control input-sm" disabled data-l1key="configuration" data-l2key="calcul" style="height:35px"></textarea>';
		}
	}
	tr += '</td>';
	tr += '<td>';
	tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label>';
	tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label>';
	tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label>';
	tr += '<div style="margin-top:7px">';
	tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min.}}" title="{{Min.}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px"/>';
	tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max.}}" title="{{Max.}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px"/>';
	tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="unite" placeholder="{{Unité}}" title="{{Unité}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;display:inline-block;margin-right:2px"/>';
	tr += '</div>';
	tr += '</td>';
	tr += '<td>';
	if (is_numeric(_cmd.id)) {
		tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
		tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> Tester</a>';
	}
	if (!isStandard || _cmd.configuration.required == 'optional') {
		tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>';
	}
	tr += '</td>';
	tr += '</tr>';
	$('#table_cmd tbody').append(tr);
	tr = $('#table_cmd tbody tr').last();
//	jeedom.eqLogic.buildSelectCmd({
//		id:  $('.eqLogicAttr[data-l1key=id]').value(),
//		filter: {type: 'info'},
//		error: function (error) {
//			$.fn.showAlert({message: error.message, level: 'danger'});
//		},
//		success: function (result) {
//			tr.find('.cmdAttr[data-l1key=value]').append(result);
//			tr.setValues(_cmd, '.cmdAttr');
//			jeedom.cmd.changeType(tr, init(_cmd.subType));
//		}
//	});
	tr.setValues(_cmd, '.cmdAttr');
}

function prePrintEqLogic (id) {
	$('#account_icon_visu, #charger_icon_visu').attr('src','')
}

//displayAsTable
if (getCookie('jeedom_displayAsTable') == 'true' || jeedom.theme.theme_displayAsTable == 1) {
	$('.accountDisplayCard').addClass('displayAsTable')
	$('.accountDisplayCard .hiddenAsCard').removeClass('hidden')
}
//core event:
$('#bt_pluginDisplayAsTable[data-coreSupport="1"]').on('click', function() {
	if ($(this).data('state') == "1") {
		$('.accountDisplayCard').addClass('displayAsTable')
		$('.accountDisplayCard .hiddenAsCard').removeClass('hidden')
	} else {
		$('.accountDisplayCard').removeClass('displayAsTable')
		$('.accountDisplayCard .hiddenAsCard').addClass('hidden')
	}
})
