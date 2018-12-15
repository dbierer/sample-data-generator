<?php
// Populates "source_data.post_codes" MongoDB collection
// WARNING: drops the collection before generating sample data
// Provided by GeoNames (http://www.geonames.org) under a Creative Commons Attribution 3.0 License
define('DATA_SOURCE', __DIR__ . '/allCountries.txt');
define('EXCLUSION_FILE', __DIR__ . '/excluded.txt');

require __DIR__ . '/vendor/autoload.php';
use Application\ {Client, Build};

// set up mongodb client + collection
$params = ['host' => '127.0.0.1'];
$client = (new Client($params))->getClient();
$fix = function ($headers, $insert) {
    $final = [];
    $count = 1;
    foreach ($insert as $key => $value) {
        // is data is floating point, assumes it's latitude or longitude
        if (is_float($value) && !is_int($value)) {
            // latitude occurs first; if empty, populate it from $value
            $final['latitude'] = ($final['latitude']) ? $final['latitude'] : $value;
            // longitude occurs next; if empty, populate it from $value
            $final['longitude'] = ($final['longitude']) ? $final['longitude'] : $value;
        } else {
            if (isset($headers[$key])) {
                $final[$headers[$key]] = $value;
            } else {
                $final['unknown' . $count++] = $value;
            }
        }
    }
    return $final;
};
echo Build::populate(DATA_SOURCE, "\t", $client->source_data->post_codes, EXCLUSION_FILE, $fix);
