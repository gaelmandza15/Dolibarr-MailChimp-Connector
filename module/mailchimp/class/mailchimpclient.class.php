<?php
/* Copyright (C) 2026  Gael <gael@example.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    class/mailchimpclient.class.php
 * \ingroup mailchimp
 * \brief   Client REST pour l'API Mailchimp Marketing v3.0, sans dependance Composer.
 */

/**
 * Exception levee par MailchimpClient en cas d'erreur API.
 */
class MailchimpApiException extends Exception
{
	/** @var int Statut HTTP de la reponse */
	public $status = 0;

	/** @var string|null Message d'erreur detaille renvoye par Mailchimp */
	public $detail = null;

	/** @var array Corps complet de la reponse d'erreur (avec errors[] eventuel) */
	public $response = array();

	/**
	 * @param int         $status   Statut HTTP
	 * @param string      $message  Message d'erreur
	 * @param string|null $detail   Detail renvoye par l'API
	 * @param array       $response Corps complet decode
	 */
	public function __construct($status, $message, $detail = null, $response = array())
	{
		$this->status = $status;
		$this->detail = $detail;
		$this->response = $response;
		parent::__construct($message, $status);
	}
}

/**
 * Client REST pour l'API Mailchimp Marketing v3.0.
 *
 * Contraintes API prises en compte :
 * - authentification HTTP Basic (cle API, datacenter suffixe a la cle) ;
 * - limite de 10 connexions simultanees : retry avec back-off sur HTTP 429 ;
 * - pagination offset/limit via getPaginated() ;
 * - lots de 500 operations maximum via batch().
 */
class MailchimpClient
{
	/** @var string Cle API Mailchimp */
	private $apikey;

	/** @var string Prefixe datacenter (ex: us21) */
	private $dc;

	/** @var string URL de base de l'API */
	private $api_url;

	/** @var int Nombre de retries en cas de 429 */
	private $max_retries = 3;

	/** @var array Derniere reponse brute (debug) */
	public $last_response = array();

	/**
	 * @param string $apikey Cle API Mailchimp au format 'xxxxxxxx-us21'
	 * @throws MailchimpApiException Si la cle ne contient pas de datacenter
	 */
	public function __construct($apikey)
	{
		$apikey = trim((string) $apikey);
		$pos = strrpos($apikey, '-');
		if ($pos === false || $pos === strlen($apikey) - 1) {
			throw new MailchimpApiException(0, 'Invalid Mailchimp API key: datacenter suffix missing');
		}
		$this->apikey = $apikey;
		$this->dc = substr($apikey, $pos + 1);
		$this->api_url = 'https://'.$this->dc.'.api.mailchimp.com/3.0';
	}

	/**
	 * Retourne le prefixe datacenter deduit de la cle.
	 * @return string
	 */
	public function getDatacenter()
	{
		return $this->dc;
	}

	/**
	 * Test de connexion : GET /ping.
	 * @return array Reponse decodee (doit contenir health_status)
	 */
	public function ping()
	{
		return $this->get('/ping');
	}

	/**
	 * Appel REST generique.
	 *
	 * @param string     $method   HTTP method (GET, POST, PATCH, PUT, DELETE)
	 * @param string     $path     Chemin API commencant par '/' (ex: '/lists')
	 * @param array|null $data     Corps JSON (POST/PATCH/PUT)
	 * @param int        $attempt  Usage interne (nombre de tentatives effectuees)
	 * @return array                      Reponse JSON decodee
	 * @throws MailchimpApiException
	 */
	public function request($method, $path, $data = null, $attempt = 0)
	{
		$url = $this->api_url.$path;

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_USERPWD, 'anystring:'.$this->apikey);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		curl_setopt($ch, CURLOPT_TIMEOUT, 120); // timeout maximum impose par Mailchimp
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
		if ($data !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		}

		$body = curl_exec($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		$curl_error = curl_error($ch);
		curl_close($ch);

		if ($body === false) {
			throw new MailchimpApiException(0, 'cURL error: '.$curl_error);
		}

		$decoded = json_decode($body, true);
		if (!is_array($decoded)) {
			$decoded = array();
		}

		$this->last_response = array('status' => $status, 'body' => $decoded);

		if ($status >= 200 && $status < 300) {
			return $decoded;
		}

		// Limite de connexions simultanees : back-off puis retry (API: 10 connexions max)
		if ($status === 429 && $attempt < $this->max_retries) {
			sleep(2 * ($attempt + 1));
			return $this->request($method, $path, $data, $attempt + 1);
		}

		$message = isset($decoded['title']) ? $decoded['title'] : 'HTTP error '.$status;
		$detail = isset($decoded['detail']) ? $decoded['detail'] : $body;
		throw new MailchimpApiException($status, $message, $detail, $decoded);
	}

	/** @param string $path @param array $params @return array */
	public function get($path, $params = array())
	{
		if (!empty($params)) {
			$path .= (strpos($path, '?') === false ? '?' : '&').http_build_query($params);
		}
		return $this->request('GET', $path);
	}

	/** @param string $path @param array $data @return array */
	public function post($path, $data = array())
	{
		return $this->request('POST', $path, $data);
	}

	/** @param string $path @param array $data @return array */
	public function patch($path, $data = array())
	{
		return $this->request('PATCH', $path, $data);
	}

	/** @param string $path @param array $data @return array */
	public function put($path, $data = array())
	{
		return $this->request('PUT', $path, $data);
	}

	/** @param string $path @return array */
	public function delete($path)
	{
		return $this->request('DELETE', $path);
	}

	/**
	 * Parcourt tous les elements d'une ressource paginee (offset/limit, 1000 max par page).
	 *
	 * @param string $path        Chemin API (ex: '/lists/{id}/members')
	 * @param array  $params      Parametres additionnels de la requete
	 * @param int    $limit       Taille de page (max 1000)
	 * @param int    $maxpages    Garde-fou anti boucle infinie
	 * @return array              Liste aplatie des elements ($decoded['members'] ou 1er tableau de la reponse)
	 */
	public function getPaginated($path, $params = array(), $limit = 1000, $maxpages = 100)
	{
		$all = array();
		$offset = 0;
		$pages = 0;
		while ($pages < $maxpages) {
			$page_params = array_merge($params, array('offset' => $offset, 'limit' => $limit));
			$response = $this->get($path, $page_params);
			$items = array();
			foreach ($response as $value) {
				if (is_array($value)) {
					$items = $value;
					break;
				}
			}
			if (empty($items)) {
				break;
			}
			$all = array_merge($all, $items);
			if (count($items) < $limit) {
				break;
			}
			$offset += $limit;
			$pages++;
		}
		return $all;
	}

	/**
	 * Envoie un lot d'operations asynchrones via POST /batches (500 operations max par lot).
	 *
	 * @param array $operations Liste d'operations array('method'=>..., 'path'=>..., 'body'=>array|null)
	 * @return array            Reponse de l'API (id, status, total_operations...)
	 * @throws MailchimpApiException
	 */
	public function batch($operations)
	{
		if (count($operations) > 500) {
			throw new MailchimpApiException(0, 'Batch endpoint limited to 500 operations per call');
		}
		return $this->post('/batches', array('operations' => $operations));
	}

	/**
	 * Statut d'un lot asynchrone (GET /batches/{id}).
	 * @param string $batch_id
	 * @return array
	 */
	public function getBatchStatus($batch_id)
	{
		return $this->get('/batches/'.rawurlencode($batch_id));
	}

	/**
	 * Hash Mailchimp d'un membre : MD5 de l'email en minuscules.
	 * @param string $email
	 * @return string
	 */
	public static function subscriberHash($email)
	{
		return md5(strtolower(trim((string) $email)));
	}
}
