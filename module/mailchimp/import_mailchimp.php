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
 * \file    import_mailchimp.php
 * \ingroup mailchimp
 * \brief   Import des membres Mailchimp vers Dolibarr (contacts et/ou tiers).
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) { $res = include __DIR__.'/../../main.inc.php'; }
if (!$res && file_exists(__DIR__.'/../main.inc.php')) { $res = include __DIR__.'/../main.inc.php'; }
if (!$res) { die('Include of main fails'); }

global $db, $langs, $user, $conf, $form;

require_once dol_buildpath('/mailchimp/lib/mailchimp.lib.php', 0);
require_once dol_buildpath('/mailchimp/class/mailchimpimport.class.php', 0);

if (!$user->rights->mailchimp->sync) {
	accessforbidden();
}

$langs->loadLangs(array("mailchimp@mailchimp"));

$action = GETPOST('action', 'aZ09');
$list_id = GETPOST('list_id', 'alpha');

$options = array(
	'create_contact' => GETPOSTISSET('create_contact') ? (GETPOSTINT('create_contact') ? 1 : 0) : 1,
	'create_company' => GETPOSTISSET('create_company') ? (GETPOSTINT('create_company') ? 1 : 0) : 0,
	'update_existing' => GETPOSTINT('update_existing') ? 1 : 0,
	'unsubscribed' => GETPOST('unsubscribed', 'aZ09') === 'import_flagged' ? 'import_flagged' : 'skip',
	'category_id' => GETPOSTINT('category_id'),
	'fk_user' => (int) $user->id,
);

$importer = new MailchimpImport($db);
$config = mailchimp_get_config($db);
if (empty($list_id)) {
	$list_id = $config['default_list_id'];
}

$errors = array();
$dryrun_result = null;
$import_result = null;
$lists = array();

// Chargement des audiences disponibles (client partagé avec la sync)
$client_lists = null;
require_once dol_buildpath('/mailchimp/class/mailchimpcontactsync.class.php', 0);
$sync = new MailchimpContactSync($db);
$client_lists = $sync->getClient();

if ($client_lists !== null) {
	try {
		$resp = $client_lists->get('/lists', array('count' => 50, 'fields' => 'lists.id,lists.name,lists.stats.member_count'));
		$lists = $resp['lists'] ?? array();
	} catch (MailchimpApiException $e) {
		$errors[] = '['.$e->status.'] '.$e->getMessage().' - '.$e->detail;
	}
}

// Categories pour l'affectation (types 2 = tiers, 4 = contacts)
$categories = array();
$sql = "SELECT rowid, label, type FROM ".MAIN_DB_PREFIX."categorie WHERE entity IN (".getEntity('categorie', 1).") AND type IN (2, 4) ORDER BY type, label";
$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$categories[] = $obj;
	}
	$db->free($resql);
}

if ($action == 'dryrun') {
	if (empty($list_id)) {
		$errors[] = $langs->trans("MailchimpNoAudienceSelected");
	} else {
		$dryrun_result = $importer->dryRun($list_id, $options);
		if ($dryrun_result === false) {
			$errors[] = $langs->trans("MailchimpNoApiKey");
		}
	}
}

if ($action == 'import') {
	if (!$user->rights->mailchimp->write) {
		$errors[] = $langs->trans("NotEnoughPermissions");
	} elseif (empty($list_id)) {
		$errors[] = $langs->trans("MailchimpNoAudienceSelected");
	} else {
		$import_result = $importer->import($list_id, $options);
	}
}

/*
 * View
 */

llxHeader('', $langs->trans("MailchimpImport"), '', '', 0, 0, '', '', '', 'mod-mailchimp page-import');

print load_fiche_titre($langs->trans("MailchimpImport"), '', 'email');

dol_htmloutput_events();
foreach ($errors as $e) {
	print '<div class="error">'.dol_escape_htmltag($e).'</div>';
}

// Formulaire
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("MailchimpImportOptions").'</td></tr>';

print '<tr><td>'.$langs->trans("MailchimpAudience").'</td><td>';
print '<select name="list_id">';
foreach ($lists as $l) {
	$sel = ($list_id === $l['id']) ? ' selected' : '';
	print '<option value="'.dol_escape_htmltag($l['id']).'"'.$sel.'>'.dol_escape_htmltag($l['name']).' ('.((int) ($l['stats']['member_count'] ?? 0)).')</option>';
}
print '</select></td></tr>';

print '<tr><td>'.$langs->trans("MailchimpImportCreate").'</td><td>';
print '<label><input type="checkbox" name="create_contact" value="1"'.(!empty($options['create_contact']) ? ' checked' : '').'> '.$langs->trans("MailchimpImportAsContact").'</label> &nbsp; ';
print '<label><input type="checkbox" name="create_company" value="1"'.(!empty($options['create_company']) ? ' checked' : '').'> '.$langs->trans("MailchimpImportAsCompany").'</label>';
print '</td></tr>';

print '<tr><td>'.$langs->trans("MailchimpImportUpdateExisting").'</td><td>';
print '<input type="checkbox" name="update_existing" value="1"'.(!empty($options['update_existing']) ? ' checked' : '').'> <span class="opacitymedium">'.$langs->trans("MailchimpImportUpdateExistingHint").'</span>';
print '</td></tr>';

print '<tr><td>'.$langs->trans("MailchimpImportUnsubscribed").'</td><td>';
print '<label><input type="radio" name="unsubscribed" value="skip"'.($options['unsubscribed'] === 'skip' ? ' checked' : '').'> '.$langs->trans("MailchimpImportUnsubSkip").'</label> &nbsp; ';
print '<label><input type="radio" name="unsubscribed" value="import_flagged"'.($options['unsubscribed'] === 'import_flagged' ? ' checked' : '').'> '.$langs->trans("MailchimpImportUnsubFlagged").'</label>';
print '</td></tr>';

print '<tr><td>'.$langs->trans("MailchimpImportCategory").'</td><td>';
print '<select name="category_id"><option value="0">-- '.$langs->trans("None").' --</option>';
foreach ($categories as $cat) {
	$sel = ($options['category_id'] == $cat->rowid) ? ' selected' : '';
	$type_label = ($cat->type == 2) ? $langs->trans("ThirdParties") : $langs->trans("Contacts");
	print '<option value="'.$cat->rowid.'"'.$sel.'>'.dol_escape_htmltag($cat->label).' ['.$type_label.']</option>';
}
print '</select></td></tr>';

print '</table>';
print '<div class="tabsAction">';
print '<input type="submit" class="button" name="do_dryrun" value="'.$langs->trans("MailchimpDryRun").'" onclick="this.form.action.value=\'dryrun\'">';
print '<input type="submit" class="button" name="do_import" value="'.$langs->trans("MailchimpRunImport").'" onclick="this.form.action.value=\'import\'; return confirm(\''.dol_escape_js($langs->trans("MailchimpImportConfirm")).'\');">';
print '</div>';
print '</form>';

// Resultat du dry-run
if (is_array($dryrun_result)) {
	print load_fiche_titre($langs->trans("MailchimpDryRunResult"), '', 'email');
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans("MailchimpDryRunAction").'</td><td>'.$langs->trans("Nb").'</td></tr>';
	print '<tr><td>'.$langs->trans("MailchimpImportCreateContacts").'</td><td>'.$dryrun_result['to_create_contact'].'</td></tr>';
	print '<tr><td>'.$langs->trans("MailchimpImportCreateCompanies").'</td><td>'.$dryrun_result['to_create_company'].'</td></tr>';
	print '<tr><td>'.$langs->trans("MailchimpToUpdate").'</td><td>'.$dryrun_result['to_update'].'</td></tr>';
	print '<tr><td>'.$langs->trans("MailchimpAlreadySynced").'</td><td>'.$dryrun_result['already'].'</td></tr>';
	print '<tr><td>'.$langs->trans("MailchimpImportSkippedUnsub").'</td><td>'.$dryrun_result['skipped_unsub'].'</td></tr>';
	print '</table>';

	if (!empty($dryrun_result['lines'])) {
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre"><td>Email</td><td>Nom</td><td>Societe</td><td>Tags</td><td>MC</td><td>Existant</td><td>'.$langs->trans("Action").'</td></tr>';
		$i = 0;
		foreach ($dryrun_result['lines'] as $l) {
			if ($i++ >= 200) {
				print '<tr><td colspan="7" class="opacitymedium">...</td></tr>';
				break;
			}
			print '<tr><td>'.dol_escape_htmltag($l['email']).'</td><td>'.dol_escape_htmltag($l['name']).'</td><td>'.dol_escape_htmltag($l['company']).'</td>';
			print '<td>'.dol_escape_htmltag($l['tags']).'</td><td>'.dol_escape_htmltag($l['status']).'</td><td>'.dol_escape_htmltag($l['existing']).'</td><td>'.dol_escape_htmltag($l['action']).'</td></tr>';
		}
		print '</table>';
	}
}

// Resultat de l'import
if (is_array($import_result)) {
	print load_fiche_titre($langs->trans("MailchimpImportResult"), '', 'email');
	if (isset($import_result['error'])) {
		print '<div class="error">'.dol_escape_htmltag($import_result['error']).'</div>';
	} else {
		print '<div class="ok">';
		print $langs->trans("MailchimpImportDone", $import_result['created_contact'], $import_result['created_company'], $import_result['updated'], $import_result['skipped']);
		print '</div>';
		if (!empty($import_result['errors'])) {
			print '<table class="noborder centpercent"><tr class="liste_titre"><td>'.$langs->trans("Errors").'</td></tr>';
			foreach (array_slice($import_result['errors'], 0, 50) as $err) {
				print '<tr><td>'.dol_escape_htmltag($err).'</td></tr>';
			}
			print '</table>';
		}
	}
}

llxFooter();
$db->close();
