<?php
/* Copyright (C) 2026  Gael <gael@example.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    lib/mailchimp_crypto.lib.php
 * \ingroup mailchimp
 * \brief   Chiffrement/déchiffrement de la clé API Mailchimp (AES-256-CBC).
 */

/**
 * Cle de chiffrement utilisée pour la clé API.
 * Utilise la constante MAIN_MAILCHIMP_CRYPT_KEY si définie dans conf.php,
 * sinon une cle derivee de l'installation (moins robuste : a surcharger en production).
 *
 * @return string
 */
function mailchimp_get_crypt_key()
{
	global $conf;

	if (function_exists('getDolGlobalString') && getDolGlobalString('MAIN_MAILCHIMP_CRYPT_KEY')) {
		return getDolGlobalString('MAIN_MAILCHIMP_CRYPT_KEY');
	}
	// Fallback derivee de l'installation : stable pour un meme Dolibarr, mais recommander
	// d'ajouter $dolibarr_main_data_root ou MAIN_MAILCHIMP_CRYPT_KEY dans conf.php.
	return hash('sha256', (defined('DOL_DATA_ROOT') ? DOL_DATA_ROOT : 'mailchimp').'|mailchimp-apikey');
}

/**
 * Chiffre une chaine. Format retourne : iv:cipher (les deux en base64).
 *
 * @param string $plaintext Chaine en clair
 * @return string|false     Chaine chiffree, false en cas d'echec
 */
function mailchimp_encrypt($plaintext)
{
	if ($plaintext === '' || $plaintext === null) {
		return '';
	}
	$key = hex2bin(substr(mailchimp_get_crypt_key(), 0, 64));
	$iv = random_bytes(16);
	$cipher = openssl_encrypt((string) $plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
	if ($cipher === false) {
		return false;
	}
	return base64_encode($iv).':'.base64_encode($cipher);
}

/**
 * Dechiffre une chaine au format iv:cipher.
 *
 * @param string $enc Chaine chiffree
 * @return string     Chaine en clair ('' si entree vide ou invalide)
 */
function mailchimp_decrypt($enc)
{
	if ($enc === '' || $enc === null || strpos($enc, ':') === false) {
		return '';
	}
	list($iv64, $cipher64) = explode(':', $enc, 2);
	$iv = base64_decode($iv64);
	$cipher = base64_decode($cipher64);
	if ($iv === false || $cipher === false || strlen($iv) !== 16) {
		return '';
	}
	$key = hex2bin(substr(mailchimp_get_crypt_key(), 0, 64));
	$plain = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
	return $plain === false ? '' : $plain;
}

/**
 * Genere un secret aleatoire (utilise pour l'URL du webhook).
 * @param int $length
 * @return string
 */
function mailchimp_generate_secret($length = 32)
{
	return substr(bin2hex(random_bytes($length)), 0, $length);
}
