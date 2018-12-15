<?php
// adds dialing codes to "source_data.iso_country_codes" MongoDB collection
define('INPUT_FILE', __DIR__ . '/iso2_dialing_code.csv');

require __DIR__ . '/vendor/autoload.php';
use Application\Client;

// set up mongodb client + collection
$params = ['host' => '127.0.0.1'];
$client = (new Client($params))->getClient();
$countries = $client->source_data->iso_country_codes;

// read CSV file and process
$dialCodes = [];
$codes = file(INPUT_FILE);
foreach ($codes as $line) {
    [$iso2,$dial] = explode(',', $line);
    $countries->updateOne(['ISO2' => $iso2], ['$set' => ['dialingCode' => trim($dial)]]);
}

