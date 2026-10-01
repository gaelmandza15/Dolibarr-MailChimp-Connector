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
	 * Charge les données depuis llx_mailchimp_campaign_map x llx_mailchimp_campaign_stats.
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

		$contents = array();
		$sql = "SELECT cm.subject, cm.status, cs.emails_sent, cs.unique_opens, cs.open_rate, cs.unique_subscriber_clicks, cs.click_rate";
		$sql .= " FROM ".MAIN_DB_PREFIX."mailchimp_campaign_map cm";
		$sql .= " JOIN ".MAIN_DB_PREFIX."mailchimp_campaign_stats cs ON cs.fk_campaign_map = cm.rowid";
		$sql .= " WHERE cm.entity = ".getEntity('mailchimp');
		$sql .= " ORDER BY cs.last_sync DESC LIMIT ".((int) $max);
		$resql = $db->query($sql);

		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$contents[] = array(
					0 => array('text' => dol_escape_htmltag($obj->subject !== '' ? $obj->subject : '(sans objet)'), 'asis' => 1),
					1 => array('text' => (int) $obj->emails_sent.' env.'),
					2 => array('text' => (int) $obj->unique_opens.' ouv. ('.round(100 * (float) $obj->open_rate, 1).' %)'),
					3 => array('text' => (int) $obj->unique_subscriber_clicks.' clics ('.round(100 * (float) $obj->click_rate, 1).' %)'),
				);
			}
			$db->free($resql);
		}

		if (empty($contents)) {
			$contents[] = array(
				0 => array('text' => '<span class="opacitymedium">'.$langs->trans("MailchimpNoReports").'</span>', 'asis' => 1),
			);
		}

		$this->info_box_contents = $contents;
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
