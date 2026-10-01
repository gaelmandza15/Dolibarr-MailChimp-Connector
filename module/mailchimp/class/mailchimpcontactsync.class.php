<?php
/* Copyright (C) 2026  Gael <gael@example.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    class/mailchimpcontactsync.class.php
 * \ingroup mailchimp
 * \brief   Synchronisation des contacts Dolibarr vers Mailchimp (implémentation complète en Phase 2).
 */

require_once dol_buildpath('/mailchimp/class/mailchimpclient.class.php', 0);
require_once dol_buildpath('/mailchimp/lib/mailchimp.lib.php', 0);

/**
 * Classe de synchronisation des contacts.
 *
 * Phase 2 : mapping tiers/contacts -> membres Mailchimp, dry-run, sync par lots (batch 500),
 * sync incrémentale via cron, traitement de la file d'attente alimentée par les triggers.
 * Ce squelette fournit la structure, la file d'attente et les points d'entrée cron.
 */
class MailchimpContactSync
{
	/** @var DoliDB */
	private $db;

	/** @var MailchimpClient|null */
	private $client;

	/** @var array Configuration du module */
	private $config;

	/**
	 * @param DoliDB $db
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->config = mailchimp_get_config($db);
		if (!empty($this->config['apikey'])) {
			try {
				$this->client = new MailchimpClient($this->config['apikey']);
			} catch (MailchimpApiException $e) {
				$this->client = null;
			}
		}
	}

	/**
	 * Construit le payload Mailchimp d'un tiers ou d'un contact.
	 * Phase 2 : mapping complet (merge fields configurables, tags = catégories).
	 *
	 * @param array $record array('email'=>..., 'first_name'=>..., 'last_name'=>..., 'company'=>...)
	 * @return array Payload au format membre Mailchimp
	 */
	public function buildMemberPayload($record)
	{
		$payload = array(
			'email_address' => $record['email'],
			'status_if_new' => 'subscribed',
		);
		if (!empty($record['first_name'])) {
			$payload['merge_fields']['FNAME'] = $record['first_name'];
		}
		if (!empty($record['last_name'])) {
			$payload['merge_fields']['LNAME'] = $record['last_name'];
		}
		if (!empty($record['company'])) {
			$payload['merge_fields']['SOCIETE'] = $record['company'];
		}
		return $payload;
	}

	/**
	 * Ajoute un événement dans la file d'attente (llx_mailchimp_sync_log).
	 * Appelé par les triggers COMPANY_*/CONTACT_*.
	 *
	 * @param string $object_type company|contact
	 * @param int    $fk_object
	 * @param string $event       CREATE|MODIFY|DELETE
	 * @return int 1 si OK, -1 si erreur
	 */
	public function queueEvent($object_type, $fk_object, $event)
	{
		$payload = json_encode(array('event' => $event, 'object_type' => $object_type, 'fk_object' => (int) $fk_object));
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."mailchimp_sync_log";
		$sql .= " (entity, log_type, object_type, fk_object, payload, status, date_creation)";
		$sql .= " VALUES (".getEntity('mailchimp').", 'queue', '".$this->db->escape($object_type)."', ".((int) $fk_object);
		$sql .= ", '".$this->db->escape($payload)."', 'pending', '".$this->db->idate(dol_now())."')";
		$resql = $this->db->query($sql);
		return $resql ? 1 : -1;
	}

	/**
	 * Traite la file d'attente des événements (déclenché après sync incrémentale en Phase 2).
	 * Phase 2 : lit les lignes 'queue'/'pending', convertit en opérations Mailchimp,
	 * les envoie par lots de 500 via l'endpoint batch, marque ok/error.
	 *
	 * @return int Nombre d'événements traités
	 */
	public function processQueue()
	{
		if ($this->client === null) {
			return 0;
		}
		// Phase 2 : implémentation de la conversion file -> opérations -> batch
		return 0;
	}

	/**
	 * Tâche cron : synchronisation incrémentale des contacts (toutes les 15 min).
	 *
	 * @return int 0 si OK, code > 0 si erreur (convention cron Dolibarr)
	 */
	public function runCronIncremental()
	{
		if ($this->client === null) {
			return 0; // Pas configure : rien a faire (pas d'erreur cron)
		}
		$processed = $this->processQueue();
		// Phase 2 : selection des tiers/contacts modifies depuis la derniere sync (tms),
		// diff avec llx_mailchimp_member_map, envoie par lots, maj du mapping.
		return 0;
	}

	/**
	 * Tâche cron : réconciliation des désabonnements (toutes les 24 h, fallback webhook).
	 * Phase 6 : parcourt GET /lists/{id}/members?status=unsubscribed,cleaned et met
	 * a jour no_email + llx_mailchimp_member_map.
	 *
	 * @return int 0 si OK
	 */
	public function runCronOptoutReconciliation()
	{
		if ($this->client === null) {
			return 0;
		}
		// Phase 6
		return 0;
	}
}
