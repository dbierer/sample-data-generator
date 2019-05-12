<?php
/**
 * builds $targetDb.bookings MongoDB collection
 * WARNING: drops the collection before generating sample data
 */
define('DATE_FORMAT', 'Y-m-d H:i:s');

require __DIR__ . '/vendor/autoload.php';
use Application\ {Client, MakeFake};

// init vars
$inserted         = 0;
$processed        = 0;
$written          = 0;
$writeJs          = TRUE;    // set this TRUE to output JS file to perform inserts
$writeBson        = TRUE;   // set this TRUE to directly input into MongoDB database
$sourceDb         = 'booksomeplace';
$targetDb         = 'booksomeplace';
$targetCollection = 'common';
$targetJs         = __DIR__ . '/' . $targetDb . '_' . $targetCollection . '_insert.js';
$openJs           = 'db.' . $targetCollection . '.insertOne(' . PHP_EOL;
$closeJs          = ');' . PHP_EOL;

// common data:
$common = [
    'title'        => ['Mr','Ms','Dr'],
    'phoneType'    => ['home', 'work', 'mobile', 'fax'],
    'socMediaType' => ['google','twitter','facebook','skype','line','linkedin'],
    'genderType'   => ['male','female','other'],
    'propertyType' => ['hotel','motel','inn','guest house','hostel','resort','serviced apartment','condo','b & b','lodge'],
    'facilityType' => ['outdoor pool','indoor pool','free parking','WiFi','fitness center','business center','pharmacy','sauna','jacuzzi','buffet breakfast'],
    'chain'        => ['Accor','Hyatt','Hilton'],
    'roomType'     => ['premium','standard','poolside','groundFloor'],
    'bedType'      => ['single','double','queen','king'],
    'currency'     => ['AUD','CAD','EUR','GBP','INR','NZD','SGD','USD'],
    'rsvStatus'    => ['pending','confirmed','cancelled'],
    'payStatus'    => ['pending','confirmed','refunded']
];

// set up javascript
if ($writeJs) {
    $jsFile = new SplFileObject($targetJs, 'w');
    $outputJs = 'conn = new Mongo();' . PHP_EOL
              . 'db = conn.getDB("' . $targetDb . '");' . PHP_EOL
              . 'db.' . $targetCollection . '.drop();' . PHP_EOL;
    $jsFile->fwrite($outputJs);
    echo $outputJs;
}


try {

    // set up mongodb client + collections
    $params = ['host' => '127.0.0.1'];
    $client = (new Client($params))->getClient();
    $target = $client->$targetDb->$targetCollection;

    if ($writeBson) $target->drop();

    foreach ($common as $key => $value) {

        $insert = ['key' => $key, 'value' => $value];

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
