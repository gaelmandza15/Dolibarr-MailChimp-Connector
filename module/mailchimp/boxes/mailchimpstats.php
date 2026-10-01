<?php
/* Copyright (C) 2026  Gael <gael@example.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    boxes/mailchimpstats.php
 * \ingroup mailchimp
 * \brief   Widget tableau de bord : statistiques des dernières campagnes Mailchimp.
 */

include_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';

/**
 * Box des statistiques Mailchimp.
 */
class mailchimpstats extends ModeleBoxes
{
	/** @var string */
	public $boxcode = 'mailchimpstats';

	/** @var string */
	public $boximg = 'email';

	/** @var string */
	public $boxlabel = 'MailchimpCampaignStats';

	/** @var int */
	public $depends = array('mailchimp');

	/**
	 * Charge les données et remplit $this->info_box_contents.
	 *
	 * @param int $max Nombre max d'entrées
	 * @return void
	 */
	public function loadBox($max = 5)
	{
		global $db, $langs, $user;

		$this->max = $max;
		$langs->load('mailchimp@mailchimp');

		$this->info_box_head = array(
			'text' => $langs->trans("MailchimpCampaignStats"),
			'sublink' => dol_buildpath('/mailchimp/reports_list.php', 1),
			'subtext' => $langs->trans("MailchimpReports"),
		);

		if (!$user->rights->mailchimp->read) {
			$this->info_box_contents = array();
			return;
		}

		// Phase 5 : jointure llx_mailchimp_campaign_map x llx_mailchimp_campaign_stats
		// pour afficher les dernieres campagnes avec taux d'ouverture/clics.
		$this->info_box_contents = array(array(0 => array(
			'text' => $langs->trans("MailchimpSoon"),
			'asis' => 1,
		)));
	}

	/**
	 * @param int $head Position
	 * @return void
	 */
	public function showBox($head = null, $contents = null, $nooutput = 0)
	{
		return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
	}
}
