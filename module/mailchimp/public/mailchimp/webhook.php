<?php
/* Copyright (C) 2026  Gael <gael@example.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    public/mailchimp/webhook.php
 * \ingroup mailchimp
 * \brief   Recepteur du webhook Mailchimp (desabonnements, mises a jour de profil, nettoyages).
 *
 * URL declaree chez Mailchimp : .../custom/mailchimp/public/webhook.php?secret=xxxx
 * A la creation du webhook, Mailchimp envoie un challenge 'mailchimp_challenge' a echo.
 */

// Load Dolibarr environment (public page, no login)
$res = 0;
if (!$res && file_exists(__DIR__.'/../../../../main.inc.php')) {
	$res = include __DIR__.'/../../../../main.inc.php'; // htdocs/custom/mailchimp/public/mailchimp/
}
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) {
	$res = include __DIR__.'/../../../main.inc.php'; // fallback 2 niveaux
}
if (!$res) {
	die("Failed to include main.inc.php");
}

global $db, $conf;

// Mailchimp validation challenge : renvoyer le parametre tel quel (echo), avant tout controle.
$challenge = $_POST['mailchimp_challenge'] ?? ($_GET['mailchimp_challenge'] ?? '');
if ($challenge !== '') {
	http_response_code(200);
	header('Content-Type: text/plain');
	echo $challenge;
	exit;
}

// Protection par secret dans l'URL (Mailchimp ne signe pas ses webhooks)
$secret = $_GET['secret'] ?? '';
$sql = "SELECT webhook_secret FROM ".MAIN_DB_PREFIX."mailchimp_config WHERE webhook_secret IS NOT NULL AND webhook_secret <> ''";
$resql = $db->query($sql);
$valid_secret = '';
if ($resql) {
	$obj = $db->fetch_object($resql);
	if ($obj) {
		$valid_secret = $obj->webhook_secret;
	}
	$db->free($resql);
}
if (empty($secret) || $secret !== $valid_secret) {
	http_response_code(403);
	exit('Forbidden');
}

// Type d'evenement : subscribe, unsubscribe, profile, upemail, cleaned, campaign
$type = $_POST['type'] ?? '';
$list_id = $_POST['data']['list_id'] ?? '';
$email = $_POST['data']['email'] ?? '';

if ($type !== '' && $list_id !== '' && $email !== '') {
	// Journaliser l'evenement pour traitement (Phase 6 : maj no_email, member_map)
	$payload = json_encode($_POST);
	$sql = "INSERT INTO ".MAIN_DB_PREFIX."mailchimp_sync_log";
	$sql .= " (entity, log_type, object_type, payload, status, date_creation)";
	$sql .= " VALUES (".getEntity('mailchimp').", 'webhook', 'company', '".$db->escape($payload)."', 'pending', '".$db->idate(dol_now())."')";
	$db->query($sql);

	// Phase 6 : traitement immediat pour 'unsubscribe' (maj no_email + member_map)
}

http_response_code(200);
header('Content-Type: text/plain');
echo 'ok';
