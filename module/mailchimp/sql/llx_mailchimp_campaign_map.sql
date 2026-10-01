-- Copyright (C) 2026 Gael <gael@example.com>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.

--
-- Correspondance campagnes Dolibarr (llx_mailing) <-> campagnes Mailchimp
--

CREATE TABLE llx_mailchimp_campaign_map(
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_mailing integer,								-- llx_mailing.rowid (0 = campagne creee directement dans le module)
	campaign_id varchar(32) NOT NULL,				-- id de campagne Mailchimp
	list_id varchar(32),							-- audience ciblee au moment de la creation
	status varchar(32),								-- statut Mailchimp (save, send, schedule, paused, sent...)
	subject varchar(255),
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer
) ENGINE=innodb;
