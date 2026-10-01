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
 * \file    campaigns_list.php
 * \ingroup mailchimp
 * \brief   Liste des campagnes Mailchimp avec actions (envoyer, tester, supprimer).
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
$campaign_id = GETPOST('campaign_id', 'alpha');
$test_email = GETPOST('test_email', 'alpha');
$schedule_time = GETPOST('schedule_time', 'alphanohtml');

$campaign = new MailchimpCampaign($db);
$client = $campaign->getClient();

$errors = array();
$messages = array();

if ($action == 'send' && $campaign_id !== '') {
	if (!empty($user->rights->mailchimp->write)) {
		try {
			$campaign->send($campaign_id);
			$messages[] = $langs->trans("MailchimpCampaignSent");
		} catch (MailchimpApiException $e) {
			$errors[] = '['.$e->status.'] '.$e->getMessage().' - '.$e->detail;
		}
	} else {
		$errors[] = $langs->trans("NotEnoughPermissions");
	}
}

if ($action == 'test' && $campaign_id !== '' && $test_email !== '') {
	try {
		$campaign->sendTest($campaign_id, array($test_email));
		$messages[] = $langs->trans("MailchimpCampaignTestSent", $test_email);
	} catch (MailchimpApiException $e) {
		$errors[] = '['.$e->status.'] '.$e->getMessage().' - '.$e->detail;
	}
}

if ($action == 'schedule' && $campaign_id !== '' && $schedule_time !== '') {
	try {
		// Le formulaire fournit une heure locale ISO (type datetime-local) : on ajoute le fuseau
		$campaign->schedule($campaign_id, $schedule_time.':00'.date('P', dol_now()));
		$messages[] = $langs->trans("MailchimpCampaignScheduled", $schedule_time);
	} catch (MailchimpApiException $e) {
		$errors[] = '['.$e->status.'] '.$e->getMessage().' - '.$e->detail;
	}
}

if ($action == 'delete' && $campaign_id !== '') {
	try {
		$campaign->delete($campaign_id);
		$messages[] = $langs->trans("MailchimpCampaignDeleted");
	} catch (MailchimpApiException $e) {
		$errors[] = '['.$e->status.'] '.$e->getMessage().' - '.$e->detail;
	}
}

/*
 * View
 */

llxHeader('', $langs->trans("MailchimpCampaigns"), '', '', 0, 0, '', '', '', 'mod-mailchimp page-campaigns');

print load_fiche_titre($langs->trans("MailchimpCampaigns"), '', 'email');

dol_htmloutput_events();
foreach ($errors as $e) {
	print '<div class="error">'.dol_escape_htmltag($e).'</div>';
}
if (!empty($messages)) {
	setEventMessages(implode(' ', $messages), null, 'mesgs');
	dol_htmloutput_events();
}

if ($client === null) {
	print '<div class="warning">'.$langs->trans("MailchimpNoApiKey").'</div>';
} else {
	print '<div class="tabsAction">';
	print '<a class="butAction" href="'.dol_buildpath('/mailchimp/campaigns_new.php', 1).'">'.$langs->trans("MailchimpNewCampaign").'</a>';
	print '</div>';

	$campaigns = array();
	try {
		$campaigns = $campaign->listCampaigns(50);
	} catch (MailchimpApiException $e) {
		print '<div class="error">['.$e->status.'] '.dol_escape_htmltag($e->getMessage().' - '.$e->detail).'</div>';
	}

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>Objet</td><td>Audience</td><td>Statut</td><td>Date d\'envoi</td><td class="right">Actions</td></tr>';

	if (empty($campaigns)) {
		print '<tr><td colspan="5" class="opacitymedium">'.$langs->trans("MailchimpNoCampaign").'</td></tr>';
	}

	foreach ($campaigns as $c) {
		$subject = $c['settings']['subject_line'] ?? $c['longest_subject_line'] ?? '';
		$status = $c['status'] ?? '';
		$send_time = $c['send_time'] ?? '';
		$list_name = $c['recipients']['list_name'] ?? '';

		print '<tr>';
		print '<td>'.dol_escape_htmltag($subject).'</td>';
		print '<td>'.dol_escape_htmltag($list_name).'</td>';
		print '<td><span class="badge badge-status'.($status === 'sent' ? '4' : '1').'">'.dol_escape_htmltag($status).'</span></td>';
		print '<td>'.dol_escape_htmltag($send_time ? dol_print_date(strtotime($send_time), 'dayhour') : '-').'</td>';
		print '<td class="right">';

		if (in_array($status, array('save', 'paused'))) {
			// Envoyer
			print '<form method="POST" style="display:inline" action="'.$_SERVER["PHP_SELF"].'">';
			print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="send">';
			print '<input type="hidden" name="campaign_id" value="'.dol_escape_htmltag($c['id']).'">';
			print '<input type="submit" class="button" value="'.$langs->trans("MailchimpCampaignSend").'" onclick="return confirm(\''.dol_escape_js($langs->trans("MailchimpCampaignSendConfirm", $subject)).'\');">';
			print '</form> ';
			// Planifier
			print '<form method="POST" style="display:inline" action="'.$_SERVER["PHP_SELF"].'">';
			print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="schedule">';
			print '<input type="hidden" name="campaign_id" value="'.dol_escape_htmltag($c['id']).'">';
			print '<input type="datetime-local" name="schedule_time" required> ';
			print '<input type="submit" class="button" value="'.$langs->trans("MailchimpCampaignSchedule").'">';
			print '</form> ';
		}

		// Test
		print '<form method="POST" style="display:inline" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="test">';
		print '<input type="hidden" name="campaign_id" value="'.dol_escape_htmltag($c['id']).'">';
		print '<input type="email" name="test_email" placeholder="'.$langs->trans("MailchimpTestEmailPlaceholder").'" required> ';
		print '<input type="submit" class="button" value="'.$langs->trans("MailchimpCampaignSendTest").'">';
		print '</form> ';

		if (in_array($status, array('save', 'paused'))) {
			// Supprimer
			print '<form method="POST" style="display:inline" action="'.$_SERVER["PHP_SELF"].'">';
			print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="delete">';
			print '<input type="hidden" name="campaign_id" value="'.dol_escape_htmltag($c['id']).'">';
			print '<input type="submit" class="button button-cancel" value="'.$langs->trans("Delete").'" onclick="return confirm(\''.dol_escape_js($langs->trans("ConfirmDelete")).'\');">';
			print '</form>';
		}

		print '</td></tr>';
	}
	print '</table>';
}

llxFooter();
$db->close();
