<?php
/* Copyright (C) 2026  Gael <gael@example.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    class/ActionsMailchimp.class.php
 * \ingroup mailchimp
 * \brief   Hooks du module Mailchimp (injection dans les fiches tiers, contacts, emails de masse).
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonhookactions.class.php';

/**
 * Hooks du module Mailchimp.
 */
class ActionsMailchimp extends CommonHookActions
{
	/** @var array Hook contexts geres */
	public $module_number = 421880;

	/**
	 * Ajoute du contenu dans les fiches (hook printObjectSubLine / formObjectOptions etc.).
	 *
	 * @param array $parameters Parametres du hook
	 * @return int              0 = on ne remplace rien, 1 = on remplace
	 */
	public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs;

		$errors = array();
		if (in_array($parameters['currentcontext'], array('thirdpartycard', 'contactcard')) && !empty($conf->mailchimp->enabled)) {
			// Phase 2 : afficher le statut Mailchimp du tiers/contact (badge sous la fiche)
		}
		return 0;
	}

	/**
	 * Hook addMoreActionsButtons : ajoute des boutons d'action Mailchimp sur les fiches.
	 *
	 * @param array $parameters
	 * @return int
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs, $user;

		if (in_array($parameters['currentcontext'], array('thirdpartycard', 'contactcard')) && !empty($conf->mailchimp->enabled)) {
			// Phase 2 : bouton "Synchroniser vers Mailchimp" (si droit sync)
			// Phase 4 : bouton "Creer une campagne" sur la fiche tiers (si droit campaigns)
		}
		return 0;
	}
}
