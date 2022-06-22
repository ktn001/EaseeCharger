
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
	opacity = 'disableCard';
	if (account['isEnable'] == 1){
		opacity = '';
	}
	card =  '<div class="accountDisplayCard cursor ' + opacity + '" data-account_id="' + account.name + '">';
	card += '<img src="/plugins/EaseeCharger/desktop/img/account.png" style="width:unset !important"/>';
	card += '<br>';
	card += '<span class="name">' + account['name'] + '</span>';
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
							$('.accountDisplayCard[data-account_id=' + name + ']').remove();
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
							card = $('.accountDisplayCard[data-account_id=' + data['account']['name'] + ']'); 
							if (card.length == 1) {
								if (data['account']['isEnable'] == 1) {
									card.removeClass('disableCard');
								} else {
									card.addClass('disableCard');
								}
							} else {
								card = buildAccountCard(data['account']);
								cards = $('.eqLogicThumbnailContainer[data-type=account] .accountDisplayCard');
								nbCards = cards.length;
								if (nbCards == 0) {
									$('.eqLogicThumbnailContainer[data-type=account]').append(card);
								} else {
									for (let i=0; i<nbCards; i++) {
										n = $(cards[i]).attr('data-account_id');
										if ( name.toLowerCase() < n.toLowerCase() ) {
											$(cards[i]).before(card);
											break;
										}
										if (i == (nbCards -1)) {
											$(cards[i]).after(card);
										}
									}
								}
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
	bootbox.prompt('{{Nom de du compte}}', function(result) {
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
	editAccount($(this).data('account_id'));
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
	let isStandard = false;
	if ('required' in _cmd.configuration) {
		isStandard = true;
	}
	let  tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
	tr += '<td class="hidden-xs">';
	tr += '<span class="cmdAttr" data-l1key="id"></span>';
	tr += '</td>';
	tr += '<td>';
	tr += '  <input class="cmdAttr form-control input-sm" data-l1key="name" placeholder="{{Nom de la commande}}" style="margin-bottom:3px">';
	if (isStandard) {
		tr += '  <input class="cmdAttr form-control input-sm" data-l1key="logicalId" style="margin-top:5px" disabled>';
	}
	tr += '</td>';
	tr += '<td>';
	tr += '  <a class="cmdAction btn btn-default btn-sm" data-l1key="chooseIcon"><i class="fas fa-flag"></i> {{Icône}}</a>';
	tr += '  <span class="cmdAttr" data-l1key="display" data-l2key="icon" style="margin-left : 10px;"></span>';
	tr += '  <select class="cmdAttr form-control input-sm" data-l1key="value" style="display : none;margin-top : 5px;" title="{{Commande information liée}}">';
	tr += '	<option value="">{{Aucune}}</option>';
	tr += '  </select>';
	tr += '</td>';
	tr += '<td>';
	if (isStandard ) {
		tr += '<input class="cmdAttr form-control input-sm" data-l1key="type" style="width:120px; margin-bottom:3px" disabled>';
		tr += '<input class="cmdAttr form-control input-sm" data-l1key="subType" style="width:120px; margin-top:5px" disabled>';
	} else {
		tr += '<span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>';
		tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
	}
	tr += '</td>';
	tr += '<td>';
	if (_cmd.type == 'info') {
		if (_cmd.configuration.hasOwnProperty('source')) {
			source = _cmd.configuration.source
			if (source == 'calcul') {
				tr += '<textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="calcul" style="height:35px"></textarea>';
				tr += '<a class="btn btn-default listEquipementInfo btn-xs" data-input="calcul" style="width:100%;margin-top:5px"><i class="fas fa-list-alt"></i> {{Rechercher équipement}}</a>'
			} else  if (source == 'info') {
				tr += '<div class="input-group" style="margin-bottom:5px">';
				tr += '  <input type="text" class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="calcul"></input>'
				tr += '   <span class="input-group-btn">';
				tr += '	<a class="btn btn-default btn-sm listEquipementAction roundedRight" data-input="calcul">';
				tr += '	  <i class="fas fa-list-alt"></i>';
				tr += '	</a>';
				tr += '  </span>';
				tr += '</div>';
			}
		} else if (!isStandard) {
			tr += '<textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="calcul" style="height:35px"></textarea>';
			tr += '<a class="btn btn-default listEquipementInfo btn-xs" data-input="calcul" style="width:100%;margin-top:5px"><i class="fas fa-list-alt"></i> {{Rechercher équipement}}</a>'
		}
	} else if (_cmd.type == 'action') {
		if (_cmd.configuration.hasOwnProperty('destination')) {
			destination = _cmd.configuration.destination
			if (destination == 'cmd') {
				tr += '<div class="input-group" style="margin-bottom:5px">';
				tr += '  <input type="text" class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="destId"></input>'
				tr += '   <span class="input-group-btn">';
				tr += '	<a class="btn btn-default btn-sm listEquipementAction roundedRight" data-input="destId">';
				tr += '	  <i class="fas fa-list-alt"></i>';
				tr += '	</a>';
				tr += '  </span>';
				tr += '</div>';
			}
		} else if (!isStandard) {
			tr += '<div class="input-group" style="margin-bottom:5px">';
			tr += '  <input type="text" class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="destId"></input>'
			tr += '   <span class="input-group-btn">';
			tr += '	<a class="btn btn-default btn-sm listEquipementAction roundedRight" data-input="destId">';
			tr += '	  <i class="fas fa-list-alt"></i>';
			tr += '	</a>';
			tr += '  </span>';
			tr += '</div>';
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
	if (isStandard){
		tr.find('.cmdAttr[data-l1key=unite]:visible').prop('disabled',true);
		tr.find('.cmdAttr[data-l1key=configuration][data-l2key=minValue]:visible').prop('disabled',true);
		tr.find('.cmdAttr[data-l1key=configuration][data-l2key=maxValue]:visible').prop('disabled',true);
	}
	jeedom.eqLogic.buildSelectCmd({
		id:  $('.eqLogicAttr[data-l1key=id]').value(),
		filter: {type: 'info'},
		error: function (error) {
			$.fn.showAlert({message: error.message, level: 'danger'});
		},
		success: function (result) {
			tr.find('.cmdAttr[data-l1key=value]').append(result);
			tr.setValues(_cmd, '.cmdAttr');
			jeedom.cmd.changeType(tr, init(_cmd.subType));
		}
	});
}

/*
 * Chargement de la liste des choix des accounts
 */
function loadSelectAccount(defaut) {
	$.ajax({
		type: 'POST',
		url: 'plugins/EaseeCharger/core/ajax/account.ajax.php',
		data: {
			action: 'getAccountToSelect',
			modelId: $('.eqLogicAttr[data-l1key=configuration][data-l2key=modelId]').value(),
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
			$('#selectAccount').empty();
			datas = json_decode(data.result);
			content = "";
			for (let data of datas) {
				content += '<option value="' + data.id + '">' + data.value + '</option>';
			}
			$('#selectAccount').append(content).val(defaut).trigger('change');
		}
	});
}

function prePrintEqLogic (id) {
	let displayCard = $('.eqLogicDisplayCard[data-eqlogic_id=' + id + ']')
	let type = displayCard.attr('data-eqlogic_type');
	$('#account_icon_visu, #charger_icon_visu').attr('src','')
	$('.tab-EaseeCharger_xaccount, .tab-EaseeCharger_charger').hide()
	$('.EaseeCharger_xaccountAttr, .EaseeCharger_chargerAttr').removeClass('eqLogicAttr')
	if (type =='EaseeCharger_charger') {
		$('.tab-EaseeCharger_charger').show()
		$('.EaseeCharger_chargerAttr').addClass('eqLogicAttr')
		modelId = displayCard.attr('data-eqlogic_modelId');
		$.ajax({
			type: 'POST',
			url: 'plugins/EaseeCharger/core/ajax/EaseeCharger.ajax.php',
			data: {
				action: 'ParamsHtml',
				object: 'charger',
				modelId: modelId
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
				let html = data.result;
				$('#ChargerSpecificsParams').html(html);
			}
		});
	}
}

