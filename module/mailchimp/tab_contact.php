<?php
/* Copyright (C) 2026  Gael <gael@example.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    tab_contact.php
 * \ingroup mailchimp
 * \brief   Onglet Mailchimp sur la fiche contact (implemente en Phase 2).
 */

// Load Dolibarr environment
$res = 0;
include_once DOL_DOCUMENT_ROOT.'/core/main.inc.php';

global $db, $langs, $user, $conf;

require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/contact.lib.php';

if (!$user->rights->mailchimp->read) {
	accessforbidden();
}

$langs->loadLangs(array("companies", "mailchimp@mailchimp"));

$id = GETPOST('id', 'int');

$contact = new Contact($db);
if ($id > 0 && $contact->fetch($id) <= 0) {
	accessforbidden();
}

llxHeader('', $langs->trans("MailchimpTab"), '', '', 0, 0, '', '', '', 'mod-mailchimp tab-contact');

$head = contact_prepare_head($contact);
print dol_get_fiche_head($head, 'mailchimp', $langs->trans("Contact"), -1, 'contact');

print dol_get_alert($langs->trans("MailchimpSoonPhase2"), 'info');

print dol_get_fiche_end();

llxFooter();
$db->close();
