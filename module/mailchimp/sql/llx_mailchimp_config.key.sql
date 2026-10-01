-- Copyright (C) 2026 Gael <gael@example.com>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.

ALTER TABLE llx_mailchimp_config ADD UNIQUE INDEX uk_mailchimp_config (entity, rowid);

ALTER TABLE llx_mailchimp_config ADD CONSTRAINT fk_mailchimp_config_user_creat FOREIGN KEY (fk_user_creat) REFERENCES llx_user (rowid);
