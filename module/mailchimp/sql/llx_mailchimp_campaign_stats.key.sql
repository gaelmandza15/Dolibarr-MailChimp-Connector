-- Copyright (C) 2026 Gael <gael@example.com>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.

ALTER TABLE llx_mailchimp_campaign_stats ADD UNIQUE INDEX uk_mailchimp_campaign_stats (entity, fk_campaign_map);

ALTER TABLE llx_mailchimp_campaign_stats ADD CONSTRAINT fk_mailchimp_campaign_stats_map FOREIGN KEY (fk_campaign_map) REFERENCES llx_mailchimp_campaign_map (rowid);
