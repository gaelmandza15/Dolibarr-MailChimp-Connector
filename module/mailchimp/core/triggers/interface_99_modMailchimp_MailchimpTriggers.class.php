<?php
/* Copyright (C) 2026  Gael <gael@example.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    core/triggers/interface_99_modMailchimp_MailchimpTriggers.class.php
 * \ingroup mailchimp
 * \brief   Triggers du module : met les tiers/contacts modifies en file pour la sync Mailchimp.
 */

/**
 * Classe des triggers du module Mailchimp.
 */
class InterfaceMailchimpTriggers
{
	/** @var DoliDB */
	public $db;

	/** @var string Nom du fichier trigger (utilise par Dolibarr pour la trace) */
	public $name;

	/** @var string Famille du trigger */
	public $family = 'mailchimp';

	/** @var string Description */
	public $description;

	/** @var int Version */
	public $version = '1.0.0';

	/** @var string Pictogramme */
	public $picto = 'email';

	/**
	 * @param DoliDB $db
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->name = 'MAILCHIMP_TRIGGERS';
		$this->description = 'Mise en file des evenements tiers/contacts pour la synchronisation Mailchimp';
	}

	/**
	 * Gestion des evenements metier.
	 *
	 * @param string       $action  Code de l'evenement (ex: COMPANY_CREATE)
	 * @param CommonObject $object  Objet concerne
	 * @param User         $user    Utilisateur a l'origine
	 * @param Translate    $langs   Traductions
	 * @param Conf         $conf    Configuration
	 * @return int                  0 = non traite, 1 = traite
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (empty($conf->mailchimp->enabled)) {
			return 0;
		}

		$object_type = null;
		if (strpos($action, 'COMPANY_') === 0) {
			$object_type = 'company';
		} elseif (strpos($action, 'CONTACT_') === 0) {
			$object_type = 'contact';
		}
		if ($object_type === null) {
			return 0;
		}

		require_once dol_buildpath('/mailchimp/class/mailchimpcontactsync.class.php', 0);
		$sync = new MailchimpContactSync($this->db);
		$event = str_replace(array('COMPANY_', 'CONTACT_'), '', $action); // CREATE, MODIFY, DELETE
		$sync->queueEvent($object_type, $object->id, $event);

		return 1;
	}
}
