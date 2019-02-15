<?php
/** 
 * builds $targetDb.purchases MongoDB collection
 * WARNING: drops the collection before generating sample data
 */
define('DATE_FORMAT', 'Y-m-d H:i:s');
require __DIR__ . '/vendor/autoload.php';
use Application\Client;

// init vars
$max        = 500;         // target number of entries to generate
$inserted   = 0;
$processed  = 0;
$written    = 0;
$writeJs    = TRUE;    // set this TRUE to output JS file to perform inserts
$writeBson  = FALSE; // set this TRUE to directly input into MongoDB database
$sourceDb         = 'sweetscomplete';
$targetDb         = 'sweetscomplete';
$targetCollection = 'purchases';
$targetJs   = __DIR__ . '/' . $targetDb . '_' . $targetCollection . '_insert.js';

// set up javascript
if ($writeJs) {
    $jsFile = new SplFileObject($targetJs, 'w');
    $outputJs = 'conn = new Mongo();' . PHP_EOL
              . 'db = conn.getDB("' . $targetDb . '");' . PHP_EOL
              . 'db.' . $targetCollection . '.drop();' . PHP_EOL;
    $openJs   = 'db.' . $targetCollection . '.insertOne(' . PHP_EOL;
    $closeJs  = ');' . PHP_EOL;
    $jsFile->fwrite($outputJs);
    echo $outputJs;
}

try {

    // set up mongodb client + collections
    $params = ['host' => '127.0.0.1'];
    $client = (new Client($params))->getClient();
    $target = $client->$targetDb->$targetCollection;
    $customers = $client->$sourceDb->customers;
    $products  = $client->$sourceDb->products;

    // get max # customers
    $maxCust = $customers->countDocuments();
    // get max # products
    $maxProd = $products->countDocuments();
    
    // build sample data
    for ($x = 0; $x < $max; $x++) {

        // pull customer at random
        $skipCust = rand(0, $maxCust);
        if ($skipCust == 0) {
            $custDoc = $customers->findOne([],['projection' => ['PrimaryContactInfo' => 1,'Address' => 1]]);
        } else {
            $custDoc = $customers->findOne([],['projection' => ['PrimaryContactInfo' => 1,'Address' => 1], 'skip' => $skipCust]);
        }
                
        // pull customer at random
        $skipProd = rand(0, $maxProd);
        if ($skipProd == 0) {
            $prodDoc = $products->findOne([],['projection' => ['MainProductInfo' => 1]]);
        } else {
            $prodDoc = $products->findOne([],['projection' => ['MainProductInfo' => 1], 'skip' => $skipProd]);
        }
        
        $qty = rand(1,999);
        $extPrice = $qty * $prodDoc->MainProductInfo->price;
        $purchDate = new DateTime('now');
        $transId = sprintf('%8d%04d', date('Ymd'), $x);
                
        if ($x % 3 === 0) {
            $purchDate->add(new DateInterval('P' . rand(1,300) . 'D'));
        } else {
            $purchDate->sub(new DateInterval('P' . rand(1,999) . 'D'));
        }        
        // set up document to be inserted
        $insert = [
            'transactionId' => $transId,
            'CustomerInfo' => [
                'PrimaryContactInfo' => $custDoc->PrimaryContactInfo,
                'Address' => $custDoc->Address,
            ],
            'MainProductInfo' => $prodDoc->MainProductInfo,
            'PurchaseInfo' => [
                'dateOfPurchase'        => $purchDate->format(DATE_FORMAT),
                'quantityPurchased'     => $qty,
                'extendedPrice'         => $extPrice
            ]
        ];
                        
        // write to MongoDB if flag enabled
        if ($writeBson) {
            if ($target->insertOne($insert)) {
                $inserted++;
            }
        }

        // write to js file if flag enabled
        $outputJs = $openJs . json_encode($insert, JSON_PRETTY_PRINT) . $closeJs;
        if ($writeJs) {
            $jsFile->fwrite($outputJs);
            $written++;
        }
        echo $outputJs;
        $processed++;

    }

    echo $processed . ' documents processed' . PHP_EOL;
    echo $inserted  . ' documents inserted' . PHP_EOL;
    echo $written   . ' documents written' . PHP_EOL;

} catch (Exception $e) {
    echo $e->getMessage();
}
