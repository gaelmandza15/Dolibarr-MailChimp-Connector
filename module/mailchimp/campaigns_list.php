<?php
/* Copyright (C) 2026  Gael <gael@example.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    campaigns_list.php
 * \ingroup mailchimp
 * \brief   Liste des campagnes Mailchimp (implemente en Phase 4).
 */

// Load Dolibarr environment
$res = 0;
include_once DOL_DOCUMENT_ROOT.'/core/main.inc.php';

global $db, $langs, $user, $conf;

if (!$user->rights->mailchimp->campaigns) {
	accessforbidden();
}

$langs->loadLangs(array("mailchimp@mailchimp"));

llxHeader('', $langs->trans("MailchimpCampaigns"), '', '', 0, 0, '', '', '', 'mod-mailchimp page-campaigns');

print load_fiche_titre($langs->trans("MailchimpCampaigns"), '', 'email');
print dol_get_alert($langs->trans("MailchimpSoonPhase4"), 'info');

llxFooter();
$db->close();
