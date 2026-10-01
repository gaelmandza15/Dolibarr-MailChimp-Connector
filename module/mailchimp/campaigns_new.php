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
 * \file    campaigns_new.php
 * \ingroup mailchimp
 * \brief   Creation d'une campagne Mailchimp depuis Dolibarr.
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) { $res = include __DIR__.'/../../main.inc.php'; }
if (!$res && file_exists(__DIR__.'/../main.inc.php')) { $res = include __DIR__.'/../main.inc.php'; }
if (!$res) { die('Include of main fails'); }

global $db, $langs, $user, $conf;

require_once dol_buildpath('/mailchimp/lib/mailchimp.lib.php', 0);
require_once dol_buildpath('/mailchimp/class/mailchimpcampaign.class.php', 0);

if (!$user->rights->mailchimp->campaigns) {
	accessforbidden();
}

$langs->loadLangs(array("mailchimp@mailchimp"));

$action = GETPOST('action', 'aZ09');
$config = mailchimp_get_config($db);
$campaign = new MailchimpCampaign($db);
$client = $campaign->getClient();

$errors = array();

$list_id = GETPOST('list_id', 'alpha');
$subject = GETPOST('subject', 'restricthtml');
$from_name = GETPOST('from_name', 'alpha') !== '' ? GETPOST('from_name', 'alpha') : $config['from_name'];
$reply_to = GETPOST('reply_to', 'alpha') !== '' ? GETPOST('reply_to', 'alpha') : $config['reply_to'];
$content_html = GETPOST('content_html', 'restricthtml');

$lists = array();
if ($client !== null) {
	try {
		$resp = $client->get('/lists', array('count' => 50, 'fields' => 'lists.id,lists.name'));
		$lists = $resp['lists'] ?? array();
	} catch (MailchimpApiException $e) {
		$errors[] = '['.$e->status.'] '.$e->getMessage().' - '.$e->detail;
	}
}

if ($action == 'create') {
	if (!$user->rights->mailchimp->write) {
		$errors[] = $langs->trans("NotEnoughPermissions");
	} elseif ($client === null) {
		$errors[] = $langs->trans("MailchimpNoApiKey");
	} elseif (empty($list_id) || $subject === '' || $from_name === '' || $reply_to === '' || $content_html === '') {
		$errors[] = $langs->trans("MailchimpCampaignMissingFields");
	} else {
		try {
			$campaign_id = $campaign->create($list_id, $subject, $from_name, $reply_to);
			if ($campaign_id === false) {
				throw new MailchimpApiException(0, 'Client non configure');
			}
			$campaign->setContent($campaign_id, $content_html, strip_tags($content_html));
			header('Location: '.dol_buildpath('/mailchimp/campaigns_list.php', 1).'&save_lastsearch_values=1');
			exit;
		} catch (MailchimpApiException $e) {
			$errors[] = '['.$e->status.'] '.$e->getMessage().' - '.$e->detail;
		}
	}
}

/*
 * View
 */

llxHeader('', $langs->trans("MailchimpNewCampaign"), '', '', 0, 0, '', '', '', 'mod-mailchimp page-campaign-new');

print load_fiche_titre($langs->trans("MailchimpNewCampaign"), '<a class="butAction" href="'.dol_buildpath('/mailchimp/campaigns_list.php', 1).'">'.$langs->trans("BackToList").'</a>', 'email');

dol_htmloutput_events();
foreach ($errors as $e) {
	print '<div class="error">'.dol_escape_htmltag($e).'</div>';
}

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="create">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("MailchimpCampaignParams").'</td></tr>';

print '<tr><td>'.$langs->trans("MailchimpAudience").'</td><td>';
print '<select name="list_id" required>';
print '<option value="">--</option>';
foreach ($lists as $l) {
	$sel = ($list_id === $l['id']) ? ' selected' : '';
	print '<option value="'.dol_escape_htmltag($l['id']).'"'.$sel.'>'.dol_escape_htmltag($l['name']).'</option>';
}
print '</select></td></tr>';

print '<tr><td>'.$langs->trans("MailchimpCampaignSubject").'</td><td><input name="subject" size="60" required value="'.dol_escape_htmltag($subject).'"></td></tr>';
print '<tr><td>'.$langs->trans("MailchimpFromName").'</td><td><input name="from_name" size="40" required value="'.dol_escape_htmltag($from_name).'"></td></tr>';
print '<tr><td>'.$langs->trans("MailchimpReplyTo").'</td><td><input type="email" name="reply_to" size="40" required value="'.dol_escape_htmltag($reply_to).'"></td></tr>';

print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("MailchimpCampaignContent").'</td></tr>';
print '<tr><td colspan="2">';
print '<textarea name="content_html" rows="15" style="width:100%" required placeholder="&lt;p&gt;Bonjour, ...">'.dol_escape_htmltag($content_html, 1).'</textarea>';
print '<br><span class="opacitymedium">'.$langs->trans("MailchimpCampaignContentHint", '*|UNSUB|*').'</span>';
print '</td></tr>';

print '</table>';
print '<div class="tabsAction">';
print '<input type="submit" class="butAction" value="'.$langs->trans("MailchimpCampaignCreateDraft").'">';
print '</div>';
print '</form>';

llxFooter();
$db->close();
