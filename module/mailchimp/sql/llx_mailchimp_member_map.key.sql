-- Copyright (C) 2026 Gael <gael@example.com>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.

ALTER TABLE llx_mailchimp_member_map ADD UNIQUE INDEX uk_mailchimp_member_map (entity, list_id, email);
ALTER TABLE llx_mailchimp_member_map ADD INDEX idx_mailchimp_member_map_soc (fk_soc);
ALTER TABLE llx_mailchimp_member_map ADD INDEX idx_mailchimp_member_map_socpeople (fk_socpeople);

ALTER TABLE llx_mailchimp_member_map ADD CONSTRAINT fk_mailchimp_member_map_soc FOREIGN KEY (fk_soc) REFERENCES llx_societe (rowid);
ALTER TABLE llx_mailchimp_member_map ADD CONSTRAINT fk_mailchimp_member_map_socpeople FOREIGN KEY (fk_socpeople) REFERENCES llx_socpeople (rowid);
