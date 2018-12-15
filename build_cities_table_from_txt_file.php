<?php
// builds MongoDB "source_data.cities" collection
// WARNING: drops the collection before generating sample data
// Provided by GeoNames (http://www.geonames.org) under a Creative Commons Attribution 3.0 License
define('DATA_SOURCE', __DIR__ . '/cities15000.txt');

require __DIR__ . '/vendor/autoload.php';
use Application\ {Client, Build};

// set up mongodb client + collection
$params = ['host' => '127.0.0.1'];
$client = (new Client($params))->getClient();

echo Build::populate(DATA_SOURCE, "\t", $client->source_data->cities);
