<?php
/* Copyright (C) 2026  Gael <gael@example.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    contactsync.php
 * \ingroup mailchimp
 * \brief   Page de synchronisation des contacts (implementee en Phase 2).
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) { $res = include __DIR__.'/../../main.inc.php'; }
if (!$res && file_exists(__DIR__.'/..//../main.inc.php')) { $res = include __DIR__.'/..//../main.inc.php'; }
if (!$res) { die('Include of main fails'); }

global $db, $langs, $user, $conf;

if (!$user->rights->mailchimp->sync) {
	accessforbidden();
}

$langs->loadLangs(array("mailchimp@mailchimp"));

llxHeader('', $langs->trans("MailchimpContactSync"), '', '', 0, 0, '', '', '', 'mod-mailchimp page-contactsync');

print load_fiche_titre($langs->trans("MailchimpContactSync"), '', 'email');
setEventMessages($langs->trans("MailchimpSoonPhase2"), null, 'mesgs'); dol_htmloutput_events();

llxFooter();
$db->close();
