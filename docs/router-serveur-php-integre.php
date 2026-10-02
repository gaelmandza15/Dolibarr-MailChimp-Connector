<?php
// Routeur pour le serveur PHP integre (php -S) - contourne le bug Dolibarr :
// main.inc.php lit $_SERVER["QUERY_STRING"] sans garde, mais php -S ne la definit
// pas quand l'URL n'a pas de parametre (Apache la definit toujours, chaine vide).
if (!isset($_SERVER['QUERY_STRING'])) {
	$_SERVER['QUERY_STRING'] = '';
}
// Servir les fichiers existants (css, js, images) tel quel
$file = __DIR__.parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
if ($file !== __DIR__ && is_file($file)) {
	return false;
}
// Le reste est gere par le mecanisme standard du serveur integre
return false;
