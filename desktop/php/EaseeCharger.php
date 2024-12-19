<?php
if (!isConnect('admin')) {
     throw new Exception('{{401 - Accès non autorisé}}');
}
//  Déclaration des variables obligatoires
$plugin = plugin::byId('EaseeCharger');
$accounts = EaseeAccount::all();
$chargers = eqLogic::byType($plugin->getId());

// Déclaration de variables pour javasctipt
sendVarToJS('eqType', $plugin->getId());
?>

<div class="row row-overflow">
    <!-- ======================== -->
    <!-- Page d'accueil du plugin -->
    <!-- ======================== -->
    <div class="col-xs-12 eqLogicThumbnailDisplay">

	<!-- Boutons de gestion du plugin -->
	<!-- ============================ -->
	<legend><i class="fas fa-cog"></i>  {{Gestion}}</legend>
	<div class="eqLogicThumbnailContainer">
	    <div class="cursor accountAction logoPrimary" data-action="add">
		<i class="fas fa-user-plus"></i>
		<br>
		<span>{{Ajouter un compte}}</span>
	    </div>
	    <div class="cursor eqLogicAction logoPrimary" data-action="add">
		<i class="fas fa-charging-station"></i>
		<br>
		<span>{{Ajouter un chargeur}}</span>
	    </div>
	    <div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
		<i class="fas fa-wrench"></i>
		<br>
		<span>{{Configuration}}</span>
	    </div>
	</div> <!-- Boutons de gestion du plugin -->

	<!-- Les comptes -->
	<!-- =========== -->
	<legend><i class="fas fa-user"></i> {{Mes comptes}}</legend>
	<!-- Champ de recherche des comptes -->
	<div class="input-group">
	    <input class="form-control roundedLeft" placeholder="{{Rechercher}}" id="in_searchAccount"/>
	    <div class="input-group-btn">
		<a id="bt_resetSearch" class="btn roundedRight" style="width:30px"><i class="fas fa-times"></i></a>
		<a class="btn roundedRight hidden" id="bt_pluginDisplayAsTable" data-coreSupport="1" data-state="0"><i class="fas fa-grip-lines"></i></a>
	    </div>
	</div> <!-- Champ de recherche des comptes -->
	<!-- Liste des comptes -->
	<div class="eqLogicThumbnailContainer" data-type="account">
	    <?php
	    foreach ($accounts as $account) {
		echo '<div class="accountDisplayCard cursor" data-account_id="' . $account->getId() . '">';
		echo '<img src="/plugins/EaseeCharger/desktop/img/account.png" style="width:unset !important"/>';
		echo '<br>';
		echo '<span class="name">';
		echo $account->getName();
		echo '</span>';
		echo '<span class="displayTableRight hiddenAsCard hidden">' . __('Login',__FILE__) . ': <strong class="accountLogin">' . $account->getLogin() . '</strong></span>';
		echo '</div>' . PHP_EOL;
	    }
	    ?>
	</div> <!-- Liste des comptes -->

	<!-- Les chargeurs -->
	<!-- ============= -->
	<legend><i class="fas fa-charging-station"></i> {{Mes chargeurs}}</legend>
	<!-- Champ de recherche des chargeurs -->
	<div class="input-group">
	    <input class="form-control roundedLeft" placeholder="{{Rechercher}}" id="in_searchEqlogic"/>
	    <div class="input-group-btn">
		<a id="bt_resetSearch" class="btn roundedRight" style="width:30px"><i class="fas fa-times"></i></a>
		<a class="btn roundedRight hidden" id="bt_pluginDisplayAsTable" data-coreSupport="1" data-state="0"><i class="fas fa-grip-lines"></i></a>
	    </div>
	</div> <!-- Champ de recherche des chargeurs -->
	<!-- Liste des chargeurs -->
	<div class="eqLogicThumbnailContainer">
	    <?php
	    foreach ($chargers as $charger) {
		$opacity = ($charger->getIsEnable()) ? '' : 'disableCard';
		echo '<div class="eqLogicDisplayCard cursor '.$opacity.'" data-eqLogic_id="' . $charger->getId() . '">';
		echo '<img src="' . $charger->getPathImg() . '" style="width:unset !important"/>';
		echo '<br>';
		echo '<span class="name">';
		echo $charger->getHumanName(true, true);
		echo '</span>';
		echo '<span class="displayTableRight hiddenAsCard hidden">' . __('Compte',__FILE__) . ': <strong>' . EaseeAccount::byId($charger->getAccountid())->getName() . '</strong></span>';
		echo '</div>';
	    }
	    ?>
	</div> <!-- Liste des chargeurs -->

    </div> <!-- Page d'accueil du plugin -->

    <!-- ==================================== -->
    <!-- Pages de configuration des chargeurs -->
    <!-- ==================================== -->
    <div class="col-xs-12 eqLogic" style="display: none;">

	<!-- barre de gestion des chargeurs et véhicules -->
	<!-- =========================================== -->
	<div class="input-group pull-right" style="display:inline-flex;">
	    <span class="input-group-btn">
		<!-- Les balises <a></a> sont volontairement fermées à la ligne suivante pour éviter les espaces entre les boutons. Ne pas modifier -->
		<a class="btn btn-sm btn-default eqLogicAction roundedLeft" data-action="configure"><i class="fas fa-cogs"></i><span class="hidden-xs"> {{Configuration avancée}}</span>
		</a><a class="btn btn-sm btn-default eqLogicAction" data-action="copy"><i class="fas fa-copy"></i><span class="hidden-xs"> {{Dupliquer}}</span>
		</a><a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}
		</a><a class="btn btn-sm btn-danger eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}
		</a>
	    </span>
	</div> <!-- barre de gestion du chargeur -->

	<!-- Les onglets des chargeurs -->
	<!-- ========================= -->
	<ul class="nav nav-tabs" role="tablist">
	    <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
	    <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-charging-station"></i><span class="hidden-xs"> {{Chargeur}}</span></a></li>
	    <li role="presentation"><a href="#commandtab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-list"></i><span class="hidden-xs"> {{Commandes}}</span></a></li>
	</ul>

	<!-- Les panneaux -->
	<!-- ============ -->
	<div class="tab-content">

	    <!-- Tab de configuration d'un chargeur -->
	    <!-- ================================== -->
	    <div role="tabpanel" class="tab-pane active" id="eqlogictab">
		<!-- Paramètres généraux de l'équipement -->
		<form class="form-horizontal">
		    <fieldset>

			<!-- Partie gauche de l'onglet "Equipements" -->
			<div class="col-lg-6">
			    <legend><i class="fas fa-wrench"></i> {{Général}}</legend>
			    <div class="form-group">
				<label class="col-sm-3 control-label">{{Nom du chargeur}}</label>
				<div class="col-sm-7">
				    <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;"/>
				    <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom du chargeur}}"/>
				</div>
			    </div>
			    <div class="form-group">
				<label class="col-sm-3 control-label" >{{Objet parent}}</label>
				<div class="col-sm-7">
				    <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
					<option value="">{{Aucun}}</option>
					<?php
					$options = '';
					foreach ((jeeObject::buildTree(null, false)) as $object) {
					    $options .= '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
					}
					echo $options;
					?>
				    </select>
				</div>
			    </div>
			    <div class="form-group">
				<label class="col-sm-3 control-label">{{Catégorie}}</label>
				<div class="col-sm-7">
				    <?php
				    foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
					echo '<label class="checkbox-inline">';
					echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
					echo '</label>';
				    }
				    ?>
				</div>
			    </div>
			    <div class="form-group">
				<label class="col-sm-3 control-label">{{Options}}</label>
				<div class="col-sm-7">
				    <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked/>{{Activer}}</label>
				    <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>{{Visible}}</label>
				</div>
			    </div>
			    <br>

			    <legend><i class="fas fa-cogs"></i> {{Paramètres}}</legend>

			    <!-- Compte -->
			    <div class='form-group'>
				<label class="col-sm-3 control-label">{{Compte}}</label>
				<div class="col-sm-7">
				    <select id="selectAccount" class="eqLogicAttr" data-l1key="configuration" data-l2key="accountId">
					<option value=''> -- <?= __('Sélectionez un compte',__FILE__); ?> -- </option>
					<?php
					foreach ($accounts as $account) {
						$name =  $account->getName();
						$id = $account->getId();
						echo ("<option value='" . $id . "'>" . $name . "</option>");
					}
					?>
				    </select>
				</div>
			    </div>

			    <!-- Numéro de série -->
			    <div class='form-group'>
				<label class="col-sm-3 control-label">{{N° de série}}</label>
				<div class="col-sm-7">
				    <input type="text" class="eqLogicAttr form-control" data-l1key="logicalId"/>
				</div>
			    </div>

			    <!-- Choix du widget -->
			    <div class='form-group'>
				<label class="col-sm-3 control-label">{{Widget personnalisé}}</label>
				<div class="col-sm-7">
				    <input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="widget_perso"/>
				</div>
			    </div>
			   
			</div> <!-- Partie gauche de l'onglet "Equipements" -->

			<!-- Partie droite de l'onglet "Équipement" -->
			<div class="col-lg-6">
			    <!-- Informations des chargeurs -->
			    <legend><i class="fas fa-palette"></i> {{Couleur}}</legend>
			    <div class="form-group">
				<div class="text-center">
				    <img id="charger_icon_visu" src="/plugins/EaseeCharger/desktop/img/charger.png" style="max-width:160px;"/>
				    <select id="selectChargerImg" class="eqLogicAttr" data-l1key="configuration" data-l2key="image">
					<option value=''> -- <?= __('Sélectionez une couleur',__FILE__); ?> -- </option>
					<option value='/plugins/EaseeCharger/desktop/img/charger_noir.png'><?= __('noir',__FILE__) ?></option>
					<option value='/plugins/EaseeCharger/desktop/img/charger_bleu.png'><?= __('bleu',__FILE__) ?></option>
					<option value='/plugins/EaseeCharger/desktop/img/charger_blanc.png'><?= __('blanc',__FILE__) ?></option>
					<option value='/plugins/EaseeCharger/desktop/img/charger_gris.png'><?= __('gris',__FILE__) ?></option>
					<option value='/plugins/EaseeCharger/desktop/img/charger_rouge.png'><?= __('rouge',__FILE__) ?></option>
				    </select>
				</div>
			    </div>
			</div> <!-- Partie droite de l'onglet "Équipement" -->

		    </fieldset>
		</form>
	    </div> <!-- Tab de configuration d'un chargeur -->

	    <!-- Onglet des commandes d'un chargeur -->
	    <!-- ================================== -->
	    <div role="tabpanel" class="tab-pane" id="commandtab">
		<a class="btn btn-default btn-sm pull-right cmdAction" data-action="recreateCmds" style="margin-top:5px;"><i class="fas fa-magic"></i> {{Mettre à jours les commandes}}</a>
		<br/><br/>
		<div class="table-responsive">
		    <table id="table_cmd" class="table table-bordered table-condensed">
			<thead>
			    <tr>
				<th class="hidden-xs" style="min-width:50px;width:70px"> ID</th>
				<th style="min-width:200px;width:280px">{{Nom}}</th>
				<th style="width:80px">{{Type}}</br>{{Sous-type}}</th>
				<th style="width:180px">LogicalId</th>
				<th>{{Valeur}}</th>
				<th style="min-width:260px;width:280px">{{Options}}</th>
				<th style="width:100px">{{Etat}}</th>
				<th style="min-width:80px;width:140px">{{Action}}</th>
			    </tr>
			</thead>
			<tbody>
			</tbody>
		    </table>
		</div>
	    </div> <!-- Onglet des commandes d'un chargeur -->

	</div> <!-- Les panneaux -->
    </div> <!-- Pages de configuration des chargeurs et véhicules -->
</div><!-- /.row row-overflow -->

<!-- Inclusion du fichier javascript du core - NE PAS MODIFIER NI SUPPRIMER -->
<?php include_file('core', 'plugin.template', 'js');?>
<!-- Inclusion du fichier javascript du plugin (dossier, nom_du_fichier, extension_du_fichier, id_du_plugin) -->
<?php include_file('desktop', 'EaseeCharger', 'js', 'EaseeCharger');?>
<?php include_file('desktop', 'EaseeCharger', 'css', 'EaseeCharger');?>
