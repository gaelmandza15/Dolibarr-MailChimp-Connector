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
 * \file    class/mailchimpcontactsync.class.php
 * \ingroup mailchimp
 * \brief   Synchronisation des contacts Dolibarr vers Mailchimp.
 */

require_once dol_buildpath('/mailchimp/class/mailchimpclient.class.php', 0);
require_once dol_buildpath('/mailchimp/lib/mailchimp.lib.php', 0);

/**
 * Classe de synchronisation des contacts Dolibarr -> Mailchimp.
 *
 * Sources : llx_socpeople (contacts) et llx_societe (tiers avec email générique).
 * Éligibilité : email valide, no_email = 0, filtres catégorie/statut optionnels.
 * Envoi : POST /lists/{id} (batch subscribe, 500 max par appel, update_existing).
 * Traçage : llx_mailchimp_member_map (mapping) + llx_mailchimp_sync_log (journal).
 */
class MailchimpContactSync
{
	/** @var DoliDB */
	private $db;

	/** @var MailchimpClient|null */
	private $client;

	/** @var array Configuration du module */
	private $config;

	/** @var int Taille max d'un lot Mailchimp */
	const BATCH_SIZE = 500;

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
	 * Client API (null si non configuré).
	 * @return MailchimpClient|null
	 */
	public function getClient()
	{
		return $this->client;
	}

	/**
	 * Sélectionne les enregistrements éligibles côté Dolibarr.
	 *
	 * @param array $filters array('sources'=>array('contact','company'), 'category_id'=>int, 'only_modified_since'=>int timestamp)
	 * @return array Liste de array('object_type','fk_object','email','first_name','last_name','company','tags'=>array())
	 */
	public function getEligibleRecords($filters = array())
	{
		$sources = isset($filters['sources']) && is_array($filters['sources']) && !empty($filters['sources'])
			? $filters['sources'] : array('contact', 'company');
		$category_id = (int) (empty($filters['category_id']) ? 0 : $filters['category_id']);
		$since = empty($filters['only_modified_since']) ? 0 : (int) $filters['only_modified_since'];

		$records = array();

		if (in_array('contact', $sources)) {
			$sql = "SELECT p.rowid, p.email, p.firstname, p.lastname, p.no_email, s.nom as company";
			$sql .= " FROM ".MAIN_DB_PREFIX."socpeople p";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = p.fk_soc";
			$sql .= " WHERE p.entity IN (".getEntity('socpeople', 1).")";
			$sql .= " AND p.email <> '' AND p.no_email = 0";
			// Opt-out global emailing (module Emailing)
			$sql .= " AND NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."mailing_unsubscribe u WHERE u.email = p.email AND u.entity IN (".getEntity('mailing', 1)."))";
			if ($since > 0) {
				$sql .= " AND p.tms >= '".$this->db->idate($since)."'";
			}
			if ($category_id > 0) {
				$sql .= " AND p.rowid IN (SELECT fk_socpeople FROM ".MAIN_DB_PREFIX."categorie_contact WHERE fk_categorie = ".((int) $category_id).")";
			}
			$resql = $this->db->query($sql);
			if ($resql) {
				while ($obj = $this->db->fetch_object($resql)) {
					$tags = $this->getObjectCategoryTags('contact', $obj->rowid);
					$records[] = array(
						'object_type' => 'contact',
						'fk_object' => (int) $obj->rowid,
						'email' => trim($obj->email),
						'first_name' => $obj->firstname,
						'last_name' => $obj->lastname,
						'company' => $obj->company,
						'tags' => $tags,
					);
				}
				$this->db->free($resql);
			}
		}

		if (in_array('company', $sources)) {
			$sql = "SELECT s.rowid, s.email, s.nom";
			$sql .= " FROM ".MAIN_DB_PREFIX."societe s";
			$sql .= " WHERE s.entity IN (".getEntity('societe', 1).")";
			$sql .= " AND s.email <> ''";
			// llx_societe n'a pas de no_email : opt-out global via llx_mailing_unsubscribe
			$sql .= " AND NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."mailing_unsubscribe u WHERE u.email = s.email AND u.entity IN (".getEntity('mailing', 1)."))";
			if ($since > 0) {
				$sql .= " AND s.tms >= '".$this->db->idate($since)."'";
			}
			if ($category_id > 0) {
				$sql .= " AND s.rowid IN (SELECT fk_soc FROM ".MAIN_DB_PREFIX."categorie_societe WHERE fk_categorie = ".((int) $category_id).")";
			}
			$resql = $this->db->query($sql);
			if ($resql) {
				while ($obj = $this->db->fetch_object($resql)) {
					$tags = $this->getObjectCategoryTags('company', $obj->rowid);
					$records[] = array(
						'object_type' => 'company',
						'fk_object' => (int) $obj->rowid,
						'email' => trim($obj->email),
						'first_name' => '',
						'last_name' => '',
						'company' => $obj->nom,
						'tags' => $tags,
					);
				}
				$this->db->free($resql);
			}
		}

		// Dédoublonnage par email (priorité au contact, plus riche)
		$by_email = array();
		foreach ($records as $rec) {
			$key = strtolower($rec['email']);
			if (!isset($by_email[$key]) || $rec['object_type'] === 'contact') {
				$by_email[$key] = $rec;
			}
		}

		return array_values($by_email);
	}

	/**
	 * Catégories d'un objet au format tags Mailchimp.
	 *
	 * @param string $object_type contact|company
	 * @param int    $fk_object
	 * @return array
	 */
	private function getObjectCategoryTags($object_type, $fk_object)
	{
		$table = $object_type === 'contact' ? 'categorie_contact' : 'categorie_societe';
		$col = $object_type === 'contact' ? 'fk_socpeople' : 'fk_soc';
		$tags = array();
		$sql = "SELECT c.label FROM ".MAIN_DB_PREFIX."categorie c";
		$sql .= " JOIN ".MAIN_DB_PREFIX.$table." ct ON ct.fk_categorie = c.rowid";
		$sql .= " WHERE ct.".$col." = ".((int) $fk_object)." AND c.entity IN (".getEntity('categorie', 1).")";
		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$tags[] = $obj->label;
			}
			$this->db->free($resql);
		}
		return $tags;
	}

	/**
	 * Construit le payload Mailchimp d'un enregistrement.
	 *
	 * @param array $record Voir getEligibleRecords()
	 * @return array Payload membre Mailchimp
	 */
	public function buildMemberPayload($record)
	{
		$payload = array(
			'email_address' => $record['email'],
			'status_if_new' => 'subscribed',
			'merge_fields' => array(),
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
		if (!empty($record['tags'])) {
			// Batch subscribe (POST /lists/{id}) attend des tags en chaines simples
			$payload['tags'] = array_values($record['tags']);
		}
		return $payload;
	}

	/**
	 * Analyse locale (dry-run) : compare l'éligibilité Dolibarr au mapping local
	 * et aux membres déjà présents dans l'audience Mailchimp (lecture seule).
	 *
	 * @param string $list_id Audience cible
	 * @param array  $filters Filtres getEligibleRecords()
	 * @return array{to_add:int,to_update:int,excluded_local:int,already_synced:int,members:array,remote_count:int}
	 */
	public function dryRun($list_id, $filters = array())
	{
		$eligible = $this->getEligibleRecords($filters);
		$local_map = $this->getLocalMap($list_id);

		// Membres déjà présents dans Mailchimp (lecture seule, pagination)
		$remote = array();
		$remote_count = 0;
		if ($this->client !== null) {
			try {
				$members = $this->client->getPaginated('/lists/'.rawurlencode($list_id).'/members', array('fields' => 'members.email_address,members.status', 'count' => 1000));
				$remote_count = count($members);
				foreach ($members as $m) {
					if (!empty($m['email_address'])) {
						$remote[strtolower($m['email_address'])] = $m['status'];
					}
				}
			} catch (MailchimpApiException $e) {
				// Dry-run sans lecture distante possible : on s'appuie sur le mapping local
			}
		}

		$to_add = 0;
		$to_update = 0;
		$already = 0;
		$members = array();
		foreach ($eligible as $rec) {
			$key = strtolower($rec['email']);
			$in_local = isset($local_map[$key]);
			$in_remote = isset($remote[$key]);
			$action = ($in_local || $in_remote) ? 'update' : 'add';
			if ($in_local) {
				$already++;
			}
			if ($action === 'add') {
				$to_add++;
			} else {
				$to_update++;
			}
			$members[] = array(
				'email' => $rec['email'],
				'name' => trim($rec['first_name'].' '.$rec['last_name'].' '.$rec['company']),
				'type' => $rec['object_type'],
				'tags' => implode(', ', $rec['tags']),
				'action' => $action,
				'remote_status' => $in_remote ? $remote[$key] : '',
			);
		}

		return array(
			'to_add' => $to_add,
			'to_update' => $to_update,
			'already_synced' => $already,
			'excluded_local' => 0, // les exclus ne sont pas retournés par getEligibleRecords
			'members' => $members,
			'remote_count' => $remote_count,
		);
	}

	/**
	 * Mapping local email -> info pour une audience.
	 *
	 * @param string $list_id
	 * @return array email => array(rowid, mc_status)
	 */
	private function getLocalMap($list_id)
	{
		$out = array();
		$sql = "SELECT rowid, email, mc_status FROM ".MAIN_DB_PREFIX."mailchimp_member_map";
		$sql .= " WHERE list_id = '".$this->db->escape($list_id)."' AND entity = ".getEntity('mailchimp');
		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$out[strtolower($obj->email)] = array('rowid' => (int) $obj->rowid, 'mc_status' => $obj->mc_status);
			}
			$this->db->free($resql);
		}
		return $out;
	}

	/**
	 * Synchronise les enregistrements éligibles vers une audience (lots de 500).
	 * Règle opt-out stricte : un membre remote 'unsubscribed'/'cleaned' n'est jamais réabonné.
	 *
	 * @param string $list_id Audience cible
	 * @param array  $filters Filtres getEligibleRecords()
	 * @return array{ok:int,updated:int,errors:array,batches:int} ou array('error'=>message)
	 */
	public function sync($list_id, $filters = array())
	{
		if ($this->client === null) {
			return array('error' => 'no-client');
		}

		$eligible = $this->getEligibleRecords($filters);
		if (empty($eligible)) {
			return array('ok' => 0, 'updated' => 0, 'errors' => array(), 'batches' => 0);
		}

		// Statuts distants pour respecter les opt-out (lecture des emails+statuts)
		$remote_status = array();
		try {
			$members = $this->client->getPaginated('/lists/'.rawurlencode($list_id).'/members', array('fields' => 'members.email_address,members.status', 'count' => 1000));
			foreach ($members as $m) {
				if (!empty($m['email_address'])) {
					$remote_status[strtolower($m['email_address'])] = $m['status'];
				}
			}
		} catch (MailchimpApiException $e) {
			// Sans lecture distante, on ne peut pas vérifier les opt-out : on refuse par sécurité
			return array('error' => 'remote-fetch-failed: '.$e->getMessage().' '.$e->detail);
		}

		$ops_members = array();
		foreach ($eligible as $rec) {
			$key = strtolower($rec['email']);
			// Opt-out strict : jamais de réabonnement d'un désabonné ou nettoyé
			if (isset($remote_status[$key]) && in_array($remote_status[$key], array('unsubscribed', 'cleaned'))) {
				$this->markLocalOptOut($rec, $list_id, $remote_status[$key]);
				continue;
			}
			$payload = $this->buildMemberPayload($rec);
			if (isset($remote_status[$key])) {
				$payload['status'] = 'subscribed'; // membre existant actif : maj des champs
				unset($payload['status_if_new']);
			}
			$ops_members[] = $payload;
		}

		$errors = array();
		$ok = 0;
		$updated = 0;
		$batches = 0;

		foreach (array_chunk($ops_members, self::BATCH_SIZE) as $chunk) {
			try {
				$result = $this->client->post('/lists/'.rawurlencode($list_id), array(
					'members' => $chunk,
					'update_existing' => true,
				));
				$batches++;
				$ok += (int) ($result['total_created'] ?? 0);
				$updated += (int) ($result['total_updated'] ?? 0);
				// Mapping local uniquement pour les membres effectivement crees/maj
				foreach (array_merge($result['new_members'] ?? array(), $result['updated_members'] ?? array()) as $m) {
					if (!empty($m['email_address'])) {
						$this->upsertLocalMap(array('email_address' => $m['email_address']), $list_id);
					}
				}
				if (!empty($result['errors'])) {
					foreach ($result['errors'] as $err) {
						$errors[] = ($err['email_address'] ?? '?').': '.($err['error'] ?? 'unknown');
					}
				}
			} catch (MailchimpApiException $e) {
				$errors[] = 'batch: ['.$e->status.'] '.$e->getMessage().' - '.$e->detail;
			}
		}

		$this->logSync('batch', $list_id, array(
			'eligible' => count($eligible), 'sent' => count($ops_members),
			'created' => $ok, 'updated' => $updated, 'errors' => count($errors),
		), empty($errors) ? 'ok' : 'error');

		return array('ok' => $ok, 'updated' => $updated, 'errors' => $errors, 'batches' => $batches);
	}

	/**
	 * Insère ou met à jour le mapping local après un envoi réussi.
	 *
	 * @param array  $payload Payload membre envoyé
	 * @param string $list_id
	 * @return void
	 */
	private function upsertLocalMap($payload, $list_id)
	{
		$email = $payload['email_address'];
		$hash = MailchimpClient::subscriberHash($email);
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."mailchimp_member_map";
		$sql .= " WHERE list_id = '".$this->db->escape($list_id)."' AND email = '".$this->db->escape($email)."' AND entity = ".getEntity('mailchimp');
		$resql = $this->db->query($sql);
		$rowid = 0;
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj) {
				$rowid = (int) $obj->rowid;
			}
			$this->db->free($resql);
		}
		if ($rowid > 0) {
			$sql = "UPDATE ".MAIN_DB_PREFIX."mailchimp_member_map SET subscriber_hash = '".$hash."', mc_status = 'subscribed', last_sync = '".$this->db->idate(dol_now())."' WHERE rowid = ".((int) $rowid);
			$this->db->query($sql);
		} else {
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."mailchimp_member_map (entity, email, list_id, subscriber_hash, mc_status, last_sync, date_creation)";
			$sql .= " VALUES (".getEntity('mailchimp').", '".$this->db->escape($email)."', '".$this->db->escape($list_id)."', '".$hash."', 'subscribed', '".$this->db->idate(dol_now())."', '".$this->db->idate(dol_now())."')";
			$this->db->query($sql);
		}
	}

	/**
	 * Marque localement un contact désabonné/nettoyé côté Mailchimp.
	 *
	 * @param array  $rec    Enregistrement éligible
	 * @param string $list_id
	 * @param string $status unsubscribed|cleaned
	 * @return void
	 */
	private function markLocalOptOut($rec, $list_id, $status)
	{
		$email = $rec['email'];
		$sql = "UPDATE ".MAIN_DB_PREFIX."mailchimp_member_map";
		$sql .= " SET mc_status = '".$this->db->escape($status)."', opt_out = 1, last_sync = '".$this->db->idate(dol_now())."'";
		$sql .= " WHERE list_id = '".$this->db->escape($list_id)."' AND email = '".$this->db->escape($email)."' AND entity = ".getEntity('mailchimp');
		$this->db->query($sql);
	}

	/**
	 * Ajoute un événement dans la file d'attente (llx_mailchimp_sync_log).
	 * Appelé par les triggers COMPANY_x / CONTACT_x.
	 *
	 * @param string $object_type company|contact
	 * @param int    $fk_object
	 * @param string $event       CREATE|MODIFY|DELETE
	 * @return int 1 si OK, -1 si erreur
	 */
	public function queueEvent($object_type, $fk_object, $event)
	{
		// Éviter les doublons en attente pour le même objet
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."mailchimp_sync_log";
		$sql .= " WHERE log_type = 'queue' AND status = 'pending' AND object_type = '".$this->db->escape($object_type)."' AND fk_object = ".((int) $fk_object)." AND entity = ".getEntity('mailchimp');
		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) > 0) {
			$this->db->free($resql);
			return 1;
		}

		$payload = json_encode(array('event' => $event, 'object_type' => $object_type, 'fk_object' => (int) $fk_object));
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."mailchimp_sync_log";
		$sql .= " (entity, log_type, object_type, fk_object, payload, status, date_creation)";
		$sql .= " VALUES (".getEntity('mailchimp').", 'queue', '".$this->db->escape($object_type)."', ".((int) $fk_object);
		$sql .= ", '".$this->db->escape($payload)."', 'pending', '".$this->db->idate(dol_now())."')";
		$resql = $this->db->query($sql);
		return $resql ? 1 : -1;
	}

	/**
	 * Traite la file d'attente des événements (triggers) : pousse chaque objet
	 * modifié vers l'audience par défaut (un appel membre par objet, idempotent).
	 *
	 * @return int Nombre d'événements traités
	 */
	public function processQueue()
	{
		if ($this->client === null || empty($this->config['default_list_id'])) {
			return 0;
		}
		$list_id = $this->config['default_list_id'];
		$entity = getEntity('mailchimp');
		$processed = 0;

		$sql = "SELECT rowid, object_type, fk_object, payload FROM ".MAIN_DB_PREFIX."mailchimp_sync_log";
		$sql .= " WHERE log_type = 'queue' AND status = 'pending' AND entity = ".$entity." ORDER BY rowid ASC LIMIT 500";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return 0;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$evt = json_decode($obj->payload, true);
			$event = is_array($evt) ? $evt['event'] : 'MODIFY';

			if ($event === 'DELETE') {
				// Suppression : on marque le mapping local opt-out (on ne supprime pas l'audience Mailchimp)
				$col = $obj->object_type === 'contact' ? 'fk_socpeople' : 'fk_soc';
				$this->db->query("UPDATE ".MAIN_DB_PREFIX."mailchimp_member_map SET opt_out = 1 WHERE ".$col." = ".((int) $obj->fk_object)." AND entity = ".$entity);
				$this->setQueueStatus((int) $obj->rowid, 'ok');
				$processed++;
				continue;
			}

			$rec = $this->fetchRecord($obj->object_type, (int) $obj->fk_object);
			if ($rec === null) {
				// Objet supprimé ou non éligible : on clôture la file
				$this->setQueueStatus((int) $obj->rowid, 'ok');
				$processed++;
				continue;
			}

			// Respect des opt-out distants
			$hash = MailchimpClient::subscriberHash($rec['email']);
			try {
				$remote = $this->client->get('/lists/'.rawurlencode($list_id).'/members/'.$hash, array('fields' => 'status'));
				if (isset($remote['status']) && in_array($remote['status'], array('unsubscribed', 'cleaned'))) {
					$this->markLocalOptOut($rec, $list_id, $remote['status']);
					$this->setQueueStatus((int) $obj->rowid, 'ok');
					$processed++;
					continue;
				}
			} catch (MailchimpApiException $e) {
				if ($e->status !== 404) {
					$this->setQueueStatus((int) $obj->rowid, 'error', '['.$e->status.'] '.$e->getMessage());
					continue; // sera retenté
				}
				// 404 = pas encore membre : l'ajout ci-dessous le crée
			}

			$payload = $this->buildMemberPayload($rec);
			try {
				$this->client->post('/lists/'.rawurlencode($list_id).'/members', $payload);
				$this->upsertLocalMapFromRecord($rec, $list_id);
				$this->setQueueStatus((int) $obj->rowid, 'ok');
			} catch (MailchimpApiException $e) {
				$this->setQueueStatus((int) $obj->rowid, 'error', '['.$e->status.'] '.$e->getMessage().' '.$e->detail);
			}
			$processed++;
		}
		$this->db->free($resql);

		return $processed;
	}

	/**
	 * Charge un objet Dolibarr au format enregistrement (ou null si non éligible).
	 *
	 * @param string $object_type company|contact
	 * @param int    $fk_object
	 * @return array|null
	 */
	private function fetchRecord($object_type, $fk_object)
	{
		$records = $this->getEligibleRecords(array('sources' => array($object_type)));
		foreach ($records as $rec) {
			if ($rec['fk_object'] === $fk_object) {
				return $rec;
			}
		}
		return null;
	}	/**
	 * Mapping local à partir d'un enregistrement (utilisé par processQueue).
	 *
	 * @param array  $rec
	 * @param string $list_id
	 * @return void
	 */
	private function upsertLocalMapFromRecord($rec, $list_id)
	{
		$this->upsertLocalMap(array('email_address' => $rec['email']), $list_id);
	}

	/**
	 * Change le statut d'une ligne de file d'attente.
	 *
	 * @param int    $rowid
	 * @param string $status ok|error
	 * @param string $error
	 * @return void
	 */
	private function setQueueStatus($rowid, $status, $error = '')
	{
		$sql = "UPDATE ".MAIN_DB_PREFIX."mailchimp_sync_log SET status = '".$this->db->escape($status)."', error_msg = '".$this->db->escape($error)."' WHERE rowid = ".((int) $rowid);
		$this->db->query($sql);
	}

	/**
	 * Journalise une synchronisation.
	 *
	 * @param string $type    batch|cron
	 * @param string $list_id
	 * @param array  $data
	 * @param string $status  ok|error
	 * @return void
	 */
	private function logSync($type, $list_id, $data, $status)
	{
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."mailchimp_sync_log";
		$sql .= " (entity, log_type, object_type, payload, status, date_creation)";
		$sql .= " VALUES (".getEntity('mailchimp').", '".$this->db->escape($type)."', 'company', '";
		$sql .= $this->db->escape(json_encode(array('list_id' => $list_id, 'data' => $data)));
		$sql .= "', '".$this->db->escape($status)."', '".$this->db->idate(dol_now())."')";
		$this->db->query($sql);
	}

	/**
	 * Tâche cron : synchronisation incrémentale (toutes les 15 min).
	 * Traite d'abord la file des triggers, puis les objets modifiés depuis la dernière passe.
	 *
	 * @return int 0 si OK, code > 0 si erreur (convention cron Dolibarr)
	 */
	public function runCronIncremental()
	{
		if ($this->client === null) {
			return 0; // Non configuré : rien à faire
		}
		$this->processQueue();

		$list_id = $this->config['default_list_id'] ?? '';
		if ($list_id === '') {
			return 0;
		}

		$last = (int) getDolGlobalInt('MAILCHIMP_LASTINC_SYNC', 0);
		$now = dol_now();
		if ($last > 0) {
			$result = $this->sync($list_id, array('only_modified_since' => $last - 60)); // marge 1 min
			if (isset($result['error'])) {
				dol_syslog('Mailchimp cron incremental error: '.$result['error'], LOG_ERR);
				return 1;
			}
		}
		dolibarr_set_const($this->db, 'MAILCHIMP_LASTINC_SYNC', (string) $now, 'chaine', 0, '', 0);

		return 0;
	}

	/**
	 * Traite un événement webhook Mailchimp (unsubscribe, cleaned, profile, subscribe).
	 * Appelé par public/mailchimp/webhook.php.
	 *
	 * @param string $type    Type d'evenement
	 * @param array  $data    data[list_id], data[email], data[merges]...
	 * @return string         'ok' | 'ignored'
	 */
	public function processWebhookEvent($type, $data)
	{
		$list_id = $data['list_id'] ?? '';
		$email = strtolower(trim($data['email'] ?? ''));
		if ($email === '') {
			return 'ignored';
		}

		if ($type === 'unsubscribe' || $type === 'cleaned') {
			$status = $type === 'cleaned' ? 'cleaned' : 'unsubscribed';
			// Mapping local
			$this->markLocalOptOut(array('email' => $email), $list_id, $status);
			// Dolibarr : no_email sur les contacts + opt-out global emailing (tiers)
			$this->db->query("UPDATE ".MAIN_DB_PREFIX."socpeople SET no_email = 1 WHERE email = '".$this->db->escape($email)."'");
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."mailing_unsubscribe (entity, email, date_creat)";
			$sql .= " SELECT ".getEntity('mailing').", '".$this->db->escape($email)."', '".$this->db->idate(dol_now())."' FROM DUAL";
			$sql .= " WHERE NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."mailing_unsubscribe WHERE email = '".$this->db->escape($email)."' AND entity = ".getEntity('mailing').")";
			$this->db->query($sql);
			return 'ok';
		}

		if ($type === 'profile') {
			// Mise a jour des noms si fournis dans merges
			$merges = $data['merges'] ?? array();
			$firstname = trim($merges['FNAME'] ?? '');
			$lastname = trim($merges['LNAME'] ?? '');
			if ($firstname !== '' || $lastname !== '') {
				$set = array();
				if ($firstname !== '') {
					$set[] = "firstname = '".$this->db->escape($firstname)."'";
				}
				if ($lastname !== '') {
					$set[] = "lastname = '".$this->db->escape($lastname)."'";
				}
				$this->db->query("UPDATE ".MAIN_DB_PREFIX."socpeople SET ".implode(', ', $set)." WHERE email = '".$this->db->escape($email)."'");
			}
			return 'ok';
		}

		// subscribe : remettre l'eligibilite locale (le contact peut a nouveau recevoir des envois)
		if ($type === 'subscribe') {
			$sql = "UPDATE ".MAIN_DB_PREFIX."mailchimp_member_map SET opt_out = 0, mc_status = 'subscribed'";
			$sql .= " WHERE email = '".$this->db->escape($email)."' AND list_id = '".$this->db->escape($list_id)."' AND entity = ".getEntity('mailchimp');
			$this->db->query($sql);
			return 'ok';
		}

		return 'ignored';
	}

	/**
	 * Tâche cron : réconciliation des désabonnements (toutes les 24 h, fallback webhook).
	 * Parcourt les membres unsubscribed/cleaned et met à jour no_email + mapping local.
	 *
	 * @return int 0 si OK
	 */
	public function runCronOptoutReconciliation()
	{
		if ($this->client === null) {
			return 0;
		}
		$list_id = $this->config['default_list_id'] ?? '';
		if ($list_id === '') {
			return 0;
		}
		try {
			$members = $this->client->getPaginated('/lists/'.rawurlencode($list_id).'/members', array('status' => 'unsubscribed,cleaned', 'fields' => 'members.email_address,members.status', 'count' => 1000));
		} catch (MailchimpApiException $e) {
			dol_syslog('Mailchimp optout reconciliation error: '.$e->getMessage(), LOG_ERR);
			return 1;
		}
		foreach ($members as $m) {
			if (empty($m['email_address'])) {
				continue;
			}
			$email = $m['email_address'];
			// Mapping local
			$this->markLocalOptOut(array('email' => $email), $list_id, $m['status'] ?? 'unsubscribed');
			// Dolibarr : no_email sur les contacts + inscription dans llx_mailing_unsubscribe
			// (llx_societe n'a pas de no_email ; la table unsubscribe couvre les deux)
			$this->db->query("UPDATE ".MAIN_DB_PREFIX."socpeople SET no_email = 1 WHERE email = '".$this->db->escape($email)."'");
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."mailing_unsubscribe (entity, email, date_creat)";
			$sql .= " SELECT 1, '".$this->db->escape($email)."', '".$this->db->idate(dol_now())."' FROM DUAL";
			$sql .= " WHERE NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."mailing_unsubscribe WHERE email = '".$this->db->escape($email)."' AND entity = 1)";
			$this->db->query($sql);
		}
		return 0;
	}
}
