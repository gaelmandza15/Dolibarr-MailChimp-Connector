-- Copyright (C) 2026 Gael <gael@example.com>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.

--
-- Correspondance contacts/tiers Dolibarr <-> membres Mailchimp
--

CREATE TABLE llx_mailchimp_member_map(
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_soc integer,									-- llx_societe.rowid (si membre issu d'un tiers)
	fk_socpeople integer,							-- llx_socpeople.rowid (si membre issu d'un contact)
	email varchar(255) NOT NULL,
	list_id varchar(32) NOT NULL,
	subscriber_hash varchar(64),					-- MD5 de l'email en minuscules (id membre Mailchimp)
	mc_status varchar(32),							-- subscribed, pending, unsubscribed, cleaned, archived
	opt_out smallint DEFAULT 0 NOT NULL,			-- 1 = contact excl de la sync (desabonne)
	last_sync datetime,
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer
) ENGINE=innodb;
