<?php
/* Copyright (C) 2026  Gael <gael@example.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    reports_list.php
 * \ingroup mailchimp
 * \brief   Rapports de performance des campagnes (implemente en Phase 5).
 */

// Load Dolibarr environment
$res = 0;
include_once DOL_DOCUMENT_ROOT.'/core/main.inc.php';

global $db, $langs, $user, $conf;

if (!$user->rights->mailchimp->read) {
	accessforbidden();
}

$langs->loadLangs(array("mailchimp@mailchimp"));

llxHeader('', $langs->trans("MailchimpReports"), '', '', 0, 0, '', '', '', 'mod-mailchimp page-reports');

print load_fiche_titre($langs->trans("MailchimpReports"), '', 'email');
print dol_get_alert($langs->trans("MailchimpSoonPhase5"), 'info');

llxFooter();
$db->close();
