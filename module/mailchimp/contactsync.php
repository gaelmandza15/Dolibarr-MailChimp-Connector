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
 * \file    contactsync.php
 * \ingroup mailchimp
 * \brief   Page de synchronisation des contacts Dolibarr vers Mailchimp.
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) { $res = include __DIR__.'/../../main.inc.php'; }
if (!$res && file_exists(__DIR__.'/../main.inc.php')) { $res = include __DIR__.'/../main.inc.php'; }
if (!$res) { die('Include of main fails'); }

global $db, $langs, $user, $conf, $form;

require_once dol_buildpath('/mailchimp/lib/mailchimp.lib.php', 0);
require_once dol_buildpath('/mailchimp/class/mailchimpcontactsync.class.php', 0);

if (!$user->rights->mailchimp->sync) {
	accessforbidden();
}

$langs->loadLangs(array("mailchimp@mailchimp"));

$action = GETPOST('action', 'aZ09');
$filters = array(
	'sources' => GETPOSTISSET('sources') ? (array) GETPOST('sources', 'array') : array('contact', 'company'),
	'category_id' => GETPOSTINT('category_id'),
);
$list_id = GETPOST('list_id', 'alpha');

$sync = new MailchimpContactSync($db);
$client = $sync->getClient();
$config = mailchimp_get_config($db);
if (empty($list_id)) {
	$list_id = $config['default_list_id'];
}

$errors = array();
$dryrun_result = null;
$sync_result = null;
$lists = array();

// Chargement des audiences disponibles
if ($client !== null) {
	try {
		$resp = $client->get('/lists', array('count' => 50, 'fields' => 'lists.id,lists.name,lists.stats.member_count'));
		$lists = $resp['lists'] ?? array();
	} catch (MailchimpApiException $e) {
		$errors[] = '['.$e->status.'] '.$e->getMessage().' - '.$e->detail;
	}
}

// Liste des catégories (tiers + contacts) pour le filtre
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
	if ($client === null) {
		$errors[] = $langs->trans("MailchimpNoApiKey");
	} elseif (empty($list_id)) {
		$errors[] = $langs->trans("MailchimpNoAudienceSelected");
	} else {
		$dryrun_result = $sync->dryRun($list_id, $filters);
	}
}

if ($action == 'sync') {
	if (!$user->rights->mailchimp->write) {
		$errors[] = $langs->trans("NotEnoughPermissions");
	} elseif ($client === null) {
		$errors[] = $langs->trans("MailchimpNoApiKey");
	} elseif (empty($list_id)) {
		$errors[] = $langs->trans("MailchimpNoAudienceSelected");
	} else {
		$sync_result = $sync->sync($list_id, $filters);
	}
}

/*
 * View
 */

llxHeader('', $langs->trans("MailchimpContactSync"), '', '', 0, 0, '', '', '', 'mod-mailchimp page-contactsync');

print load_fiche_titre($langs->trans("MailchimpContactSync"), '', 'email');

dol_htmloutput_events();
foreach ($errors as $e) {
	print '<div class="error">'.dol_escape_htmltag($e).'</div>';
}

if ($client === null) {
	print '<div class="warning">'.$langs->trans("MailchimpNoApiKey").'</div>';
}

// Formulaire de filtres
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("Filters").'</td></tr>';

print '<tr><td>'.$langs->trans("MailchimpAudience").'</td><td>';
print '<select name="list_id">';
foreach ($lists as $l) {
	$sel = ($list_id === $l['id']) ? ' selected' : '';
	print '<option value="'.dol_escape_htmltag($l['id']).'"'.$sel.'>'.dol_escape_htmltag($l['name']).' ('.((int) ($l['stats']['member_count'] ?? 0)).')</option>';
}
print '</select></td></tr>';

print '<tr><td>'.$langs->trans("MailchimpSources").'</td><td>';
print '<label><input type="checkbox" name="sources[]" value="contact"'.(in_array('contact', $filters['sources']) ? ' checked' : '').'> '.$langs->trans("Contacts").'</label> &nbsp; ';
print '<label><input type="checkbox" name="sources[]" value="company"'.(in_array('company', $filters['sources']) ? ' checked' : '').'> '.$langs->trans("ThirdParties").'</label>';
print '</td></tr>';

print '<tr><td>'.$langs->trans("MailchimpCategoryFilter").'</td><td>';
print '<select name="category_id"><option value="0">-- '.$langs->trans("None").' --</option>';
foreach ($categories as $cat) {
	$sel = ($filters['category_id'] == $cat->rowid) ? ' selected' : '';
	$type_label = ($cat->type == 2) ? $langs->trans("ThirdParties") : $langs->trans("Contacts");
	print '<option value="'.$cat->rowid.'"'.$sel.'>'.dol_escape_htmltag($cat->label).' ['.$type_label.']</option>';
}
print '</select></td></tr>';

print '</table>';
print '<div class="tabsAction">';
print '<input type="submit" class="button" name="do_dryrun" value="'.$langs->trans("MailchimpDryRun").'" onclick="this.form.action.value=\'dryrun\'">';
print '<input type="submit" class="button" name="do_sync" value="'.$langs->trans("MailchimpRunSync").'" onclick="this.form.action.value=\'sync\'; return confirm(\''.dol_escape_js($langs->trans("MailchimpSyncConfirm")).'\');">';
print '</div>';
print '</form>';

// Résultats du dry-run
if (is_array($dryrun_result)) {
	print load_fiche_titre($langs->trans("MailchimpDryRunResult"), '', 'email');
	print '<div class="fichecenter">';
	print '<div class="fichetwothirdright">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans("MailchimpDryRunAction").'</td><td>'.$langs->trans("Nb").'</td></tr>';
	print '<tr><td>'.$langs->trans("MailchimpToAdd").'</td><td>'.$dryrun_result['to_add'].'</td></tr>';
	print '<tr><td>'.$langs->trans("MailchimpToUpdate").'</td><td>'.$dryrun_result['to_update'].'</td></tr>';
	print '<tr><td>'.$langs->trans("MailchimpAlreadySynced").'</td><td>'.$dryrun_result['already_synced'].'</td></tr>';
	print '<tr><td>'.$langs->trans("MailchimpRemoteMembers").'</td><td>'.$dryrun_result['remote_count'].'</td></tr>';
	print '</table>';
	print '</div></div>';

	if (!empty($dryrun_result['members'])) {
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre"><td>Email</td><td>Nom</td><td>Type</td><td>Tags</td><td>'.$langs->trans("Action").'</td><td>MC</td></tr>';
		$i = 0;
		foreach ($dryrun_result['members'] as $m) {
			if ($i++ >= 200) {
				print '<tr><td colspan="6" class="opacitymedium">...</td></tr>';
				break;
			}
			print '<tr><td>'.dol_escape_htmltag($m['email']).'</td><td>'.dol_escape_htmltag($m['name']).'</td><td>'.$m['type'].'</td>';
			print '<td>'.dol_escape_htmltag($m['tags']).'</td><td>'.dol_escape_htmltag($m['action']).'</td><td>'.dol_escape_htmltag($m['remote_status']).'</td></tr>';
		}
		print '</table>';
	}
}

// Résultat de la synchronisation
if (is_array($sync_result)) {
	print load_fiche_titre($langs->trans("MailchimpSyncResult"), '', 'email');
	if (isset($sync_result['error'])) {
		print '<div class="error">'.dol_escape_htmltag($sync_result['error']).'</div>';
	} else {
		print '<div class="ok">';
		print $langs->trans("MailchimpSyncDone", $sync_result['ok'], $sync_result['updated'], $sync_result['batches']);
		print '</div>';
		if (!empty($sync_result['errors'])) {
			print '<table class="noborder centpercent"><tr class="liste_titre"><td>'.$langs->trans("Errors").'</td></tr>';
			foreach (array_slice($sync_result['errors'], 0, 50) as $err) {
				print '<tr><td>'.dol_escape_htmltag($err).'</td></tr>';
			}
			print '</table>';
		}
	}
}

llxFooter();
$db->close();
