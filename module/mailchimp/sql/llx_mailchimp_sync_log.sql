-- Copyright (C) 2026 Gael <gael@example.com>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.

--
-- Journal de synchronisation et file d'attente des evenements
--

CREATE TABLE llx_mailchimp_sync_log(
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	log_type varchar(32) NOT NULL,					-- queue, batch, batch_result, error, import, webhook
	object_type varchar(16),						-- company, contact, campaign
	fk_object integer,
	payload text,									-- donnees de l'evenement ou du lot
	status varchar(16),								-- pending, ok, error
	error_msg text,
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
