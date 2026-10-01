<?php
/* Copyright (C) 2026  Gael <gael@example.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    lib/mailchimp.lib.php
 * \ingroup mailchimp
 * \brief   Bibliotheque d'objets de la page d'administration (onglets) + helpers.
 */

require_once __DIR__.'/mailchimp_crypto.lib.php';

/**
 * Prepare l'entete d'onglets des pages d'administration du module.
 *
 * @param string $selected Onglet selectionne
 * @return array
 */
function mailchimp_admin_prepare_head($selected = 'connection')
{
	global $langs, $conf, $user;

	$langs->load("mailchimp@mailchimp");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath('/mailchimp/admin/mailchimp_setup.php', 1).'?tab=connection';
	$head[$h][1] = $langs->trans("MailchimpTabConnection");
	$head[$h][2] = 'connection';
	$h++;

	$head[$h][0] = dol_buildpath('/mailchimp/admin/mailchimp_setup.php', 1).'?tab=audiences';
	$head[$h][1] = $langs->trans("MailchimpTabAudiences");
	$head[$h][2] = 'audiences';
	$h++;

	$head[$h][0] = dol_buildpath('/mailchimp/admin/mailchimp_setup.php', 1).'?tab=mergefields';
	$head[$h][1] = $langs->trans("MailchimpTabMergeFields");
	$head[$h][2] = 'mergefields';
	$h++;

	$head[$h][0] = dol_buildpath('/mailchimp/admin/mailchimp_setup.php', 1).'?tab=webhook';
	$head[$h][1] = $langs->trans("MailchimpTabWebhook");
	$head[$h][2] = 'webhook';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'mailchimp', 'admin');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'mailchimp', 'admin', 'remove');

	return $head;
}

/**
 * Retourne la configuration du module pour l'entite courante (lecture dans llx_mailchimp_config).
 *
 * @param DoliDB $db
 * @return array{apikey:string,datacenter:string,default_list_id:string,from_name:string,reply_to:string,webhook_secret:string,webhook_enabled:int}
 */
function mailchimp_get_config($db)
{
	$out = array(
		'apikey' => '',
		'datacenter' => '',
		'default_list_id' => '',
		'merge_fields_map' => '',
		'from_name' => '',
		'reply_to' => '',
		'webhook_secret' => '',
		'webhook_enabled' => 0,
	);

	$entity = getEntity('mailchimp', 0);
	$sql = "SELECT rowid, apikey_enc, datacenter, default_list_id, merge_fields_map, from_name, reply_to, webhook_secret, webhook_enabled";
	$sql .= " FROM ".MAIN_DB_PREFIX."mailchimp_config WHERE entity = ".((int) $entity)." LIMIT 1";
	$resql = $db->query($sql);
	if ($resql) {
		$obj = $db->fetch_object($resql);
		if ($obj) {
			$out['apikey'] = mailchimp_decrypt($obj->apikey_enc);
			$out['datacenter'] = $obj->datacenter;
			$out['default_list_id'] = $obj->default_list_id;
			$out['merge_fields_map'] = $obj->merge_fields_map;
			$out['from_name'] = $obj->from_name;
			$out['reply_to'] = $obj->reply_to;
			$out['webhook_secret'] = $obj->webhook_secret;
			$out['webhook_enabled'] = (int) $obj->webhook_enabled;
		}
		$db->free($resql);
	}

	return $out;
}

/**
 * Sauvegarde la configuration du module pour l'entite courante (upsert dans llx_mailchimp_config).
 *
 * @param DoliDB $db
 * @param array  $fields Champs a ecrire (apikey_enc, datacenter, default_list_id, ...)
 * @return int 1 si OK, -1 si erreur
 */
function mailchimp_save_config($db, $fields)
{
	global $user;

	$entity = getEntity('mailchimp', 0);

	$entity = getEntity('mailchimp', 0);

	// Determiner si une ligne existe deja
	$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."mailchimp_config WHERE entity = ".((int) $entity)." LIMIT 1";
	$resql = $db->query($sql);
	$existing_rowid = 0;
	if ($resql) {
		$obj = $db->fetch_object($resql);
		if ($obj) {
			$existing_rowid = (int) $obj->rowid;
		}
		$db->free($resql);
	}

	$allowed = array('apikey_enc', 'datacenter', 'default_list_id', 'merge_fields_map', 'from_name', 'reply_to', 'webhook_secret', 'webhook_enabled');
	$set = array();
	foreach ($allowed as $field) {
		if (array_key_exists($field, $fields)) {
			$set[] = $field." = ".(is_null($fields[$field]) ? "NULL" : "'".$db->escape($fields[$field])."'");
		}
	}
	if (empty($set)) {
		return 1;
	}

	if ($existing_rowid > 0) {
		$sql = "UPDATE ".MAIN_DB_PREFIX."mailchimp_config SET ".implode(', ', $set)." WHERE rowid = ".((int) $existing_rowid);
	} else {
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."mailchimp_config (entity, ".implode(', ', array_keys(array_intersect_key($fields, array_flip($allowed)))).", date_creation, fk_user_creat)";
		$values = array((string) (int) $entity);
		foreach ($allowed as $field) {
			if (array_key_exists($field, $fields)) {
				$values[] = is_null($fields[$field]) ? "NULL" : "'".$db->escape($fields[$field])."'";
			}
		}
		$sql .= " VALUES (".implode(', ', $values).", '".$db->idate(dol_now())."', ".((int) $user->id).")";
	}

	$resql = $db->query($sql);
	if (!$resql) {
		return -1;
	}
	return 1;
}

/**
 * Construit une instance MailchimpClient a partir de la config enregistree, si possible.
 *
 * @param DoliDB $db
 * @return MailchimpClient|null Null si la cle n'est pas configuree
 */
function mailchimp_get_client($db)
{
	$config = mailchimp_get_config($db);
	if (empty($config['apikey'])) {
		return null;
	}
	try {
		$client = new MailchimpClient($config['apikey']);
		// Resynchroniser le datacenter si la cle a change
		if ($client->getDatacenter() !== $config['datacenter']) {
			mailchimp_save_config($db, array('datacenter' => $client->getDatacenter()));
		}
		return $client;
	} catch (MailchimpApiException $e) {
		return null;
	}
}
