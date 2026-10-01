-- Copyright (C) 2026 Gael <gael@example.com>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.

--
-- Statistiques de performance des campagnes Mailchimp (rappel via cron)
--

CREATE TABLE llx_mailchimp_campaign_stats(
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_campaign_map integer NOT NULL,
	emails_sent integer DEFAULT 0,
	opens integer DEFAULT 0,
	unique_opens integer DEFAULT 0,
	open_rate double DEFAULT 0,
	clicks integer DEFAULT 0,
	unique_subscriber_clicks integer DEFAULT 0,
	click_rate double DEFAULT 0,
	unsubscribes integer DEFAULT 0,
	bounces integer DEFAULT 0,
	last_sync datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
