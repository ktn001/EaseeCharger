// vim: tabstop=2 autoindent expandtab
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

"use strict";

if (typeof EaseeChargerFrontEnd === "undefined") {
  var EaseeChargerFrontEnd = {
    mdId_editAccount: "mod_editEaseeCharger",
    ajaxUrl: "plugins/EaseeCharger/core/ajax/EaseeCharger.ajax.php",

    /*
     * Initialisation après chargement de la page
     */
    init: function () {
      document.getElementById("div_pageContainer").addEventListener("click", function (event) {
        let _target = null
  
        // Ajout d'un account
        if (_target = event.target.closest('.accountAction[data-action=add]')) {
          EaseeChargerFrontEnd.editAccount()
        }

        // Account DisplayCard
        if (_target = event.target.closest('.accountDisplayCard')) {
          EaseeChargerFrontEnd.loadAndEditAccount(_target.getAttribute("data-account_id"))
        }
      })
    },
    
    /*
     * Upload et edition d'un compte 
     */
    loadAndEditAccount: function (id) {
      domUtils.ajax({
        type: "POST",
        async: false,
        global: false,
        url: EaseeChargerFrontEnd.ajaxUrl,
        data: {
          action: "getAccount",
          id: id,
        },
        dataType: "json",
        success: function (data) {
          if (data.state != 'ok') {
            jeedomUtils.showAlert({ message: data.result, level: "danger" })
            return
          }
          let account = json_decode(data.result)
          EaseeChargerFrontEnd.editAccount(account)
        }
      })
    },
        
    /*
     * Edition d'un compte
     */
    editAccount: function (account = null) {
      let title = ''
      if (account != null) {
        title = account.name
      }
     
      jeeDialog.dialog({
        id: EaseeChargerFrontEnd.mdId_editAccount,
        title: "{{Compte}}: " + title,
        height: 280,
        width: 400,
        contentUrl: "index.php?v=d&plugin=EaseeCharger&modal=editAccount",
        buttons: {
          cancel: {
            callback: {
              click: function (event) {
                editEaseeAccount.close()
              }
            }
          },
          delete: {
            label: '<i class="fa fa-times"></i> {{Supprimer}}',
            className: "danger",
            callback: {
              click: function (event) {
                let account = editEaseeAccount.getAccount()
                domUtils.ajax({
                  type: "POST",
                  async: false,
                  global: false,
                  url: EaseeChargerFrontEnd.ajaxUrl,
                  data: {
                    action: "removeAccount",
                    id: account.id,
                  },
                  dataType: 'json',
                  success: function (data) {
                    if (data.state != "ok") {
                      jeedomUtils.showAlert({
                        message: data.result,
                        level: "danger",
                      });
                      return;
                    }
                    let card = document.querySelector('.accountDisplayCard[data-account_id="' + account.id + '"]')
                    if (card) {
                      card.remove()
                    }
                    editEaseeAccount.close()
                  }
                })
              }
            }
          },
          confirm: {
            callback: {
              click: function (event) {
                let account = editEaseeAccount.getAccount()
                domUtils.ajax({
                  type: "POST",
                  async: false,
                  global: false,
                  url: EaseeChargerFrontEnd.ajaxUrl,
                  data: {
                    action: "saveAccount",
                    account: json_encode(account),
                  },
                  dataType: 'json',
                  success: function (data) {
                    if (data.state != "ok") {
                      jeedomUtils.showAlert({
                        message: data.result,
                        level: "danger",
                      });
                      return;
                    }
                    jeedomUtils.loadPage(document.URL);
                  }
                })
              }
            }
          },
        },
        callback: function() {
          editEaseeAccount.init(account)
        }
      })
    },
  }
}
EaseeChargerFrontEnd.init()  


















/*
 * Permet la réorganisation des commandes dans l'équipement et des accounts
 */
$("#table_cmd").sortable({
  axis: "y",
  cursor: "move",
  items: ".cmd",
  placeholder: "ui-state-highlight",
  tolerance: "intersect",
  forcePlaceholderSize: true,
});

$("#table_cmd").on("sortupdate", function (event, ui) {
  modifyWithoutSave = true;
});

/*
 * Construction d'une accountCard
 */
function buildAccountCard(account) {
  displayAsTable = "";
  hiddenAsCard = "hidden";
  if (
    getCookie("jeedom_displayAsTable") == "true" ||
    jeedom.theme.theme_displayAsTable == 1
  ) {
    displayAsTable = "displayAsTable";
    hiddenAsCard = "";
  }
  opacity = "disableCard ";
  if (account["isEnable"] == 1) {
    opacity = "";
  }
  card =
    '<div class="accountDisplayCard cursor ' +
    opacity +
    displayAsTable +
    '" data-account_name="' +
    account.name +
    '">';
  card +=
    '<img src="/plugins/EaseeCharger/desktop/img/account.png" style="width:unset !important"/>';
  card += "<br>";
  card += '<span class="name">' + account["name"] + "</span>";
  card +=
    '<span class="displayTableRight hiddenAsCard ' +
    hiddenAsCard +
    '">{{Login}}: <strong class="accountLogin">' +
    account["login"] +
    "</strong></span>";
  card += "</div>";
  return card;
}

/*
 * Action sur modification d'image d'un chargeur
 */
$("#selectChargerImg").on("change", function () {
  if ($(this).value() != "") {
    $("#charger_icon_visu").attr("src", $(this).value());
  } else {
    $("#charger_icon_visu").attr(
      "src",
      "/plugins/EaseeCharger/desktop/img/charger.png",
    );
  }
});

/*
 * mise à jour ou création des commandes
 */
function updateCmds(action = "") {}

/*
 * Action sur recréation des commandes
 */
$(".cmdAction[data-action=recreateCmds]").on("click", function () {
  if (jeedomUtils.checkPageModified()) {
    return;
  }
  $.ajax({
    type: "POST",
    url: "plugins/EaseeCharger/core/ajax/EaseeCharger.ajax.php",
    data: {
      action: "createCmds",
      id: $(".eqLogicAttr[data-l1key=id]").value(),
    },
    dataType: "json",
    global: false,
    error: function (request, status, error) {
      handleAjaxError(request, status, error);
    },
    success: function (data) {
      if (data.state != "ok") {
        $.fn.showAlert({ message: data.result, level: "danger" });
        return;
      }
      modifyWithoutSave = false;
      let vars = getUrlVars();
      let url = "index.php?";
      for (let i in vars) {
        if (i != "saveSuccessFull" && i != "removeSuccessFull") {
          url += i + "=" + vars[i] + "&";
        }
      }
      url += "saveSuccessFull=1" + document.location.hash;
      jeedomUtils.loadPage(url);
    },
  });
});

$("#table_cmd").delegate(".listEquipementAction", "click", function () {
  var el = $(this);
  var type = $(this).closest(".cmd").find(".cmdAttr[data-l1key=type]").value();
  jeedom.cmd.getSelectModal({ cmd: { type: type } }, function (result) {
    var calcul = el
      .closest("tr")
      .find(
        ".cmdAttr[data-l1key=configuration][data-l2key=" +
          el.attr("data-input") +
          "]",
      );
    //calcul.atCaret('insert',result.human)
    calcul.value(result.human);
  });
});

$("#table_cmd").delegate(".listEquipementInfo", "click", function () {
  var el = $(this);
  jeedom.cmd.getSelectModal({ cmd: { type: "info" } }, function (result) {
    var calcul = el
      .closest("tr")
      .find(
        ".cmdAttr[data-l1key=configuration][data-l2key=" +
          el.data("input") +
          "]",
      );
    calcul.atCaret("insert", result.human);
  });
});

/*
 * Fonction permettant l'affichage des commandes dans l'équipement
 */
function addCmdToTable(_cmd) {
  if (!isset(_cmd)) {
    var _cmd = { configuration: {} };
  }
  if (!isset(_cmd.configuration)) {
    _cmd.configuration = {};
  }
  let isStandard = false;
  let tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
  tr += '<td class="hidden-xs">';
  tr += '<span class="cmdAttr" data-l1key="id"></span>';
  tr += "</td>";
  tr += "<td>";
  tr += '<div class="input-group">';
  tr +=
    '  <input class="cmdAttr form-control input-sm roundLeft" data-l1key="name" placeholder="{{Nom de la commande}}">';
  tr +=
    '<span class="input-group-btn"><a class="cmdAction btn btn-sm btn-default" data-l1key="chooseIcon" title="{{Choisir une icône}}"><i class="fas fa-icons"></i></a></span>';
  tr +=
    '<span class="cmdAttr input-group-addon roundedRight" data-l1key="display" data-l2key="icon" style="font-size:19px;padding:0 5px 0 0!important;"></span>';
  tr += "</div>";
  if (_cmd.type == "action") {
    tr +=
      '  <input class="cmdAttr form-control input-sm" data-l1key="value" disabled style="margin-top:5px" title="{{Commande information liée}}">';
  }
  tr += "</td>";
  tr += "<td>";
  tr +=
    '<input class="cmdAttr form-control input-sm" data-l1key="type" style="width:100%; margin-bottom:3px" disabled>';
  tr +=
    '<input class="cmdAttr form-control input-sm" data-l1key="subType" style="width:100%; margin-top:5px" disabled>';
  tr += "</td>";
  tr += "<td>";
  tr +=
    '  <input class="cmdAttr form-control input-sm" data-l1key="logicalId" style="margin-top:5px" disabled>';
  tr += "<td>";
  if (_cmd.type == "info") {
    if (_cmd.configuration.hasOwnProperty("calcul")) {
      tr +=
        '<textarea class="cmdAttr form-control input-sm" disabled data-l1key="configuration" data-l2key="calcul" style="height:35px"></textarea>';
    }
  }
  tr += "</td>";
  tr += "<td>";
  tr +=
    '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label>';
  tr +=
    '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label>';
  tr +=
    '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label>';
  tr += '<div style="margin-top:7px">';
  tr +=
    '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min.}}" title="{{Min.}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px"/>';
  tr +=
    '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max.}}" title="{{Max.}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px"/>';
  tr +=
    '<input class="tooltips cmdAttr form-control input-sm" data-l1key="unite" placeholder="{{Unité}}" title="{{Unité}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;display:inline-block;margin-right:2px"/>';
  tr += "</div>";
  tr += "</td>";
  tr += "<td>";
  tr += '<span class="cmdAttr" data-l1key="htmlstate"></span>';
  tr += "</td>";

  tr += "<td>";
  if (is_numeric(_cmd.id)) {
    tr +=
      '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
    if (_cmd.type == "action") {
      tr +=
        '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> Tester</a>';
    }
  }
  if (!isStandard || _cmd.configuration.required == "optional") {
    tr +=
      '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>';
  }
  tr += "</td>";
  tr += "</tr>";
  $("#table_cmd tbody").append(tr);
  tr = $("#table_cmd tbody tr").last();
  tr.setValues(_cmd, ".cmdAttr");
}

function prePrintEqLogic(id) {
  $("#account_icon_visu, #charger_icon_visu").attr("src", "");
}

//displayAsTable
if (
  getCookie("jeedom_displayAsTable") == "true" ||
  jeedom.theme.theme_displayAsTable == 1
) {
  $(".accountDisplayCard").addClass("displayAsTable");
  $(".accountDisplayCard .hiddenAsCard").removeClass("hidden");
}
//core event:
$('#bt_pluginDisplayAsTable[data-coreSupport="1"]').on("click", function () {
  if ($(this).data("state") == "1") {
    $(".accountDisplayCard").addClass("displayAsTable");
    $(".accountDisplayCard .hiddenAsCard").removeClass("hidden");
  } else {
    $(".accountDisplayCard").removeClass("displayAsTable");
    $(".accountDisplayCard .hiddenAsCard").addClass("hidden");
  }
});
