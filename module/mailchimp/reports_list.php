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
 * \file    reports_list.php
 * \ingroup mailchimp
 * \brief   Rapports de performance des campagnes Mailchimp (KPIs + drill-down).
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) { $res = include __DIR__.'/../../main.inc.php'; }
if (!$res && file_exists(__DIR__.'/../main.inc.php')) { $res = include __DIR__.'/../main.inc.php'; }
if (!$res) { die('Include of main fails'); }

global $db, $langs, $user, $conf;

require_once dol_buildpath('/mailchimp/lib/mailchimp.lib.php', 0);
require_once dol_buildpath('/mailchimp/class/mailchimpcampaign.class.php', 0);

if (!$user->rights->mailchimp->read) {
	accessforbidden();
}

$langs->loadLangs(array("mailchimp@mailchimp"));

$action = GETPOST('action', 'aZ09');
$campaign_id = GETPOST('campaign_id', 'alpha');

$campaign = new MailchimpCampaign($db);
$client = $campaign->getClient();

$errors = array();

if ($action == 'refresh') {
	if (!empty($user->rights->mailchimp->write)) {
		$n = $campaign->refreshStats();
		setEventMessages($langs->trans("MailchimpStatsRefreshed", $n), null, 'mesgs');
	} else {
		$errors[] = $langs->trans("NotEnoughPermissions");
	}
}

llxHeader('', $langs->trans("MailchimpReports"), '', '', 0, 0, '', '', '', 'mod-mailchimp page-reports');

print load_fiche_titre($langs->trans("MailchimpReports"), '', 'email');

dol_htmloutput_events();
foreach ($errors as $e) {
	print '<div class="error">'.dol_escape_htmltag($e).'</div>';
}

if ($client === null) {
	print '<div class="warning">'.$langs->trans("MailchimpNoApiKey").'</div>';
	llxFooter();
	$db->close();
	exit;
}

print '<div class="tabsAction">';
print '<form method="POST" style="display:inline" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="refresh">';
print '<input type="submit" class="butAction" value="'.$langs->trans("MailchimpStatsRefresh").'">';
print '</form></div>';

// Rapports des campagnes envoyees
$reports = array();
try {
	$reports = $campaign->listReports(200);
} catch (MailchimpApiException $e) {
	print '<div class="error">['.$e->status.'] '.dol_escape_htmltag($e->getMessage().' - '.$e->detail).'</div>';
}

if (empty($reports)) {
	print '<div class="opacitymedium">'.$langs->trans("MailchimpNoReports").'</div>';
}

// Totaux agreges
$total_sent = 0;
$total_opens = 0;
$total_unique_opens = 0;
$total_clicks = 0;
$total_unsub = 0;
$total_bounces = 0;
foreach ($reports as $r) {
	$total_sent += (int) ($r['emails_sent'] ?? 0);
	$total_opens += (int) ($r['opens']['opens_total'] ?? 0);
	$total_unique_opens += (int) ($r['opens']['unique_opens'] ?? 0);
	$total_clicks += (int) ($r['clicks']['clicks_total'] ?? 0);
	$total_unsub += (int) ($r['unsubscribed'] ?? 0);
	$total_bounces += (int) ($r['bounces'] ?? 0);
}

// Cartes KPI
$kpis = array(
	array('MailchimpKpiEmailsSent', $total_sent, 'fa-envelope'),
	array('MailchimpKpiTotalOpens', $total_opens, 'fa-envelope-open'),
	array('MailchimpKpiUniqueOpens', $total_unique_opens, 'fa-user-check'),
	array('MailchimpKpiTotalClicks', $total_clicks, 'fa-mouse-pointer'),
	array('MailchimpKpiUnsubscribes', $total_unsub, 'fa-user-minus'),
	array('MailchimpKpiBounces', $total_bounces, 'fa-exclamation-triangle'),
);

print '<div class="fichecenter">';
foreach ($kpis as $kpi) {
	print '<div style="display:inline-block; width:16%; min-width:130px; margin:5px; text-align:center;" class="ficheaddleft">';
	print '<div class="info-box">';
	print '<span class="info-box-icon"><i class="fa '.$kpi[2].'"></i></span>';
	print '<div class="info-box-content"><span class="info-box-number">'.$kpi[1].'</span>';
	print '<span class="info-box-text">'.$langs->trans($kpi[0]).'</span></div>';
	print '</div></div>';
}
print '</div>';

// Tableau detaille
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>Campagne</td><td>Envoi</td><td class="right">Envoyes</td><td class="right">Ouvertures</td><td class="right">Taux ouverture</td><td class="right">Clics</td><td class="right">Taux clic</td><td class="right">Desab.</td><td class="right">Rebonds</td><td></td></tr>';

foreach ($reports as $r) {
	$opens = $r['opens'] ?? array();
	$clicks = $r['clicks'] ?? array();
	print '<tr>';
	print '<td>'.dol_escape_htmltag($r['campaign_title'] ?? $r['id']).'</td>';
	print '<td>'.dol_escape_htmltag(!empty($r['send_time']) ? dol_print_date(strtotime($r['send_time']), 'dayhour') : '-').'</td>';
	print '<td class="right">'.((int) ($r['emails_sent'] ?? 0)).'</td>';
	print '<td class="right">'.((int) ($opens['unique_opens'] ?? 0)).' / '.((int) ($opens['opens_total'] ?? 0)).'</td>';
	print '<td class="right">'.round(100 * (float) ($opens['open_rate'] ?? 0), 1).' %</td>';
	print '<td class="right">'.((int) ($clicks['unique_subscriber_clicks'] ?? 0)).' / '.((int) ($clicks['clicks_total'] ?? 0)).'</td>';
	print '<td class="right">'.round(100 * (float) ($clicks['click_rate'] ?? 0), 1).' %</td>';
	print '<td class="right">'.((int) ($r['unsubscribed'] ?? 0)).'</td>';
	print '<td class="right">'.((int) ($r['bounces'] ?? 0)).'</td>';
	print '<td><a class="butAction" href="'.$_SERVER["PHP_SELF"].'?campaign_id='.urlencode($r['id']).'">'.$langs->trans("MailchimpReportDetails").'</a></td>';
	print '</tr>';
}
print '</table>';

// Drill-down d'une campagne
if ($campaign_id !== '') {
	print load_fiche_titre($langs->trans("MailchimpReportDetails"), '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'">'.$langs->trans("BackToList").'</a>', 'email');
	try {
		$opens_detail = $client->get('/reports/'.rawurlencode($campaign_id).'/open-details', array('count' => 100, 'fields' => 'members.email_address,members.opens_count,members.opens'));
		$clicks_detail = $client->get('/reports/'.rawurlencode($campaign_id).'/click-details', array('count' => 20, 'fields' => 'urls.url,urls.unique_clicks,urls.total_clicks'));
		$unsub = $client->get('/reports/'.rawurlencode($campaign_id).'/unsubscribed', array('count' => 100, 'fields' => 'members.email_address,members.reason'));
	} catch (MailchimpApiException $e) {
		print '<div class="error">['.$e->status.'] '.dol_escape_htmltag($e->getMessage().' - '.$e->detail).'</div>';
		$opens_detail = array();
		$clicks_detail = array();
		$unsub = array();
	}

	// Clics par lien
	print '<h3>'.$langs->trans("MailchimpReportClicksByLink").'</h3>';
	if (!empty($clicks_detail['urls'])) {
		print '<table class="noborder centpercent"><tr class="liste_titre"><td>Lien</td><td class="right">Clics uniques</td><td class="right">Clics totaux</td></tr>';
		foreach ($clicks_detail['urls'] as $u) {
			print '<tr><td>'.dol_escape_htmltag($u['url']).'</td><td class="right">'.((int) $u['unique_clicks']).'</td><td class="right">'.((int) $u['total_clicks']).'</td></tr>';
		}
		print '</table>';
	} else {
		print '<div class="opacitymedium">'.$langs->trans("MailchimpNoData").'</div>';
	}

	// Ouvertures par destinataire
	print '<h3>'.$langs->trans("MailchimpReportOpensByRecipient").'</h3>';
	if (!empty($opens_detail['members'])) {
		print '<table class="noborder centpercent"><tr class="liste_titre"><td>Email</td><td class="right">Ouvertures</td></tr>';
		foreach (array_slice($opens_detail['members'], 0, 100) as $m) {
			print '<tr><td>'.dol_escape_htmltag($m['email_address']).'</td><td class="right">'.((int) $m['opens_count']).'</td></tr>';
		}
		print '</table>';
	} else {
		print '<div class="opacitymedium">'.$langs->trans("MailchimpNoData").'</div>';
	}

	// Desabonnements
	print '<h3>'.$langs->trans("MailchimpKpiUnsubscribes").'</h3>';
	if (!empty($unsub['members'])) {
		print '<table class="noborder centpercent"><tr class="liste_titre"><td>Email</td><td>Motif</td></tr>';
		foreach ($unsub['members'] as $m) {
			print '<tr><td>'.dol_escape_htmltag($m['email_address']).'</td><td>'.dol_escape_htmltag($m['reason'] ?? '').'</td></tr>';
		}
		print '</table>';
	} else {
		print '<div class="opacitymedium">'.$langs->trans("MailchimpNoData").'</div>';
	}
}

llxFooter();
$db->close();
