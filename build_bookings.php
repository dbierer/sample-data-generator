<?php
/**
 * builds $targetDb.bookings MongoDB collection
 * WARNING: drops the collection before generating sample data
 */
define('DATE_FORMAT', 'Y-m-d H:i:s');

require __DIR__ . '/vendor/autoload.php';
use Application\ {Client, MakeFake};

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
$max        = 2000;         // target number of entries to generate
$inserted   = 0;
$processed  = 0;
$written    = 0;
$writeJs    = TRUE;    // set this TRUE to output JS file to perform inserts
$writeBson  = FALSE; // set this TRUE to directly input into MongoDB database
$sourceDb         = 'booksomeplace';
$targetDb         = 'booksomeplace';
$targetCollection = 'bookings';
$targetJs         = __DIR__ . '/' . $targetDb . '_' . $targetCollection . '_insert.js';

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
    $properties = $client->$sourceDb->properties;

    // get max # customers
    $maxCust = $customers->count();
    // get max # properties
    $maxProp = $properties->count();

    // build sample data
    for ($x = 0; $x < $max; $x++) {

        //*** BookingInfo ******************************************************
        $bookingInfo = $makeFake->makeBookingInfo($x);

        //*** CustomerParty ******************************************************
        $customerParty = [];
        for ($y = 0; $y < rand(1, 4); $y ++) {
            $customerParty[] = [
                'name' => $makeFake->makeName($x),
                'other' => $makeFake->makeOtherInfo($x)
            ];
        }

        //*** Customer ******************************************************
        // pull customer at random
        $skipCust = rand(0, $maxCust);
        if ($skipCust == 0) {
            $cust = $customers->findOne(
                [],
                ['projection' => ['customerKey' => 1, 'name' => 1, 'address' => 1, 'contact' => 1]]
            );
        } else {
            $cust = $customers->findOne(
                [],
                ['projection' => ['customerKey' => 1, 'name' => 1, 'address' => 1, 'contact' => 1],
                'skip' => $skipCust]
            );
        }
        if (!$cust) continue;
        $custBooking = [
            'customerKey'     => $cust['customerKey'],
            'customerName'    => $cust['name'],
            'customerAddr'    => $cust['address'],
            'customerContact' => $cust['contact'],
            'custParty'       => $customerParty
        ];

        // pull property at random
        $skipProp = rand(0, $maxProp);
        if ($skipProp == 0) {
            $prop = $properties->findOne(
                [],
                ['projection' => ['propertyKey' => 1, 'propName' => 1, 'address' => 1, 'contactInfo' => 1, 'rooms' => 1]]
            );
        } else {
            $prop = $properties->findOne(
                [],
                ['projection' => ['propertyKey' => 1, 'propName' => 1, 'address' => 1, 'contactInfo' => 1, 'rooms' => 1],
                'skip' => $skipProp]
            );
        }
        if (!$prop) continue;
        $propBooking = [
            'propertyKey'     => $prop['propertyKey'],
            'propertyName'    => $prop['propName'],
            'propertyAddr'    => $prop['address'],
            'propertyContact' => $prop['contactInfo']
        ];

        // pull rooms at random
        $numPeeps = count($customerParty);
        $numRooms = $prop['rooms']->count();
        $maxRooms = $numPeeps;
        $roomBooking = [];

        do {
            $qty = ($numPeeps == 1) ? 1 : rand(1, $numPeeps);
            $whichRoom = rand(0, $numRooms);
            if (!$prop['rooms']->offsetExists($whichRoom)) $whichRoom = 0;
            $roomType = $prop['rooms']->offsetGet($whichRoom);
            $roomBooking[] = [
                'roomType'    => $roomType['type'],
                'roomTypeKey' => $roomType['roomTypeKey'],
                'price'       => $roomType['price'] * $qty,
                'qty'         => $qty
            ];
            if ($numPeeps == 1) {
                $maxRooms = 0;
            } else {
                $maxRooms -= $qty;
            }
        } while ($maxRooms > 0);

        // calculate price
        $price = 0;
        foreach ($roomBooking as $room)
            $price += $room['price'] * $room['qty'];

        // generate booking key
        $bookingKey = str_replace(['-',' '], '', substr($bookingInfo['arrivalDate'], 0, 10))
                    . strtoupper(substr($cust['name']['last'], 0, 4));

        $insert = [
            'bookingKey'   => $bookingKey,
            'customer'     => $custBooking,
            'property'     => $propBooking,
            'bookingInfo'  => $bookingInfo,
            'rooms'        => $roomBooking,
            'totalPrice'   => $price
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
