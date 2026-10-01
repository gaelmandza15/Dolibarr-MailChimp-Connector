<?php
/* Copyright (C) 2026  Gael <gael@example.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    class/mailchimpcampaign.class.php
 * \ingroup mailchimp
 * \brief   Gestion des campagnes Mailchimp et de leurs statistiques (Phases 4 et 5).
 */

require_once dol_buildpath('/mailchimp/class/mailchimpclient.class.php', 0);
require_once dol_buildpath('/mailchimp/lib/mailchimp.lib.php', 0);

/**
 * Classe de gestion des campagnes Mailchimp.
 *
 * Phase 4 : creation (POST /campaigns), contenu (PUT /campaigns/{id}/content),
 * envoi/planification/test, lien llx_mailing <-> campaign_id dans llx_mailchimp_campaign_map.
 * Phase 5 : rappel des statistiques (GET /reports/{id}) dans llx_mailchimp_campaign_stats.
 */
class MailchimpCampaign
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
	 * Cree une campagne Mailchimp et enregistre la correspondance.
	 * Phase 4 : utilise par la page campaigns_new.php.
	 *
	 * @param string $list_id    Audience ciblee
	 * @param string $subject    Objet de la campagne
	 * @param string $from_name  Nom d'expediteur
	 * @param string $reply_to   Email de reponse
	 * @param int    $fk_mailing Lien optionnel vers un emailing Dolibarr
	 * @return string|false      campaign_id Mailchimp, false si non configure
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
	 * Tâche cron : rappel des statistiques des campagnes envoyées (toutes les heures).
	 * Phase 5 : pour chaque campagne sent de llx_mailchimp_campaign_map, GET /reports/{id}
	 * et maj de llx_mailchimp_campaign_stats.
	 *
	 * @return int 0 si OK
	 */
	public function runCronStats()
	{
		if ($this->client === null) {
			return 0;
		}
		// Phase 5
		return 0;
	}
}
