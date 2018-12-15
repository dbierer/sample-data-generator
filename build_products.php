<?php
/** 
 * builds "sweetscomplete.products" MongoDB collection
 * WARNING: drops the collection before generating sample data
 */
require __DIR__ . '/vendor/autoload.php';
use Application\Client;

// source files
define('SOURCE_ISP', __DIR__ . '/isp.txt');

// init vars
$max = 300;    // target number of entries to generate

// set up mongodb client + collections
$params = ['host' => '127.0.0.1'];
$client = (new Client($params))->getClient();

// build arrays from source files
$firstNamesFemale = file(SOURCE_FIRST_NAMES_FEMALE);
$firstNamesMale   = file(SOURCE_FIRST_NAMES_MALE);
$surnames         = file(SOURCE_SURNAMES);
$isps             = file(SOURCE_ISP);
$isoCodes         = file(SOURCE_COUNTRIES);
$socMedia         = ['GO' => 'google', 'TW' => 'twitter', 'FB' => 'facebook', 'LN' => 'line', 'SK' => 'skype','LI' => 'linkedin'];
$street1          = ['Winding','Blue','Red','Green','Big','Little','Long','Short'];
$street2          = ['Bough','Tree','Bridge','Creek','River','Bend','Mountain','Hill','Ditch','Gulch','Gully','Canyon','Mound','Woods','Ridge','Stream'];
$street3          = ['Way','Street','Boulevard','Avenue','Drive','Road','Circle','Ride'];
$buildingName     = NULL;
$floor            = NULL;
$roomNumber       = NULL;

// build list of ISO codes
$isoCodes = [];
$cursor   = $client->source_data->post_codes->aggregate([['$group' => ['_id' => '$countryCode']]]);
foreach ($cursor as $document) {
    $isoCodes[] = $document->_id;
}

// empty out target collection
$target = $client->sweetscomplete->customers;
$target->drop();

// build sample data
$processed = 0;
$inserted  = 0;
for ($x = 100; $x < ($max + 100); $x++) {
    

    // set up document to be inserted
    $insert['sku'] = $sku;            // unique key
    $insert['MainProductInfo'] => [
        'sku'         => $sku,
        'title'       => $title,
        'description' => $description,
        'price'       => $price
    ];
    $insert['InventoryInfo'] => [
        'unit'                => $unit,
        'costPerUnit'         => $cost,
        'numberOfUnitsOnHand' => $qoh
    ];

    if ($target->insertOne($insert)) {
        $inserted++;
    }
    $processed++;
}

try {
    echo $processed . ' documents processed' . PHP_EOL;
    echo $inserted  . ' documents inserted' . PHP_EOL;
} catch (Exception $e) {
    echo $e->getMessage();
}
