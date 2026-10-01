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
 * \file    class/mailchimpimport.class.php
 * \ingroup mailchimp
 * \brief   Import des membres Mailchimp vers Dolibarr (contacts et/ou tiers).
 */

require_once dol_buildpath('/mailchimp/class/mailchimpclient.class.php', 0);
require_once dol_buildpath('/mailchimp/lib/mailchimp.lib.php', 0);

/**
 * Import Mailchimp -> Dolibarr.
 *
 * Lecture paginee des membres d'une audience, dédoublonnage par email,
 * création de contacts (llx_socpeople) et/ou de tiers (llx_societe),
 * affectation d'une catégorie (tag Dolibarr), gestion des désabonnés.
 */
class MailchimpImport
{
	/** @var DoliDB */
	private $db;

	/** @var MailchimpClient|null */
	private $client;

	/**
	 * @param DoliDB $db
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$config = mailchimp_get_config($db);
		if (!empty($config['apikey'])) {
			try {
				$this->client = new MailchimpClient($config['apikey']);
			} catch (MailchimpApiException $e) {
				$this->client = null;
			}
		}
	}

	/**
	 * Lit les membres d'une audience (paginé, 1000/page).
	 *
	 * @param string $list_id
	 * @return array|false Liste de membres Mailchimp, false si non configuré
	 */
	public function fetchMembers($list_id)
	{
		if ($this->client === null) {
			return false;
		}
		return $this->client->getPaginated('/lists/'.rawurlencode($list_id).'/members', array(
			'fields' => 'members.email_address,members.status,members.merge_fields,members.tags',
			'count' => 1000,
		));
	}

	/**
	 * Analyse un import sans écrire dans la base (dry-run).
	 *
	 * @param string $list_id Audience
	 * @param array  $options Voir import()
	 * @return array|false
	 */
	public function dryRun($list_id, $options = array())
	{
		$members = $this->fetchMembers($list_id);
		if ($members === false) {
			return false;
		}

		$lines = array();
		$to_create_contact = 0;
		$to_create_company = 0;
		$to_update = 0;
		$skipped_unsub = 0;
		$already = 0;

		foreach ($members as $m) {
			$email = strtolower(trim($m['email_address'] ?? ''));
			if ($email === '') {
				continue;
			}
			$status = $m['status'] ?? '';
			$unsub = in_array($status, array('unsubscribed', 'cleaned'));
			if ($unsub && $options['unsubscribed'] === 'skip') {
				$skipped_unsub++;
				continue;
			}

			$existing = $this->findExisting($email);
			$need_contact = false;
			$need_company = false;
			$would_update = false;

			if ($existing === null) {
				if (!empty($options['create_contact'])) {
					$need_contact = true;
					$to_create_contact++;
				}
				if (!empty($options['create_company'])) {
					$need_company = true;
					$to_create_company++;
				}
				if (!$need_contact && !$need_company) {
					continue;
				}
			} else {
				if (empty($options['update_existing'])) {
					$already++;
					continue;
				}
				$would_update = true;
				$to_update++;
			}

			$mf = $m['merge_fields'] ?? array();
			$lines[] = array(
				'email' => $email,
				'status' => $status,
				'no_email' => $unsub ? 1 : 0,
				'name' => trim(($mf['FNAME'] ?? '').' '.($mf['LNAME'] ?? '')),
				'company' => $mf['SOCIETE'] ?? '',
				'tags' => implode(', ', array_column($m['tags'] ?? array(), 'name')),
				'existing' => $existing === null ? '-' : ($existing['type'] === 'contact' ? 'contact#'.$existing['id'] : 'societe#'.$existing['id']),
				'action' => $would_update ? 'update' : 'create',
			);
		}

		return array(
			'remote_count' => count($members),
			'to_create_contact' => $to_create_contact,
			'to_create_company' => $to_create_company,
			'to_update' => $to_update,
			'already' => $already,
			'skipped_unsub' => $skipped_unsub,
			'lines' => $lines,
		);
	}

	/**
	 * Importe les membres Mailchimp dans Dolibarr.
	 *
	 * @param string $list_id Audience
	 * @param array  $options create_contact(bool), create_company(bool), update_existing(bool),
	 *                        unsubscribed('skip'|'import_flagged'), category_id(int), fk_user(int)
	 * @return array Statistiques + erreurs
	 */
	public function import($list_id, $options = array())
	{
		$members = $this->fetchMembers($list_id);
		if ($members === false) {
			return array('error' => 'no-client');
		}

		$entity = getEntity('mailchimp');
		$fk_user = (int) ($options['fk_user'] ?? 1);
		$category_id = (int) ($options['category_id'] ?? 0);
		$category_type = 0;
		if ($category_id > 0) {
			$res = $this->db->query("SELECT type FROM ".MAIN_DB_PREFIX."categorie WHERE rowid = ".$category_id);
			if ($res) {
				$obj = $this->db->fetch_object($res);
				$category_type = (int) ($obj->type ?? 0);
				$this->db->free($res);
			}
		}

		$created_contact = 0;
		$created_company = 0;
		$updated = 0;
		$skipped = 0;
		$errors = array();

		foreach ($members as $m) {
			$email = strtolower(trim($m['email_address'] ?? ''));
			if ($email === '') {
				continue;
			}
			$status = $m['status'] ?? '';
			$unsub = in_array($status, array('unsubscribed', 'cleaned'));
			if ($unsub && $options['unsubscribed'] === 'skip') {
				$skipped++;
				continue;
			}
			$no_email = $unsub ? 1 : 0;

			$mf = $m['merge_fields'] ?? array();
			$firstname = trim($mf['FNAME'] ?? '');
			$lastname = trim($mf['LNAME'] ?? '');
			$company_name = trim($mf['SOCIETE'] ?? '');
			$tags = array_column($m['tags'] ?? array(), 'name');

			$existing = $this->findExisting($email);

			if ($existing === null && empty($options['create_contact']) && empty($options['create_company'])) {
				$skipped++;
				continue;
			}

			try {
				if ($existing === null) {
					$socid = 0;
					if (!empty($options['create_company'])) {
						$nom = $company_name !== '' ? $company_name : $email;
						$socid = $this->createCompany($nom, $email, $no_email, $fk_user);
						$created_company++;
						if ($socid > 0 && $category_id > 0 && $category_type == 2) {
							$this->linkCategory($category_id, 'company', $socid);
						}
					}
					if (!empty($options['create_contact'])) {
						$lastname_db = $lastname !== '' ? $lastname : $email;
						$ctid = $this->createContact($firstname, $lastname_db, $email, $no_email, $socid, $fk_user);
						$created_contact++;
						if ($ctid > 0 && $category_id > 0 && $category_type == 4) {
							$this->linkCategory($category_id, 'contact', $ctid);
						}
					}
					// Mapping local
					$this->upsertMap($email, $list_id, $status, $socid, ($socid > 0 ? 0 : ($ctid ?? 0)));
				} elseif (!empty($options['update_existing'])) {
					if ($existing['type'] === 'contact') {
						$sql = "UPDATE ".MAIN_DB_PREFIX."socpeople SET no_email = ".((int) $no_email)." WHERE rowid = ".((int) $existing['id']);
						$this->db->query($sql);
					} else {
						// tiers : pas de no_email, opt-out via llx_mailing_unsubscribe
						if ($no_email) {
							$this->insertUnsubscribe($email);
						}
					}
					$updated++;
					$this->upsertMap($email, $list_id, $status, ($existing['type'] === 'company' ? $existing['id'] : 0), ($existing['type'] === 'contact' ? $existing['id'] : 0));
				} else {
					$skipped++;
				}
			} catch (Exception $e) {
				$errors[] = $email.': '.$e->getMessage();
			}
		}

		$this->logImport($list_id, array(
			'remote' => count($members), 'created_contact' => $created_contact,
			'created_company' => $created_company, 'updated' => $updated,
			'skipped' => $skipped, 'errors' => count($errors),
		), empty($errors) ? 'ok' : 'error');

		return array(
			'created_contact' => $created_contact,
			'created_company' => $created_company,
			'updated' => $updated,
			'skipped' => $skipped,
			'errors' => $errors,
		);
	}

	/**
	 * Cherche un email dans les contacts puis les tiers.
	 *
	 * @param string $email
	 * @return array|null array('type'=>'contact'|'company', 'id'=>int)
	 */
	private function findExisting($email)
	{
		$res = $this->db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."socpeople WHERE email = '".$this->db->escape($email)."' ORDER BY rowid ASC LIMIT 1");
		if ($res) {
			$obj = $this->db->fetch_object($res);
			$this->db->free($res);
			if ($obj) {
				return array('type' => 'contact', 'id' => (int) $obj->rowid);
			}
		}
		$res = $this->db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE email = '".$this->db->escape($email)."' ORDER BY rowid ASC LIMIT 1");
		if ($res) {
			$obj = $this->db->fetch_object($res);
			$this->db->free($res);
			if ($obj) {
				return array('type' => 'company', 'id' => (int) $obj->rowid);
			}
		}
		return null;
	}

	/**
	 * Crée un tiers minimal.
	 *
	 * @param string $nom
	 * @param string $email
	 * @param int    $no_email
	 * @param int    $fk_user
	 * @return int rowid
	 */
	private function createCompany($nom, $email, $no_email, $fk_user)
	{
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."societe (entity, nom, email, client, fk_stcomm, datec, fk_user_creat)";
		$sql .= " VALUES (".getEntity('societe').", '".$this->db->escape($nom)."', '".$this->db->escape($email)."', 0, 0, '".$this->db->idate(dol_now())."', ".((int) $fk_user).")";
		$res = $this->db->query($sql);
		if ($no_email) {
			$this->insertUnsubscribe($email);
		}
		return $res ? (int) $this->db->last_insert_id($res) : 0;
	}

	/**
	 * Crée un contact minimal.
	 *
	 * @param string $firstname
	 * @param string $lastname
	 * @param string $email
	 * @param int    $no_email
	 * @param int    $fk_soc
	 * @param int    $fk_user
	 * @return int rowid
	 */
	private function createContact($firstname, $lastname, $email, $no_email, $fk_soc, $fk_user)
	{
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."socpeople (entity, firstname, lastname, email, no_email, fk_soc, statut, datec, fk_user_creat)";
		$sql .= " VALUES (".getEntity('socpeople').", '".$this->db->escape($firstname)."', '".$this->db->escape($lastname)."'";
		$sql .= ", '".$this->db->escape($email)."', ".((int) $no_email).", ".($fk_soc > 0 ? (int) $fk_soc : "NULL").", 1";
		$sql .= ", '".$this->db->idate(dol_now())."', ".((int) $fk_user).")";
		$res = $this->db->query($sql);
		return $res ? (int) $this->db->last_insert_id($res) : 0;
	}

	/**
	 * Lie une catégorie à un contact ou un tiers.
	 *
	 * @param int    $category_id
	 * @param string $type  contact|company
	 * @param int    $fk_object
	 * @return void
	 */
	private function linkCategory($category_id, $type, $fk_object)
	{
		$table = $type === 'contact' ? 'categorie_contact' : 'categorie_societe';
		$col = $type === 'contact' ? 'fk_socpeople' : 'fk_soc';
		$sql = "INSERT INTO ".MAIN_DB_PREFIX.$table." (fk_categorie, ".$col.")";
		$sql .= " SELECT ".((int) $category_id).", ".((int) $fk_object)." FROM DUAL";
		$sql .= " WHERE NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX.$table." WHERE fk_categorie = ".((int) $category_id)." AND ".$col." = ".((int) $fk_object).")";
		$this->db->query($sql);
	}

	/**
	 * Inscrit un email dans llx_mailing_unsubscribe (opt-out tiers).
	 *
	 * @param string $email
	 * @return void
	 */
	private function insertUnsubscribe($email)
	{
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."mailing_unsubscribe (entity, email, date_creat)";
		$sql .= " SELECT ".getEntity('mailing').", '".$this->db->escape($email)."', '".$this->db->idate(dol_now())."' FROM DUAL";
		$sql .= " WHERE NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."mailing_unsubscribe WHERE email = '".$this->db->escape($email)."' AND entity = ".getEntity('mailing').")";
		$this->db->query($sql);
	}

	/**
	 * Met à jour le mapping local après import.
	 *
	 * @param string $email
	 * @param string $list_id
	 * @param string $mc_status
	 * @param int    $fk_soc
	 * @param int    $fk_socpeople
	 * @return void
	 */
	private function upsertMap($email, $list_id, $mc_status, $fk_soc, $fk_socpeople)
	{
		$hash = MailchimpClient::subscriberHash($email);
		$res = $this->db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."mailchimp_member_map WHERE list_id = '".$this->db->escape($list_id)."' AND email = '".$this->db->escape($email)."' AND entity = ".getEntity('mailchimp'));
		$rowid = 0;
		if ($res) {
			$obj = $this->db->fetch_object($res);
			if ($obj) {
				$rowid = (int) $obj->rowid;
			}
			$this->db->free($res);
		}
		if ($rowid > 0) {
			$sql = "UPDATE ".MAIN_DB_PREFIX."mailchimp_member_map SET fk_soc = ".($fk_soc > 0 ? (int) $fk_soc : "NULL");
			$sql .= ", fk_socpeople = ".($fk_socpeople > 0 ? (int) $fk_socpeople : "NULL");
			$sql .= ", subscriber_hash = '".$hash."', mc_status = '".$this->db->escape($mc_status)."', last_sync = '".$this->db->idate(dol_now())."' WHERE rowid = ".((int) $rowid);
			$this->db->query($sql);
		} else {
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."mailchimp_member_map (entity, fk_soc, fk_socpeople, email, list_id, subscriber_hash, mc_status, last_sync, date_creation)";
			$sql .= " VALUES (".getEntity('mailchimp').", ".($fk_soc > 0 ? (int) $fk_soc : "NULL").", ".($fk_socpeople > 0 ? (int) $fk_socpeople : "NULL");
			$sql .= ", '".$this->db->escape($email)."', '".$this->db->escape($list_id)."', '".$hash."', '".$this->db->escape($mc_status)."', '".$this->db->idate(dol_now())."', '".$this->db->idate(dol_now())."')";
			$this->db->query($sql);
		}
	}

	/**
	 * Journalise un import.
	 *
	 * @param string $list_id
	 * @param array  $data
	 * @param string $status
	 * @return void
	 */
	private function logImport($list_id, $data, $status)
	{
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."mailchimp_sync_log";
		$sql .= " (entity, log_type, object_type, payload, status, date_creation)";
		$sql .= " VALUES (".getEntity('mailchimp').", 'import', 'contact', '";
		$sql .= $this->db->escape(json_encode(array('list_id' => $list_id, 'data' => $data)));
		$sql .= "', '".$this->db->escape($status)."', '".$this->db->idate(dol_now())."')";
		$this->db->query($sql);
	}
}
