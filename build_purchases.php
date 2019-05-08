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
$alpha      = range('A','Z');

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
    $maxCust = $customers->count();
    // get max # products
    $maxProd = $products->count();

    // build sample data
    for ($x = 0; $x < $max; $x++) {

        // pull customer at random
        $skipCust = rand(0, $maxCust);
        if ($skipCust == 0) {
            $custDoc = $customers->findOne();
        } else {
            $custDoc = $customers->findOne([], ['skip' => $skipCust]);
        }

        if (!$custDoc) continue;
                        
        // create random number of products purchased
        $purchDate = new DateTime('now');                
        if ($x % 3 === 0) {
            $purchDate->add(new DateInterval('P' . rand(1,300) . 'D'));
        } else {
            $purchDate->sub(new DateInterval('P' . rand(1,999) . 'D'));
        }        
        
        // create transaction ID + initialize other product information
        $transId = $purchDate->format('Ymd') . strtoupper($custDoc->firstName[0] . $custDoc->lastName[0]) . sprintf('%04d', rand(0,9999));
        $numProds = rand(1,6);
        $extPrice = 0.00;
        $productsPurchased = [];

        for ($y = 0; $y < $numProds; $y++) {
            
            // pull product at random
            $skipProd = rand(0, $maxProd);
            if ($skipProd == 0) {
                $prodDoc = $products->findOne();
            } else {
                $prodDoc = $products->findOne([], ['skip' => $skipProd]);
            }
            
            if (!$prodDoc) continue;
            
            $qty = rand(1,999);
            $extPrice += $qty * $prodDoc->price;

            // 'AAA111' : {'productKey':'AAA111','qtyPurchased':111,'skuNumber':'11111','category':'AAA','title':'TEST AAA','price':1.11},
            
            $productsPurchased[$prodDoc->productKey] = [
                'productKey'   => $prodDoc->productKey, 
                'qtyPurchased' => $qty, 
                'skuNumber'    => $prodDoc->skuNumber,
                'category'     => $prodDoc->category,
                'title'        => $prodDoc->title,
                'price'        => $prodDoc->price,
            ];
        }
        
        // calc extended price
        
        // set up document to be inserted
        $insert = [
            'transactionId'            => $transId,
            'dateOfPurchase'           => $purchDate->format(DATE_FORMAT),
            'extendedPrice'            => $extPrice,
            'customerKey'              => $custDoc->customerKey,
            'firstName'                => $custDoc->firstName,
            'lastName'                 => $custDoc->lastName,
            'phoneNumber'              => $custDoc->phoneNumber,
            'email'                    => $custDoc->email,
            'streetAddressOfBuilding'  => $custDoc->streetAddressOfBuilding,
            'city'                     => $custDoc->city,
            'stateProvince'            => $custDoc->stateProvince,
            'locality'                 => $custDoc->locality,
            'country'                  => $custDoc->country,
            'postalCode'               => $custDoc->postalCode,
            'latitude'                 => $custDoc->latitude,
            'longitude'                => $custDoc->longitude,
            'productsPurchased'        => $productsPurchased
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
