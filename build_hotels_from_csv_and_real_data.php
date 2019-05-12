<?php
/**
 * builds $targetDb.$targetCol MongoDB collection
 */
require __DIR__ . '/vendor/autoload.php';
use Application\ {Client, MakeFake};

// source files
$config = [
    'SOURCE_ISP' => __DIR__ . '/isp.txt',
    'SOURCE_BRANDED' => __DIR__ . '/branded_hotels.csv',
    'SOURCE_SURNAMES' => __DIR__ . '/surnames.txt',
    'SOURCE_COUNTRIES' => __DIR__ . '/iso_codes.txt',
    'SOURCE_LOREM_IPSUM' => __DIR__ . '/lorem_ipsum.txt',
    'SOURCE_FIRST_NAMES_MALE' => __DIR__ . '/first_names_male.txt',
    'SOURCE_FIRST_NAMES_FEMALE' => __DIR__ . '/first_names_female.txt',
];
$makeFake  = new MakeFake($config);

// init vars
$max       = 500;       // target number of entries to generate
$writeJs   = TRUE;      // set this TRUE to output JS file to perform inserts
$writeBson = FALSE;     // set this TRUE to directly input into MongoDB database
$sourceDb  = 'source_data';
$targetDb  = 'booksomeplace';
$targetCol = 'properties';
$targetJs  = __DIR__ . '/' . $targetDb . '_' . $targetCol . '_insert.js';
$processed = 0;
$inserted  = 0;
$written   = 0;

// set up javascript
if ($writeJs) {
    $jsFile = new SplFileObject($targetJs, 'w');
    $outputJs = 'conn = new Mongo();' . PHP_EOL
              . 'db = conn.getDB("' . $targetDb . '");' . PHP_EOL
              . 'db.' . $targetCol . '.drop();' . PHP_EOL;
    $openJs   = 'db.' . $targetCol . '.insertOne(' . PHP_EOL;
    $closeJs  = ');' . PHP_EOL;
    $jsFile->fwrite($outputJs);
    echo $outputJs;
}

try {

    // set up mongodb client + collections
    $params    = ['host'    => '127.0.0.1'];
    $client    = (new Client($params))->getClient();
    $target    = $client->$targetDb->$targetCol;
    $source    = $client->$sourceDb;

    // build list of partner keys
    $partnerKeys = $makeFake->buildPartnerKeys($client->$targetDb->partners);
    if (!$partnerKeys) throw new Exception('ERROR: partner keys');

    // build list of customer keys
    $customerKeys = $makeFake->buildCustomerKeys($client->$targetDb->customers);
    if (!$customerKeys) throw new Exception('ERROR: customer keys');

    // build list of ISO codes
    $isoCodes = $makeFake->buildIsoCodes($source->post_codes);
    if (!$isoCodes) throw new Exception('ERROR: ISO codes');

    // empty out target collection if write flag is set
    if ($writeBson) $target->drop();

    // build sample data
    for ($x = 100; $x < ($max + 100); $x++) {

        // pick country code
        if (($x % 2) === 0) {
            $isoCode = $makeFake->weightedIso[array_rand($makeFake->weightedIso)];
        } else {
            $isoCode = trim($isoCodes[array_rand($isoCodes)]);
        }

        //*** Build Address *********************************************************
        $location = $makeFake->makeAddress($x, $source->post_codes, $isoCode);
        if (!$location) continue;

        //*** Build PropertyInfo *********************************************************
        $propInfo = $makeFake->makePropInfo($x, $source->iso_country_codes, $isoCode);

        //*** Build Name ******************************************************
        $name = $makeFake->makeName($x);

        //*** Build Contact ******************************************************
        $contact = $makeFake->makeContact($x, $source->iso_country_codes, $isoCode);

        //*** Build RoomTypes *********************************************************
        $rooms = $makeFake->makeRoomTypes();

        //*** Build Reviews *********************************************************
        $reviews = $makeFake->makeReviews($x);

        // calculate the overall rating
        $rating = 0;
        $numReviews = 0;
        foreach ($reviews as $review) {
            $numReviews++;
            foreach ($review as $item)
                $rating += (int) $item;
        }
        $rating = ($rating) ? ($rating / $numReviews) / 4 : $rating;

        // generate weighted total booked
        switch (TRUE) {
            case ($x % 7 === 0) :
                $totalBooked = rand(100,9999);
                break;
            case ($x % 3 === 0) :
                $totalBooked = rand(10,999);
                break;
            default :
                $totalBooked = rand(1,99);
        }

        // build data to write
        $insert = [
            'propertyKey' => strtoupper(substr($makeFake->makePropName(), 0, 4)) . rand(1000, 9999),
            'partnerKey'  => $makeFake->partnerKeys[array_rand($makeFake->partnerKeys)],
            'propName'    => $makeFake->makePropName(),
            'propInfo'    => $propInfo,
            'address'     => $location,
            'contactName' => $name,
            'contactInfo' => $contact,
            'rooms'       => $rooms,
            'reviews'     => $reviews,
            'rating'      => $rating,
            'totalBooked' => $totalBooked
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
