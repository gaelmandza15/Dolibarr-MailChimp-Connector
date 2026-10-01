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
 * \file    public/mailchimp/webhook.php
 * \ingroup mailchimp
 * \brief   Recepteur du webhook Mailchimp (desabonnements, mises a jour de profil, nettoyages).
 *
 * URL declaree chez Mailchimp : .../custom/mailchimp/public/mailchimp/webhook.php?secret=xxxx
 * A la creation du webhook, Mailchimp envoie un challenge 'mailchimp_challenge' a echo.
 */

// Load Dolibarr environment (public page, no login)
// Endpoint webhook : POST venant de Mailchimp, sans session ni token CSRF
if (!defined('NOLOGIN')) {
	define('NOLOGIN', '1');
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', '1');
}
if (!defined('NOIPCHECK')) {
	define('NOIPCHECK', '1');
}
$res = 0;
if (!$res && file_exists(__DIR__.'/../../../../main.inc.php')) {
	$res = include __DIR__.'/../../../../main.inc.php'; // htdocs/custom/mailchimp/public/mailchimp/
}
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) {
	$res = include __DIR__.'/../../../main.inc.php'; // fallback module a la racine htdocs
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
$sql = "SELECT webhook_secret, webhook_enabled FROM ".MAIN_DB_PREFIX."mailchimp_config";
$sql .= " WHERE webhook_secret IS NOT NULL AND webhook_secret <> '' AND webhook_enabled = 1";
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
$data = $_POST['data'] ?? array();
$list_id = is_array($data) ? ($data['list_id'] ?? '') : '';
$email = is_array($data) ? ($data['email'] ?? '') : '';

if ($type === '' || $list_id === '' || $email === '') {
	http_response_code(400);
	exit('Bad request');
}

// Journaliser puis traiter l'evenement
$payload = json_encode($_POST);
$sql = "INSERT INTO ".MAIN_DB_PREFIX."mailchimp_sync_log";
$sql .= " (entity, log_type, object_type, payload, status, date_creation)";
$sql .= " VALUES (".getEntity('mailchimp').", 'webhook', 'contact', '".$db->escape($payload)."', 'ok', '".$db->idate(dol_now())."')";
$db->query($sql);

require_once dol_buildpath('/mailchimp/class/mailchimpcontactsync.class.php', 0);
$sync = new MailchimpContactSync($db);
$result = $sync->processWebhookEvent($type, $data);

http_response_code(200);
header('Content-Type: text/plain');
echo $result === 'ok' ? 'ok' : 'ignored';
