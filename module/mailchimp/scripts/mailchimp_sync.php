#!/usr/bin/env php
<?php
/* Copyright (C) 2026  Gael <gael@example.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    scripts/mailchimp_sync.php
 * \ingroup mailchimp
 * \brief   Script CLI de synchronisation des contacts (usage crontab systeme, alternative au module Scheduled Jobs).
 *
 * Usage: php mailchimp_sync.php [incremental|full] [login|id]
 */

if (!defined('NOSESSION')) {
	define('NOSESSION', '1');
}

// Recupere le chemin de main.inc.php (1er argument) comme pour les scripts Dolibarr standard
$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path = __DIR__.'/';

// Test si mode CLI
if (substr($sapi_type, 0, 3) == 'cgi') {
	echo "Error: This script can only be run from command line.\n";
	exit(-1);
}

// Include Dolibarr environment
require_once $path.'../../../master.inc.php'; // adjust after deployment: htdocs/custom/mailchimp/scripts -> htdocs/master.inc.php is ../../

// Adapt: htdocs/custom/mailchimp/scripts/mailchimp_sync.php -> main file is at htdocs/master.inc.php
// (keep consistent with Dolibarr script conventions; see scripts/cron/cron_run_jobs.php)

require_once dol_buildpath('/mailchimp/lib/mailchimp.lib.php', 0);
require_once dol_buildpath('/mailchimp/class/mailchimpcontactsync.class.php', 0);

$langs->loadLangs(array('mailchimp@mailchimp'));

$mode = isset($argv[1]) ? $argv[1] : 'incremental';

$sync = new MailchimpContactSync($db);
$result = $sync->runCronIncremental();

echo 'Mailchimp sync done, result='.$result."\n";
exit($result);
