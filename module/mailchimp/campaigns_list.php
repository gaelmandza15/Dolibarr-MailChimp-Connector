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
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) { $res = include __DIR__.'/../../main.inc.php'; }
if (!$res && file_exists(__DIR__.'/..//../main.inc.php')) { $res = include __DIR__.'/..//../main.inc.php'; }
if (!$res) { die('Include of main fails'); }

global $db, $langs, $user, $conf;

if (!$user->rights->mailchimp->campaigns) {
	accessforbidden();
}

$langs->loadLangs(array("mailchimp@mailchimp"));

llxHeader('', $langs->trans("MailchimpCampaigns"), '', '', 0, 0, '', '', '', 'mod-mailchimp page-campaigns');

print load_fiche_titre($langs->trans("MailchimpCampaigns"), '', 'email');
setEventMessages($langs->trans("MailchimpSoonPhase4"), null, 'mesgs'); dol_htmloutput_events();

llxFooter();
$db->close();
