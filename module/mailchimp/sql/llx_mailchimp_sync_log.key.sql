-- Copyright (C) 2026 Gael <gael@example.com>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.

ALTER TABLE llx_mailchimp_sync_log ADD INDEX idx_mailchimp_sync_log_queue (entity, log_type, status);
ALTER TABLE llx_mailchimp_sync_log ADD INDEX idx_mailchimp_sync_log_object (object_type, fk_object);
ALTER TABLE llx_mailchimp_sync_log ADD INDEX idx_mailchimp_sync_log_date (date_creation);
