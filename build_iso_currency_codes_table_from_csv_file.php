<?php
// builds "source_data.iso_country_codes" MongoDB collection
// WARNING: drops the collection before generating sample data
// data is provided under by GeoNames (http://www.geonames.org) under a Creative Commons Attribution 3.0 License
define('DATA_SOURCE', __DIR__ . '/iso_currency_codes.csv');
define('EXCLUSION_FILE', __DIR__ . '/exclusion.txt');

require __DIR__ . '/vendor/autoload.php';
use Application\ {Client, Build};

// set up mongodb client + collection
$params = ['host' => '127.0.0.1'];
$client = (new Client($params))->getClient();
echo Build::populate($client->source_data->iso_currency_codes, DATA_SOURCE, ',', EXCLUSION_FILE);
