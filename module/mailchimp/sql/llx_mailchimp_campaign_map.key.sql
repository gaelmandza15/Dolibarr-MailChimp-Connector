-- Copyright (C) 2026 Gael <gael@example.com>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.

ALTER TABLE llx_mailchimp_campaign_map ADD UNIQUE INDEX uk_mailchimp_campaign_map (entity, campaign_id);
ALTER TABLE llx_mailchimp_campaign_map ADD INDEX idx_mailchimp_campaign_map_mailing (fk_mailing);

ALTER TABLE llx_mailchimp_campaign_map ADD CONSTRAINT fk_mailchimp_campaign_map_user_creat FOREIGN KEY (fk_user_creat) REFERENCES llx_user (rowid);
