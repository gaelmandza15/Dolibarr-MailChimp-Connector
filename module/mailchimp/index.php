<?php
/* Copyright (C) 2026  Gael <gael@example.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    index.php
 * \ingroup mailchimp
 * \brief   Accueil du module Mailchimp.
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) { $res = include __DIR__.'/../../main.inc.php'; }
if (!$res && file_exists(__DIR__.'/..//../main.inc.php')) { $res = include __DIR__.'/..//../main.inc.php'; }
if (!$res) { die('Include of main fails'); }

global $db, $langs, $user, $conf;

require_once dol_buildpath('/mailchimp/lib/mailchimp.lib.php', 0);

if (!$user->rights->mailchimp->read) {
	accessforbidden();
}

$langs->loadLangs(array("mailchimp@mailchimp"));

llxHeader('', $langs->trans("MailchimpHome"), '', '', 0, 0, '', '', '', 'mod-mailchimp page-index');

print load_fiche_titre($langs->trans("MailchimpHome"), '', 'email');

if (!getDolGlobalString('MAILCHIMP_APIKEY_ENC') && !mailchimp_get_config($db)['apikey']) {
	setEventMessages($langs->trans("MailchimpNoApiKey"), null, 'warnings'); dol_htmloutput_events();
}

print '<div class="fichecenter">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans("Feature").'</td><td>'.$langs->trans("Status").'</td></tr>';

$features = array(
	'/mailchimp/contactsync.php' => array("MailchimpContactSync", '$user->rights->mailchimp->sync'),
	'/mailchimp/import_mailchimp.php' => array("MailchimpImport", '$user->rights->mailchimp->sync'),
	'/mailchimp/campaigns_list.php' => array("MailchimpCampaigns", '$user->rights->mailchimp->campaigns'),
	'/mailchimp/reports_list.php' => array("MailchimpReports", '$user->rights->mailchimp->read'),
	'/mailchimp/admin/mailchimp_setup.php' => array("MailchimpSetup", '$user->admin'),
);

foreach ($features as $url => $info) {
	print '<tr><td><a href="'.dol_buildpath($url, 1).'">'.$langs->trans($info[0]).'</a></td>';
	print '<td><span class="badge badge-status4">'.$langs->trans("MailchimpAvailable").'</span></td></tr>';
}

print '</table></div></div>';

llxFooter();
$db->close();
