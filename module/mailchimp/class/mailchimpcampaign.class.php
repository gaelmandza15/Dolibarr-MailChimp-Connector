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
 * \file    class/mailchimpcampaign.class.php
 * \ingroup mailchimp
 * \brief   Gestion des campagnes Mailchimp : creation, contenu, envoi, planification, statistiques.
 */

require_once dol_buildpath('/mailchimp/class/mailchimpclient.class.php', 0);
require_once dol_buildpath('/mailchimp/lib/mailchimp.lib.php', 0);

/**
 * Classe de gestion des campagnes Mailchimp.
 */
class MailchimpCampaign
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
	 * Client API (null si non configure).
	 * @return MailchimpClient|null
	 */
	public function getClient()
	{
		return $this->client;
	}

	/**
	 * Liste les campagnes Mailchimp.
	 *
	 * @param int $count Nombre max retourne
	 * @return array|false Liste des campagnes, false si non configure
	 */
	public function listCampaigns($count = 50)
	{
		if ($this->client === null) {
			return false;
		}
		$resp = $this->client->get('/campaigns', array(
			'count' => $count,
			'fields' => 'campaigns.id,campaigns.status,campaigns.type,campaigns.settings.subject_line,campaigns.settings.from_name,campaigns.send_time,campaigns.recipients.list_id,campaigns.recipients.list_name,campaigns.longest_subject_line',
		));
		return $resp['campaigns'] ?? array();
	}

	/**
	 * Cree une campagne Mailchimp (brouillon) et enregistre la correspondance.
	 *
	 * @param string $list_id    Audience ciblee
	 * @param string $subject    Objet de la campagne
	 * @param string $from_name  Nom d'expediteur
	 * @param string $reply_to   Email de reponse
	 * @param int    $fk_mailing Lien optionnel vers un emailing Dolibarr
	 * @return string|false      campaign_id Mailchimp, false si non configure
	 * @throws MailchimpApiException
	 */
	public function create($list_id, $subject, $from_name, $reply_to, $fk_mailing = 0)
	{
		if ($this->client === null) {
			return false;
		}
		$result = $this->client->post('/campaigns', array(
			'type' => 'regular',
			'recipients' => array('list_id' => $list_id),
			'settings' => array(
				'subject_line' => $subject,
				'from_name' => $from_name,
				'reply_to' => $reply_to,
			),
		));
		if (!empty($result['id'])) {
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."mailchimp_campaign_map";
			$sql .= " (entity, fk_mailing, campaign_id, list_id, status, subject, date_creation)";
			$sql .= " VALUES (".getEntity('mailchimp').", ".((int) $fk_mailing).", '".$this->db->escape($result['id'])."'";
			$sql .= ", '".$this->db->escape($list_id)."', '".$this->db->escape($result['status'] ?? '')."'";
			$sql .= ", '".$this->db->escape($subject)."', '".$this->db->idate(dol_now())."')";
			$this->db->query($sql);
			return $result['id'];
		}
		return false;
	}

	/**
	 * Definit le contenu HTML/texte d'une campagne.
	 *
	 * @param string $campaign_id
	 * @param string $html
	 * @param string $plain_text Optionnel (derive du HTML si vide)
	 * @return array|false Reponse API, false si non configure
	 * @throws MailchimpApiException
	 */
	public function setContent($campaign_id, $html, $plain_text = '')
	{
		if ($this->client === null) {
			return false;
		}
		$data = array('html' => $html);
		if ($plain_text !== '') {
			$data['plain_text'] = $plain_text;
		}
		return $this->client->put('/campaigns/'.rawurlencode($campaign_id).'/content', $data);
	}

	/**
	 * Envoie une campagne.
	 *
	 * @param string $campaign_id
	 * @return array|false
	 * @throws MailchimpApiException
	 */
	public function send($campaign_id)
	{
		if ($this->client === null) {
			return false;
		}
		$result = $this->client->post('/campaigns/'.rawurlencode($campaign_id).'/actions/send', array());
		$this->updateMapStatus($campaign_id, 'sending');
		return $result;
	}

	/**
	 * Envoie un email de test.
	 *
	 * @param string $campaign_id
	 * @param array  $test_emails Adresses de test
	 * @return array|false
	 * @throws MailchimpApiException
	 */
	public function sendTest($campaign_id, $test_emails)
	{
		if ($this->client === null) {
			return false;
		}
		return $this->client->post('/campaigns/'.rawurlencode($campaign_id).'/actions/test', array(
			'test_emails' => $test_emails,
			'send_type' => 'html',
		));
	}

	/**
	 * Planifie une campagne.
	 *
	 * @param string $campaign_id
	 * @param string $schedule_time ISO 8601 UTC (ex: 2026-10-05T09:00:00+00:00)
	 * @return array|false
	 * @throws MailchimpApiException
	 */
	public function schedule($campaign_id, $schedule_time)
	{
		if ($this->client === null) {
			return false;
		}
		$result = $this->client->post('/campaigns/'.rawurlencode($campaign_id).'/actions/schedule', array(
			'schedule_time' => $schedule_time,
			'timewarp' => false,
		));
		$this->updateMapStatus($campaign_id, 'schedule');
		return $result;
	}

	/**
	 * Supprime une campagne (brouillon uniquement).
	 *
	 * @param string $campaign_id
	 * @return array|false
	 * @throws MailchimpApiException
	 */
	public function delete($campaign_id)
	{
		if ($this->client === null) {
			return false;
		}
		$result = $this->client->delete('/campaigns/'.rawurlencode($campaign_id));
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."mailchimp_campaign_map WHERE campaign_id = '".$this->db->escape($campaign_id)."'";
		$this->db->query($sql);
		return $result;
	}

	/**
	 * Met a jour le statut dans le mapping local.
	 *
	 * @param string $campaign_id
	 * @param string $status
	 * @return void
	 */
	private function updateMapStatus($campaign_id, $status)
	{
		$sql = "UPDATE ".MAIN_DB_PREFIX."mailchimp_campaign_map SET status = '".$this->db->escape($status)."' WHERE campaign_id = '".$this->db->escape($campaign_id)."'";
		$this->db->query($sql);
	}

	/**
	 * Liste les rapports de campagnes envoyées (GET /reports, paginé).
	 *
	 * @param int $count Nombre max par page
	 * @return array|false Rapports, false si non configure
	 */
	public function listReports($count = 50)
	{
		if ($this->client === null) {
			return false;
		}
		return $this->client->getPaginated('/reports', array(
			'fields' => 'reports.id,reports.campaign_title,reports.send_time,reports.emails_sent,reports.opens,reports.clicks,reports.unsubscribed,reports.bounces',
			'count' => $count,
		));
	}

	/**
	 * Assure l'existence d'une ligne campaign_map pour une campagne Mailchimp
	 * (utile pour les campagnes créées hors du module).
	 *
	 * @param string $campaign_id
	 * @param string $list_id
	 * @param string $status
	 * @param string $subject
	 * @return int rowid du mapping
	 */
	public function ensureCampaignMap($campaign_id, $list_id = '', $status = 'sent', $subject = '')
	{
		$entity = getEntity('mailchimp');
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."mailchimp_campaign_map WHERE campaign_id = '".$this->db->escape($campaign_id)."' AND entity = ".$entity;
		$res = $this->db->query($sql);
		if ($res) {
			$obj = $this->db->fetch_object($res);
			$this->db->free($res);
			if ($obj) {
				return (int) $obj->rowid;
			}
		}
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."mailchimp_campaign_map (entity, fk_mailing, campaign_id, list_id, status, subject, date_creation)";
		$sql .= " VALUES (".$entity.", 0, '".$this->db->escape($campaign_id)."', '".$this->db->escape($list_id)."', '".$this->db->escape($status)."', '".$this->db->escape($subject)."', '".$this->db->idate(dol_now())."')";
		$res2 = $this->db->query($sql);
		return $res2 ? (int) $this->db->last_insert_id($res2) : 0;
	}

	/**
	 * Rappelle les statistiques de toutes les campagnes envoyées (GET /reports)
	 * et les enregistre dans llx_mailchimp_campaign_stats.
	 *
	 * @return int Nombre de rapports synchronisés
	 */
	public function refreshStats()
	{
		$reports = $this->listReports(1000);
		if ($reports === false) {
			return 0;
		}
		$entity = getEntity('mailchimp');
		$n = 0;
		foreach ($reports as $r) {
			if (empty($r['id'])) {
				continue;
			}
			$rowid = $this->ensureCampaignMap($r['id'], '', 'sent', $r['campaign_title'] ?? '');
			$this->saveStats($entity, $rowid, $r);
			$n++;
		}
		return $n;
	}

	/**
	 * Tâche cron : rappel des statistiques des campagnes envoyées (toutes les heures).
	 *
	 * @return int 0 si OK
	 */
	public function runCronStats()
	{
		if ($this->client === null) {
			return 0;
		}
		$this->refreshStats();
		return 0;
	}

	/**
	 * Enregistre les statistiques d'une campagne.
	 *
	 * @param int   $entity
	 * @param int   $fk_campaign_map
	 * @param array $report Rapport Mailchimp
	 * @return void
	 */
	private function saveStats($entity, $fk_campaign_map, $report)
	{
		$opens = $report['opens'] ?? array();
		$clicks = $report['clicks'] ?? array();
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."mailchimp_campaign_stats";
		$sql .= " (entity, fk_campaign_map, emails_sent, opens, unique_opens, open_rate, clicks, unique_subscriber_clicks, click_rate, unsubscribes, bounces, last_sync)";
		$sql .= " VALUES (".((int) $entity).", ".((int) $fk_campaign_map).", ".((int) ($report['emails_sent'] ?? 0));
		$sql .= ", ".((int) ($opens['opens_total'] ?? 0)).", ".((int) ($opens['unique_opens'] ?? 0)).", ".(float) ($opens['open_rate'] ?? 0);
		$sql .= ", ".((int) ($clicks['clicks_total'] ?? 0)).", ".((int) ($clicks['unique_subscriber_clicks'] ?? 0)).", ".(float) ($clicks['click_rate'] ?? 0);
		$sql .= ", ".((int) ($report['unsubscribed'] ?? 0)).", ".((int) ($report['bounces'] ?? 0)).", '".$this->db->idate(dol_now())."')";
		$sql .= " ON DUPLICATE KEY UPDATE emails_sent = VALUES(emails_sent), opens = VALUES(opens), unique_opens = VALUES(unique_opens)";
		$sql .= ", open_rate = VALUES(open_rate), clicks = VALUES(clicks), unique_subscriber_clicks = VALUES(unique_subscriber_clicks)";
		$sql .= ", click_rate = VALUES(click_rate), unsubscribes = VALUES(unsubscribes), bounces = VALUES(bounces), last_sync = VALUES(last_sync)";
		$this->db->query($sql);
	}
}
