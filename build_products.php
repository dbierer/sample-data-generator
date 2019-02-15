<?php
/** 
 * builds $targetDb.$targetCollection MongoDB collection
 * WARNING: drops the collection before generating sample data
 */
require __DIR__ . '/vendor/autoload.php';
use Application\Client;
use Application\LoremIpsum;

define('LOREM_IPSUM', __DIR__ . '/lorem_ipsum.txt');

// init vars
$max        = 300;         // target number of entries to generate
$inserted   = 0;
$processed  = 0;
$written    = 0;
$writeJs    = TRUE;    // set this TRUE to output JS file to perform inserts
$writeBson  = FALSE; // set this TRUE to directly input into MongoDB database
$sourceDb   = 'source_data';
$targetDb   = 'sweetscomplete';
$targetCollection = 'products';
$targetJs   = __DIR__ . '/' . $targetDb . '_' . $targetCollection . '_insert.js';
$loremIpsum = file(LOREM_IPSUM);
$productsCsv = new SplFileObject($targetDb . '/products.csv', 'r');

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
    $units  = ['box','tin','piece','item'];
    $categories = ['cake','chocolate','cookie','donut','pie'];

    // category, productKey, price
    $headers = $productsCsv->fgetcsv();
    
    // build sample data
    $count = 1;
    while ($row = $productsCsv->fgetcsv()) {
        // category,productKey,price,unit
        if ($row && count($row) == 4) {
            $prodKey = $row[1];
            $photoFn = __DIR__ . '/' . $targetDb . '/' . $prodKey . '.png';
            $skuKey  = array_search($row[0], $categories);
            $sku     = strtoupper(substr($prodKey, 0, 4))
                     . (($skuKey + 1) * 100 + $count);
            $count += 3;
            $description = LoremIpsum::generateIpsum($loremIpsum, rand(1,5));
                            
            // set up document to be inserted
            $insert = [
                'productKey'      => $prodKey,
                'productPhoto'    => (file_exists($photoFn)) 
                                     ? base64_encode(file_get_contents($photoFn))
                                     : '',
                'MainProductInfo' => [
                    'skuNumber'   => $sku,
                    'category'    => $row[0],
                    'title'       => ucwords(str_replace('_', ' ', $row[1])),
                    'description' => $description,
                    'price'       => $row[2],
                ],
                'InventoryInfo' => [
                    'unit'                => $row[3],
                    'costPerUnit'         => $row[2],
                    'unitsOnHand' => rand(0,999),
                ],
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

    }

    echo $processed . ' documents processed' . PHP_EOL;
    echo $inserted  . ' documents inserted' . PHP_EOL;
    echo $written   . ' documents written' . PHP_EOL;

} catch (Exception $e) {
    echo $e->getMessage();
}
