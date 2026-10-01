<?php
/* Copyright (C) 2026  Gael <gael@example.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * 	\defgroup   mailchimp     Module Mailchimp
 *  \brief      Mailchimp module descriptor (Mailchimp Connector for Dolibarr).
 *
 *  \file       core/modules/modMailchimp.class.php
 *  \ingroup    mailchimp
 *  \brief      Description and activation file for module @ModuleName
 */

// Put here all includes required by your module file
require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 *  Mailchimp module descriptor.
 */
class modMailchimp extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs;

		$this->db = $db;

		// Id for module (must be unique).
		// 421880: not in the reserved-ID range of core modules; verify against the
		// official reserved list (wiki "List of modules identifiers") before distribution.
		$this->numero = 421880;

		// Key text used to find module data into llx_const table.
		$this->rights_class = 'mailchimp';

		// Family can be 'base' (core modules), 'crm', 'financial', 'hr', 'projects', 'products', 'ecm', 'technic' (transverse modules), 'interface' (link with external tools), 'other', 'marketing'
		$this->family = "marketing";

		// Module position in the family on 2 digits ('01', '10', ...)
		$this->module_position = '85';

		// Gives the possibility for the module, to provide his own family info and position of this family (Overwrite $this->family and $this->module_position)
		$this->familyinfo = array('marketing' => array('position' => '085', 'label' => $langs->trans("ModuleFamilyMarketing")));

		// Module label (no space allowed), used if translation string 'ModuleMailchimpName' not found.
		$this->name = preg_replace('/^mod/i', '', get_class($this));

		// Module description, used if translation string 'ModuleMailchimpDesc' not found.
		$this->description = "Synchronisation des contacts Dolibarr avec Mailchimp, gestion des campagnes et suivi des performances.";

		// Used only if file README.md and README-<LANG>.md not found.
		$this->descriptionlong = "Connecteur Mailchimp : synchronisation automatique des tiers et contacts vers les audiences Mailchimp, import/export bidirectionnel, creation et envoi de campagnes depuis Dolibarr, suivi des performances (ouvertures, clics, desabonnements).";

		// Author
		$this->editor_name = 'Gael';
		$this->editor_url = '';

		// Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated' or a version string like 'x.y.z'
		$this->version = '1.0.0';

		// Url to the file with your list of versions of the module. Used to check that a module is up to date.
		//$this->url_last_version = '';

		// Key used in llx_const table to save module status enabled/disabled (where MAILCHIMP is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/img directory under name object_mailchimp.png, use this->picto='mailchimp@mailchimp'
		$this->picto = 'email';

		// Define some features supported by module (triggers, login, substitutions, menus, css, ...)
		$this->module_parts = array(
			// Set this to 1 if module has its own trigger directory (core/triggers)
			'triggers' => 1,
			// Set this to 1 if module has its own login method file (core/login)
			//'login' => 0,
			// Set this to 1 if module has its own substitution function file (core/substitutions)
			//'substitutions' => 0,
			// Set this to 1 if module has its own menus handler directory (core/menus)
			//'menus' => 0,
			// Set this to 1 if module overlaps a core module (see list on the wiki)
			//'overlap' => 0,
			// Set this to 1 if module has its own theme directory (theme)
			//'theme' => 0,
			// Set this to 1 if module overwrite template preprocessor
			//'tpl' => 0,
			// Set this to 1 if module has its own barcode directory (core/modules/barcode)
			//'barcode' => 0,
			// Set this to 1 if module has its own models directory (core/modules/xxx)
			//'models' => 0,
			// Set this to 1 if module has its own printing directory (core/modules/printing)
			//'printing' => 0,
			// Set this to 1 if module has its own ODT/ODS templates directory (documents)
			//'odt' => 0,
			// Set this to 1 if module has its own ecommerce directory (ecommerce)
			//'ecommerce' => 0,
			// Set this to 1 if module has its own CSS (css)
			//'css' => array('/mailchimp/css/mailchimp.css.php'),
			// Set this to 1 if module has JavaScript files (js)
			//'js' => array(),
			// Set here all hooks context managed by module. To find available hook context, make "grep -r '>initHooks(' *" on source code. You can also set hook context 'all'
			'hooks' => array(
				'thirdpartycard',
				'contactcard',
				'mailingcard',
				'main',
			)
			// Set this to 1 if features of module are opened to external users
			//'moduleforexternal' => 0,
		);

		// Data directories to create when module is enabled.
		$this->dirs = array("/mailchimp/temp");

		// Config pages. Put here list of php page(s) stored by module that used for setup module.
		$this->config_page_url = array("mailchimp_setup.php@mailchimp");

		// Dependencies
		$this->hidden = false;			// A condition to hide module
		$this->depends = array();		// List of module class names that must be enabled if this module is enabled. Example: array("always1","modOtherModule1")
		$this->requiredby = array();	// List of module class names to disable if this one is disabled. Example: array("modModuleToDisable1")
		$this->conflictwith = array();	// List of module class names this module must not be enabled with. Example: array("modModuleToDisable1")

		// The language file dedicated to your module
		$this->langfiles = array("mailchimp@mailchimp");

		// Prerequisites
		$this->phpmin = array(7, 4);					// Minimum version of PHP required by module
		$this->need_dolibarr_version = array(24, 0, -2);	// Minimum version of Dolibarr required by module (-2 = beta or lower)

		// Messages activated at module activation
		$this->warnings_activation = array();
		$this->warnings_activation_ext = array();

		// Constants
		$this->const = array(
			//0=>array('MAILCHIMP_MYNEWCONST1', 'chaine', 'myvalue', 'This is a constant to add', 1, 'allentities', 1)
		);

		// Some keys to add into the overwriting translation tables
		$this->overwrite_translation = array();

		if (!isset($conf->mailchimp) || !isset($conf->mailchimp->enabled)) {
			$conf->mailchimp = new stdClass();
			$conf->mailchimp->enabled = 0;
		}

		// Array for parts to add to the chain of cron jobs. See "Module Scheduled jobs" wiki page.
		$this->cronjobs = array(
			// Sync incrementale des contacts toutes les 15 minutes (activee manuellement en Phase 2)
			0 => array(
				'label' => 'MailchimpSyncCron',
				'jobtype' => 'method',
				'class' => '/mailchimp/class/mailchimpcontactsync.class.php',
				'objectname' => 'MailchimpContactSync',
				'method' => 'runCronIncremental',
				'parameters' => '',
				'comment' => 'Sync incrementale des contacts Dolibarr vers Mailchimp',
				'frequency' => 1,
				'unitfrequency' => 900,
				'status' => 0,
				'test' => '$conf->mailchimp->enabled && getDolGlobalString("MAILCHIMP_APIKEY_ENC")',
				'datelastrun' => 0
			),
			// Rappel des statistiques de campagnes toutes les heures (Phase 5)
			1 => array(
				'label' => 'MailchimpStatsCron',
				'jobtype' => 'method',
				'class' => '/mailchimp/class/mailchimpcampaign.class.php',
				'objectname' => 'MailchimpCampaign',
				'method' => 'runCronStats',
				'parameters' => '',
				'comment' => 'Rappel des statistiques des campagnes Mailchimp',
				'frequency' => 1,
				'unitfrequency' => 3600,
				'status' => 0,
				'test' => '$conf->mailchimp->enabled && getDolGlobalString("MAILCHIMP_APIKEY_ENC")',
				'datelastrun' => 0
			),
			// Reconciliation des opt-out toutes les 24 h (Phase 6)
			2 => array(
				'label' => 'MailchimpOptoutCron',
				'jobtype' => 'method',
				'class' => '/mailchimp/class/mailchimpcontactsync.class.php',
				'objectname' => 'MailchimpContactSync',
				'method' => 'runCronOptoutReconciliation',
				'parameters' => '',
				'comment' => 'Reconciliation des desabonnements Mailchimp vers Dolibarr (fallback webhook)',
				'frequency' => 1,
				'unitfrequency' => 86400,
				'status' => 0,
				'test' => '$conf->mailchimp->enabled && getDolGlobalString("MAILCHIMP_APIKEY_ENC")',
				'datelastrun' => 0
			),
		);

		// Example: declaring 1 box (widget) for the dashboard.
		$this->boxes = array(
			0 => array(
				'type' => '',
				'file' => 'mailchimpstats@mailchimp',
				'note' => 'Statistiques des campagnes Mailchimp',
				'enabledbydefaulton' => 'Home',
			),
		);

		// Permissions provided by this module
		$this->rights = array();
		$r = 0;

		$this->rights[$r][0] = 421881;				// Permission id (must not be already used)
		$this->rights[$r][1] = 'Lire les donnees Mailchimp (contacts et campagnes)';	// Permission label
		$this->rights[$r][4] = 'read';				// Permission key (in php when checking permission)
		$r++;
		$this->rights[$r][0] = 421882;
		$this->rights[$r][1] = 'Modifier la configuration et les donnees Mailchimp';
		$this->rights[$r][4] = 'write';
		$r++;
		$this->rights[$r][0] = 421883;
		$this->rights[$r][1] = 'Lancer les synchronisations de contacts';
		$this->rights[$r][4] = 'sync';
		$r++;
		$this->rights[$r][0] = 421884;
		$this->rights[$r][1] = 'Creer et envoyer des campagnes Mailchimp';
		$this->rights[$r][4] = 'campaigns';

		// Main menu entries to add
		$this->menu = array();
		$r = 0;

		// Top menu entry
		$this->menu[$r++] = array(
			'fk_menu' => '',									// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy'
			'type' => 'top',									// This is a Top menu entry
			'titre' => 'Mailchimp',
			'prefix' => img_picto('', 'email', 'class="paddingright pictofixedwidth"'),
			'mainmenu' => 'mailchimp',
			'leftmenu' => '',
			'url' => '/mailchimp/index.php',
			'langs' => 'mailchimp@mailchimp',					// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 100 + $r,
			'enabled' => '$conf->mailchimp->enabled',			// Define condition to show or hide menu entry. Use '$conf->mailchimp->enabled' if entry must be visible if module is enabled.
			'perms' => '1',										// Use 'perms'=>'$user->rights->mailchimp->read' if you want your menu with a permission rules
			'target' => '',
			'user' => 2,										// 0=Menu for internal users, 1=external users, 2=both
		);

		// Left menu entry: accueil du module (mainmenu=mailchimp)
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=mailchimp',
			'type' => 'left',
			'titre' => 'MailchimpHome',
			'mainmenu' => 'mailchimp',
			'leftmenu' => 'mailchimp_home',
			'url' => '/mailchimp/index.php',
			'langs' => 'mailchimp@mailchimp',
			'position' => 100 + $r,
			'enabled' => '$conf->mailchimp->enabled',
			'perms' => '$user->rights->mailchimp->read',
			'target' => '',
			'user' => 2,
		);

		// Left menu entry: synchronisation des contacts (page livree en Phase 2 ; pointe sur l'index pour le moment)
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=mailchimp',
			'type' => 'left',
			'titre' => 'MailchimpContactSync',
			'mainmenu' => 'mailchimp',
			'leftmenu' => 'mailchimp_sync',
			'url' => '/mailchimp/contactsync.php',
			'langs' => 'mailchimp@mailchimp',
			'position' => 100 + $r,
			'enabled' => '$conf->mailchimp->enabled',
			'perms' => '$user->rights->mailchimp->sync',
			'target' => '',
			'user' => 0,
		);

		// Left menu entry: campagnes (page livree en Phase 4)
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=mailchimp',
			'type' => 'left',
			'titre' => 'MailchimpCampaigns',
			'mainmenu' => 'mailchimp',
			'leftmenu' => 'mailchimp_campaigns',
			'url' => '/mailchimp/campaigns_list.php',
			'langs' => 'mailchimp@mailchimp',
			'position' => 100 + $r,
			'enabled' => '$conf->mailchimp->enabled',
			'perms' => '$user->rights->mailchimp->campaigns',
			'target' => '',
			'user' => 0,
		);

		// Left menu entry: rapports (page livree en Phase 5)
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=mailchimp',
			'type' => 'left',
			'titre' => 'MailchimpReports',
			'mainmenu' => 'mailchimp',
			'leftmenu' => 'mailchimp_reports',
			'url' => '/mailchimp/reports_list.php',
			'langs' => 'mailchimp@mailchimp',
			'position' => 100 + $r,
			'enabled' => '$conf->mailchimp->enabled',
			'perms' => '$user->rights->mailchimp->read',
			'target' => '',
			'user' => 0,
		);

		// Left menu entry: configuration
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=mailchimp',
			'type' => 'left',
			'titre' => 'MailchimpSetup',
			'mainmenu' => 'mailchimp',
			'leftmenu' => 'mailchimp_setup',
			'url' => '/mailchimp/admin/mailchimp_setup.php',
			'langs' => 'mailchimp@mailchimp',
			'position' => 100 + $r,
			'enabled' => '$conf->mailchimp->enabled',
			'perms' => '$user->admin',
			'target' => '',
			'user' => 0,
		);

		// Exports profiles provided by module
		$this->export_label = array();
		$this->export_icon = array();
		$this->export_sql = array();
		$this->export_fields_array = array();

		// Imports profiles provided by module
		$this->import_label = array();
		$this->import_icon = array();
		$this->import_tables_array = array();
		$this->import_fields_array = array();
		$this->import_entities_array = array();
		$this->import_tables_sqlfrom = '';
		$this->import_examplevalues_array = array();

		// Example of tabs on standard objects
		$this->tabs = array(
			// Onglet Mailchimp sur la fiche tiers
			'thirdparty:+mailchimp:MailchimpTab:mailchimp@mailchimp:$user->rights->mailchimp->read:/mailchimp/tab_thirdparty.php?id=__ID__',
			// Onglet Mailchimp sur la fiche contact
			'contact:+mailchimp:MailchimpTab:mailchimp@mailchimp:$user->rights->mailchimp->read:/mailchimp/tab_contact.php?id=__ID__',
		);

		// Dictionaries
		$this->dictionaries = array();

		//_boxes and cronjobs are defined above. Other properties below are for template compliance only.

		$this->arrays = array();
		$this->fieldsforcontent = array();
	}

	/**
	 *  Function called when module is enabled.
	 *  The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *  It also creates data directories
	 *
	 *  @param      string  $options    Options when enabling module ('', 'noboxes')
	 *  @return     int                 1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		global $conf, $langs;

		// Permissions and menus are stored via standard mechanisms
		$sql = array();

		$result = $this->_load_tables('/mailchimp/sql/');
		if ($result < 0) {
			return -1; // Do not activate module if error 'not allowed' returned when loading module SQL queries (the _load_table run sql with run_sql with the error allowed parameter set to 'default')
		}

		// Create extra directories
		$ok = 1;
		foreach ($this->dirs as $key => $dir) {
			dol_mkdir($conf->mailchimp->dir_output.'/'.$dir);
		}

		return $this->_init($sql, $options);
	}

	/**
	 *  Function called when module is disabled.
	 *  Remove from database constants, boxes and permissions from Dolibarr database.
	 *  Data directories are not deleted.
	 *
	 *  @param      string	$options    Options when enabling module ('', 'noboxes')
	 *  @return     int                 1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();

		return $this->_remove($sql, $options);
	}
}
