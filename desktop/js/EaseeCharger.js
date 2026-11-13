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
      /* ** CLICK ** */
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

        // Recreate Cmds
        if (_target = event.target.closest(".cmdAction[data-action=recreateCmds]")) {
          EaseeChargerFrontEnd.recreateCmds()
        }
      })

      /* ** CHANGE ** */
      document.getElementById("div_pageContainer").addEventListener("change", function (event) {
        let _target = null

        /* Changement d'image */
        if (_target = event.target.closest('#selectChargerImg')) {
          EaseeChargerFrontEnd.changeImg(_target.value)
        }

        /* Changement de la commande liée d'une action */
        if (_target = event.target.closest('.cmdAttr[data-l1key=value]')) {
          EaseeChargerFrontEnd.changeLinkedCmd(_target)
        }

        /* Chagement d'un nom de commande */
        if (_target = event.target.closest('.cmdAttr[data-l1key=name]')) {
          EaseeChargerFrontEnd.changeCmdName(_target)
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
        height: 220,
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
                    let option = document.querySelector('#selectAccount option[value="' + account.id + '"]')
                    if (option) {
                      option.remove()
                    }
                  }
                })
              }
            }
          },
          confirm: {
            callback: {
              click: function(event) {
                domUtils.showLoading();
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
                      domUtils.hideLoading()
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

    /*
     * Changement du nom d'une commande
     */
    changeCmdName: function(name_el) {
      let name = name_el.jeeValue()
      let id = name_el.closest('tr').querySelector('.cmdAttr[data-l1key=id]').jeeValue()
      document.getElementById('table_cmd').querySelectorAll('.cmdAttr[data-l1key=value]').forEach(function(value_el) {
        if (value_el.jeeValue() == id) {
          value_el.closest('tr').querySelector('.linkedcmd').jeeValue(name)
        }
      })
    },

    /*
     * Changement de l'ID d'une commande liée à un action
     */
    changeLinkedCmd: function(id_el) {
      let linkedCmdId = id_el.jeeValue()
      if (linkedCmdId == '') {
        return
      }
      let selector = ('tr.cmd[data-cmd_id="' + linkedCmdId + '"] .cmdAttr[data-l1key=name]')
      let name_el = document.getElementById('table_cmd').querySelector(selector)
      if (name_el == null) {
        return
      }
      let name = name_el.jeeValue()
      id_el.closest('tr').querySelector(".linkedcmd").jeeValue(name)
    },

    /*
     * Changement de l'image du chargeur
     */
    changeImg: function(img) {
      if (img == '') {
        img = "/plugins/EaseeCharger/plugin_info/EaseeCharger_icon.png"
      }
      document.getElementById('charger_icon_visu').setAttribute("src",img)
    },

    /*
     * Recréation de commandes
     */
    recreateCmds: function() {
      if (jeedomUtils.checkPageModified()) {
        return;
      }
      domUtils.ajax({
        type: "POST",
        async: false,
        global: false,
        url: "plugins/EaseeCharger/core/ajax/EaseeCharger.ajax.php",
        data: {
          action: "createCmds",
          id: $(".eqLogicAttr[data-l1key=id]").value(),
        },
        dataType: "json",
        success: function (data) {
          if (data.state != "ok") {
            jeedomUtils.showAlert({ message: data.result, level: "danger" });
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
    },

    /*
     * Fonction permettant l'affichage des commandes dans l'équipement
     */
    addCmdToTable: function(_cmd) {
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
      tr += '  <input class="cmdAttr form-control input-sm roundLeft" data-l1key="name" placeholder="{{Nom de la commande}}">';
      tr += '<span class="input-group-btn"><a class="cmdAction btn btn-sm btn-default" data-l1key="chooseIcon" title="{{Choisir une icône}}"><i class="fas fa-icons"></i></a></span>';
      tr += '<span class="cmdAttr input-group-addon roundedRight" data-l1key="display" data-l2key="icon" style="font-size:19px;padding:0 5px 0 0!important;"></span>';
      tr += "</div>";
      if (_cmd.type == "action") {
        tr += '  <input class="cmdAttr form-control input-sm hidden" data-l1key="value" style="margin-top:5px" title="{{Commande information liée}}">';
        tr += '  <input class="form-control input-sm linkedcmd" readonly style="margin-top:5px" title="{{Commande information liée}}">';
      }
      tr += "</td>";
      tr += "<td>";
      tr += '<input class="cmdAttr form-control input-sm" data-l1key="type" style="width:100%; margin-bottom:3px;" readonly>';
      tr += '<input class="cmdAttr form-control input-sm" data-l1key="subType" style="width:100%;" readonly>';
      tr += "</td>";
      tr += "<td>";
      tr += '  <input class="cmdAttr form-control input-sm" data-l1key="logicalId" style="margin-top:5px" readonly>';
      tr += "<td>";
      tr += '  <input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="description" style="width:100%; margin-bottom:3px;" readonly>'
      tr += "</td>";
      tr += "<td>";
      tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label>';
      tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label>';
      tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label>';
      tr += '<div style="margin-top:7px">';
      tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min.}}" title="{{Min.}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px"/>';
      tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max.}}" title="{{Max.}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px"/>';
      tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="unite" placeholder="{{Unité}}" title="{{Unité}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;display:inline-block;margin-right:2px"/>';
      tr += "</div>";
      tr += "</td>";
      tr += "<td>";
      tr += '<span class="cmdAttr" data-l1key="htmlstate"></span>';
      tr += "</td>";
    
      tr += "<td>";
      if (is_numeric(_cmd.id)) {
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
        if (_cmd.type == "action") {
          tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> Tester</a>';
        }
      }
      if (!isStandard || _cmd.configuration.required == "optional") {
        tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>';
      }
      tr += "</td>";
      tr += "</tr>";

      let newRow = document.createElement('tr')
      newRow.innerHTML = tr
      newRow.addClass("cmd")
      newRow.setAttribute("data-cmd_id", init(_cmd.id))
      document.getElementById("table_cmd").querySelector("tbody").appendChild(newRow)
      newRow.setJeeValues(_cmd, '.cmdAttr')
    }
  }

}
EaseeChargerFrontEnd.init()  
addCmdToTable = EaseeChargerFrontEnd.addCmdToTable
