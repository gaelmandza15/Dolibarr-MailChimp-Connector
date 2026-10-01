<?php
/* Copyright (C) 2026  Gael <gael@example.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    admin/mailchimp_setup.php
 * \ingroup mailchimp
 * \brief   Page de configuration du module : Connexion, Audiences, Merge fields, Webhook.
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) { $res = include __DIR__.'/../../../main.inc.php'; }
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) { $res = include __DIR__.'/../../main.inc.php'; }
if (!$res) { die('Include of main fails'); }

global $db, $langs, $user, $conf;

require_once dol_buildpath('/mailchimp/lib/mailchimp.lib.php', 0);
require_once dol_buildpath('/mailchimp/class/mailchimpclient.class.php', 0);

if (!$user->admin) {
	accessforbidden();
}

$langs->loadLangs(array("admin", "mailchimp@mailchimp"));

$action = GETPOST('action', 'aZ09');
$tab = GETPOST('tab', 'aZ09');
if (!in_array($tab, array('connection', 'audiences', 'mergefields', 'webhook'))) {
	$tab = 'connection';
}

$config = mailchimp_get_config($db);
$client = mailchimp_get_client($db);

$errors = array();
$messages = array();
$test_result = null;
$lists = array();

// Actions
if ($action == 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	// Onglet connexion : cle API
	$newapikey = trim(GETPOST('MAILCHIMP_APIKEY', 'none'));
	if ($newapikey !== '' && $newapikey !== '********') {
		try {
			$probe = new MailchimpClient($newapikey);
			$dc = $probe->getDatacenter();
			$enc = mailchimp_encrypt($newapikey);
			if ($enc === false) {
				$errors[] = $langs->trans("MailchimpErrorEncrypt");
			} else {
				$res = mailchimp_save_config($db, array('apikey_enc' => $enc, 'datacenter' => $dc));
				$messages[] = $langs->trans("MailchimpSetupSaved", $dc);
				$config = mailchimp_get_config($db);
				$client = mailchimp_get_client($db);
			}
		} catch (MailchimpApiException $e) {
			$errors[] = $langs->trans("MailchimpErrorInvalidKey").' ('.$e->getMessage().')';
		}
	}

	// Onglet mergefields / expediteur par defaut
	if (GETPOSTISSET('MAILCHIMP_FROM_NAME')) {
		mailchimp_save_config($db, array(
			'from_name' => GETPOST('MAILCHIMP_FROM_NAME', 'alpha'),
			'reply_to' => GETPOST('MAILCHIMP_REPLY_TO', 'alpha'),
			'merge_fields_map' => json_encode(array(
				'FNAME' => GETPOST('MERGE_FNAME', 'alpha'),
				'LNAME' => GETPOST('MERGE_LNAME', 'alpha'),
				'SOCIETE' => GETPOST('MERGE_SOCIETE', 'alpha'),
			)),
		));
		$messages[] = $langs->trans("MailchimpSetupSaved", '');
		$config = mailchimp_get_config($db);
	}
}

if ($action == 'testconn') {
	if ($client === null) {
		$errors[] = $langs->trans("MailchimpNoApiKey");
	} else {
		try {
			$ping = $client->ping();
			$test_result = true;
			$messages[] = $langs->trans("MailchimpTestOk", isset($ping['health_status']) ? $ping['health_status'] : 'OK');
		} catch (MailchimpApiException $e) {
			$test_result = false;
			$errors[] = $langs->trans("MailchimpTestKo").' ['.$e->status.'] '.$e->getMessage().' - '.$e->detail;
		}
	}
}

if ($action == 'fetchlists') {
	if ($client === null) {
		$errors[] = $langs->trans("MailchimpNoApiKey");
	} else {
		try {
			$lists = $client->get('/lists', array('count' => 50));
			if (isset($lists['lists'])) {
				$lists = $lists['lists'];
			}
		} catch (MailchimpApiException $e) {
			$errors[] = '['.$e->status.'] '.$e->getMessage().' - '.$e->detail;
		}
	}
}

if ($action == 'savelist') {
	$list_id = GETPOST('default_list_id', 'alpha');
	mailchimp_save_config($db, array('default_list_id' => $list_id));
	$messages[] = $langs->trans("MailchimpSetupSaved", $list_id);
	$config = mailchimp_get_config($db);
}

if ($action == 'savewebhook') {
	$secret = $config['webhook_secret'];
	if (empty($secret)) {
		$secret = mailchimp_generate_secret(32);
	}
	mailchimp_save_config($db, array(
		'webhook_secret' => $secret,
		'webhook_enabled' => GETPOST('webhook_enabled', 'int') ? 1 : 0,
	));
	$messages[] = $langs->trans("MailchimpSetupSaved", '');
	$config = mailchimp_get_config($db);
}

if ($action == 'registerwebhook') {
	if ($client === null) {
		$errors[] = $langs->trans("MailchimpNoApiKey");
	} elseif (empty($config['default_list_id'])) {
		$errors[] = $langs->trans("MailchimpNoAudienceSelected");
	} elseif (empty($config['webhook_secret']) || !$config['webhook_enabled']) {
		$errors[] = $langs->trans("MailchimpWebhookNotConfigured");
	} else {
		$webhook_url = getDolGlobalString('MAIN_URL_ROOT', DOL_MAIN_URL_ROOT).'/custom/mailchimp/public/mailchimp/webhook.php?secret='.urlencode($config['webhook_secret']);
		try {
			$client->post('/lists/'.rawurlencode($config['default_list_id']).'/webhooks', array(
				'url' => $webhook_url,
				'events' => array('subscribe' => true, 'unsubscribe' => true, 'profile' => true, 'cleaned' => true, 'upemail' => false, 'campaign' => false),
				'sources' => array('user' => true, 'admin' => true, 'api' => true),
			));
			$messages[] = $langs->trans("MailchimpWebhookRegistered", $webhook_url);
		} catch (MailchimpApiException $e) {
			$errors[] = '['.$e->status.'] '.$e->getMessage().' - '.$e->detail;
		}
	}
}

/*
 * View
 */

$help_url = '';
llxHeader('', $langs->trans("MailchimpSetup"), $help_url, '', 0, 0, '', '', '', 'mod-mailchimp admin-page');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("MailchimpSetup"), $linkback, 'email');

$head = mailchimp_admin_prepare_head($tab);
print dol_get_fiche_head($head, $tab, $langs->trans("MailchimpSetup"), -1, 'email');

foreach ($errors as $e) {
	setEventMessages($e, null, 'errors'); dol_htmloutput_events();
}
foreach ($messages as $m) {
	setEventMessages($m, null, 'mesgs'); dol_htmloutput_events();
}

// ---------------------------------------------------------------- Onglet Connexion
if ($tab == 'connection') {
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?tab=connection">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="save">';

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans("Parameter").'</td><td>'.$langs->trans("Value").'</td></tr>';

	print '<tr><td>'.$langs->trans("MailchimpApiKey").'</td><td>';
	print '<input type="password" name="MAILCHIMP_APIKEY" value="'.(empty($config['apikey']) ? '' : '********').'" size="60" autocomplete="off">';
	print '<br><span class="opacitymedium">'.$langs->trans("MailchimpApiKeyHint", $config['datacenter']).'</span>';
	print '</td></tr>';

	print '<tr><td>'.$langs->trans("MailchimpDatacenter").'</td><td>'.dol_escape_htmltag($config['datacenter']).'</td></tr>';

	print '</table>';

	print $form->buttonsSaveCancel("Save", '');
	print '</form>';

	// Test de connexion
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?tab=connection">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="testconn">';
	print $form->buttonsSaveCancel("MailchimpTestConnection", '', array(), 0, '', 1);
	print '</form>';

	print '<br>'.$langs->trans("MailchimpCreateKeyHint", 'https://admin.mailchimp.com/account/api/');
}

// ---------------------------------------------------------------- Onglet Audiences
if ($tab == 'audiences') {
	if ($client === null) {
		setEventMessages($langs->trans("MailchimpNoApiKey"), null, 'warnings'); dol_htmloutput_events();
	} else {
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?tab=audiences">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="fetchlists">';
		print $form->buttonsSaveCancel("MailchimpFetchAudiences", '', array(), 0, '', 1);
		print '</form>';

		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?tab=audiences">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="savelist">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre"><td>'.$langs->trans("MailchimpAudience").'</td><td>'.$langs->trans("Members").'</td></tr>';
		print '<tr><td>';
		print '<select name="default_list_id">';
		print '<option value="">-- '.$langs->trans("None").' --</option>';
		foreach ($lists as $l) {
			$selected = ($config['default_list_id'] === $l['id']) ? ' selected' : '';
			print '<option value="'.dol_escape_htmltag($l['id']).'"'.$selected.'>'.dol_escape_htmltag($l['name']).' ('.((int) $l['stats']['member_count']).')</option>';
		}
		print '</select></td><td></td></tr>';
		print '</table>';
		print $form->buttonsSaveCancel("Save", '');
		print '</form>';
	}
}

// ---------------------------------------------------------------- Onglet Merge fields
if ($tab == 'mergefields') {
	$map = json_decode($config['merge_fields_map'], true);
	if (!is_array($map)) {
		$map = array('FNAME' => '', 'LNAME' => '', 'SOCIETE' => '');
	}
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?tab=mergefields">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="save">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans("MailchimpMergeField").'</td><td>'.$langs->trans("MailchimpDolibarrSource").'</td></tr>';
	print '<tr><td>FNAME</td><td><input name="MERGE_FNAME" value="'.dol_escape_htmltag($map['FNAME']).'"></td></tr>';
	print '<tr><td>LNAME</td><td><input name="MERGE_LNAME" value="'.dol_escape_htmltag($map['LNAME']).'"></td></tr>';
	print '<tr><td>SOCIETE</td><td><input name="MERGE_SOCIETE" value="'.dol_escape_htmltag($map['SOCIETE']).'"></td></tr>';
	print '<tr class="liste_titre"><td>'.$langs->trans("MailchimpSender").'</td><td></td></tr>';
	print '<tr><td>'.$langs->trans("MailchimpFromName").'</td><td><input name="MAILCHIMP_FROM_NAME" value="'.dol_escape_htmltag($config['from_name']).'"></td></tr>';
	print '<tr><td>'.$langs->trans("MailchimpReplyTo").'</td><td><input name="MAILCHIMP_REPLY_TO" value="'.dol_escape_htmltag($config['reply_to']).'"></td></tr>';
	print '</table>';
	print $form->buttonsSaveCancel("Save", '');
	print '</form>';
}

// ---------------------------------------------------------------- Onglet Webhook
if ($tab == 'webhook') {
	$webhook_url = DOL_MAIN_URL_ROOT.'/custom/mailchimp/public/webhook.php?secret='.urlencode($config['webhook_secret']);
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?tab=webhook">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="savewebhook">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans("Parameter").'</td><td>'.$langs->trans("Value").'</td></tr>';
	print '<tr><td>'.$langs->trans("MailchimpWebhookEnabled").'</td><td><input type="checkbox" name="webhook_enabled" value="1"'.($config['webhook_enabled'] ? ' checked' : '').'></td></tr>';
	print '<tr><td>'.$langs->trans("MailchimpWebhookUrl").'</td><td><code>'.dol_escape_htmltag($webhook_url).'</code><br><span class="opacitymedium">'.$langs->trans("MailchimpWebhookHint").'</span></td></tr>';
	print '</table>';
	print $form->buttonsSaveCancel("Save", '');
	print '</form>';

	// Enregistrement automatique du webhook chez Mailchimp
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?tab=webhook">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="registerwebhook">';
	print $form->buttonsSaveCancel("MailchimpWebhookRegister", '', array(), 0, '', 1);
	print '</form>';
	print '<br><span class="opacitymedium">'.$langs->trans("MailchimpWebhookRegisterHint").'</span>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
