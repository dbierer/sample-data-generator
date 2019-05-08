<?php
/**
 * builds $targetDb.$targetCol MongoDB collection
 */
require __DIR__ . '/vendor/autoload.php';
use Application\Client;

// source files
define('SOURCE_ISP', __DIR__ . '/isp.txt');
define('SOURCE_BRANDED', __DIR__ . '/branded_hotels.csv');
define('SOURCE_COUNTRIES', __DIR__ . '/iso_codes.txt');
define('SOURCE_SURNAMES', __DIR__ . '/surnames.txt');
define('SOURCE_FIRST_NAMES_MALE', __DIR__ . '/first_names_male.txt');
define('SOURCE_FIRST_NAMES_FEMALE', __DIR__ . '/first_names_female.txt');
define('SOURCE_LOREM_IPSUM', __DIR__ . '/lorem_ipsum.txt');

// init vars
$max       = 500;       // target number of entries to generate
$writeJs   = TRUE;      // set this TRUE to output JS file to perform inserts
$writeBson = FALSE;     // set this TRUE to directly input into MongoDB database
$sourceDb  = 'source_data';
$targetDb  = 'booksomething';
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

// build arrays from source files
$firstNamesMale   = file(SOURCE_FIRST_NAMES_MALE);
$surnames         = file(SOURCE_SURNAMES);
$firstNamesFemale = file(SOURCE_FIRST_NAMES_FEMALE);
$isoCodes         = file(SOURCE_COUNTRIES);
$isps             = file(SOURCE_ISP);
$loremIpsum       = file(SOURCE_LOREM_IPSUM);
$weightedIso      = ['US','CA','GB','AU','IN'];
$socMedia         = ['google','twitter','facebook','line','skype','linkedin'];
$name1            = ['Cozy','Riverside','Lakeside','Mountain','Rose','Garden','Valley','Castle','Sleepy','Amazing','Awesome','Romantic','Secluded','Peaceful','Restful','Quiet','Tranquil','Getaway','Take a Break','Famous','Destination','Travel','Voyage'];
$street1          = ['Winding','Blue','Red','Green','Big','Little','Long','Short'];
$street2          = ['Bough','Tree','Bridge','Creek','River','Bend','Mountain','Hill','Ditch','Gulch','Gully','Canyon','Mound','Woods','Ridge','Stream'];
$street3          = ['Way','Street','Boulevard','Avenue','Drive','Road','Circle','Ride'];
$bedType          = ['single', 'double', 'queen', 'king'];
$breakfast        = ['included', 'extra'];
$propertyType     = ['hotel','motel','inn','guest house','hostel','resort','serviced apartment','condo','b & b','lodge'];
$refundable       = ['yes','no'];
$replicaType      = ['primary', 'secondary'];
$roomType         = ['standard', 'double', 'premium', 'VIP', 'family', 'suite'];
$roomLocation     = ['city view', 'poolside', 'riverside', 'lakeside', 'penthouse', 'mountain view'];
$facilityType     = ['outdoor pool','indoor pool','free parking','WiFi','fitness center','business center','pharmacy','sauna','jacuzzi','buffet breakfast'];
$buildingName     = NULL;
$floor            = NULL;
$roomNumber       = NULL;

// build array of branded hotels
$branded = [];
$brandedFile = new SplFileObject(SOURCE_BRANDED, 'r');
while ($row = $brandedFile->fgetcsv())
    if ($row && count($row) && $row[0])
        $branded[$row[0]] = array_slice($row, 1);

try {

    // set up mongodb client + collections
    $params    = ['host'    => '127.0.0.1'];
    $client    = (new Client($params))->getClient();
    $target    = $client->$targetDb->$targetCol;
    $source    = $client->$sourceDb;

    // build list of partner keys
    $partnerKeys = [];
    $cursor      = $client->$targetDb->partners->find([],['partnerKey' => 1]);
    foreach($cursor as $document)
        $partnerKeys[] = $document->partnerKey;

    // build list of ISO codes
    $isoCodes = [];
    $cursor   = $source->post_codes->aggregate([['$group' => ['_id' => '$countryCode']]]);
    foreach ($cursor as $document)
        $isoCodes[] = $document->_id;

    // empty out target collection if write flag is set
    if ($writeBson) $target->drop();

    // build sample data
    for ($x = 100; $x < ($max + 100); $x++) {

        // pick country code
        if (($x % 2) === 0) {
            $isoCode = $weightedIso[array_rand($weightedIso)];
        } else {
            $isoCode = trim($isoCodes[array_rand($isoCodes)]);
        }

        //*** build Address *********************************************************
        /*
        "Location" : {
            "streetAddress"   : <number and name of street>,
            "buildingName"    : <name of building>,
            "floor"           : <number of name of the floor>,
            "roomAptCondoFlat": <room/apt/condo/flat number>,
            "city"            : <city name>,
            "stateProvince"   : <state or province>,
            "locality"        : <other local identifying information>,
            "country"         : <ISO2 country code>,
            "postalCode"      : <postal code>,
            "latitude"        : <latitude>,
            "longitude"       : <longitude>
        },
        */

        // street address
        $streetAddr = rand(1,9999) . ' '
                 . $street1[array_rand($street1)] . ' '
                 . $street2[array_rand($street2)] . ' '
                 . $street3[array_rand($street3)];

        // build buildingName, floor, etc.
        $buildingName = (($x % 13) === 0) ? 'Building ' . strtoupper(bin2hex(random_bytes(1))) : NULL;
        $floor        = (($x % 9) === 0) ? rand(1,20) : NULL;
        $roomNumber   = (($x % 6) === 0) ? strtoupper(bin2hex(random_bytes(1))) : NULL;

        // do a count on "post_codes" documents for this $isoCode
        $count = $source->post_codes->countDocuments(['countryCode' => $isoCode]);
        if ($count == 0) continue;

        // generate a random number between 1 and count
        $goTo  = rand(1, $count);

        // iterate until number is reached
        $document = $source->post_codes->findOne(['countryCode' => $isoCode],['skip' => $goTo]);
        if (!$document) continue;

        // from document extract city, postcode, latitude and longitude
        $city      = $document->placeName;
        $postCode  = $document->postalCode;
        $latitude  = $document->latitude;
        $longitude = $document->longitude;
        $stateProv = NULL;
        $locality  = NULL;

        // will need to do a switch statement to get state/province, etc.
        switch ($isoCode) {
            case 'GB' :
                $stateProv = $document->adminName1;
                break;
            default :
                if (isset($document->adminCode1) && $document->adminCode1 && !ctype_digit($document->adminCode1)) {
                        $stateProv = $document->adminCode1;
                } else {
                    if (isset($document->adminName3) && $document->adminName3) {
                        $stateProv = $document->adminName3;
                    } elseif (isset($document->adminName2) && $document->adminName2) {
                        $stateProv = $document->adminName2;
                    } elseif (isset($document->adminName1) && $document->adminName1) {
                        $stateProv = $document->adminName1;
                    }
                }
                break;
                // do nothing
        }
        // locality
        if (isset($document->adminName2) && $document->adminName2) {
            $locality = $document->adminName2;
        } elseif (isset($document->adminName3) && $document->adminName3) {
            $locality = $document->adminName3;
        } elseif (isset($document->adminName1) && $document->adminName1) {
            $locality = $document->adminName1;
        }

        $location = [
            'streetAddress'   => $streetAddr,
            'buildingName'    => $buildingName,
            'floor'           => $floor,
            'roomAptCondoFlat'=> $roomNumber,
            'city'            => $city,
            'stateProvince'   => $stateProv,
            'locality'        => $locality,
            'country'         => $isoCode,
            'postalCode'      => $postCode,
            'latitude'        => $document->latitude,
            'longitude'       => $document->longitude
        ];

        //*** build PropertyInfo *********************************************************
        /*
        PropInfo = {
            "type"        : <propertyType>,
            "chain"       : <chain>,
            "rating"      : (int) <determined dynamically>,
            "photos"      : <stored using GridFS>,
            "facilities"  : [<facilityType>,<facilityType>,etc.],
            "description" : <string>,
            "currency"    : <currencyType>,
            "taxFee"      : <float>
        }
         */
        $brand = (($x % 3) === 0);
        if ($brand) {
            $key       = trim(array_keys($branded)[array_rand(array_keys($branded))]);
            $propName  = trim($branded[$key][array_rand($branded[$key])]);
            $propType  = (($x % 2) === 0) ? 'hotel' : 'motel';
            $brand     = $key;
        } else {
            $propType  = array_keys($propertyType)[array_rand(array_keys($propertyType))];
            $propName  = trim($name1[array_rand($name1)]) . ' ' . ucwords($propertyType[$type]);
            $brand     = NULL;
        }
        for ($z = 0; $z < rand(1,4); $z++) {
            $facilities  = $facilityType[array_rand($facilityType)];
        }
        $countryDoc = $source->iso_country_codes->findOne(['ISO2' => $isoCode]);
        if ($countryDoc) {
            $currency = $countryDoc->currencyCode;
        } else {
            $currency = 'USD';
        }
        $propInfo = [
            'type'        => $propType,
            'chain'       => $brand,
            'rating'      => (int) rand(1,5),
            'photos'      => NULL,
            'facilities'  => $facilities,
            'description' => $loremIpsum[array_rand($loremIpsum)],
            'currency'    => $currency,
            'taxFee'      => rand(0,33) * 0.01,
        ];

        //*** Build Contact ******************************************************
        // decide gender
        $gender = ((($x + rand(1,99)) % 2) == 0) ? 'M' : 'F';
        $gender = ($x % 80 == 0) ? 'X' : $gender;               // account for "other"

        // randomly pick first and last names
        $first = ($gender == 'F')
            ? $firstNamesFemale[array_rand($firstNamesFemale)]
            : $firstNamesMale[array_rand($firstNamesMale)];
        $last  = $surnames[array_rand($surnames)];
        $first = ucfirst(strtolower(trim($first)));
        $last  = ucfirst(strtolower(trim($last)));

        // username
        $username = strtolower(substr($first, 0, 1) . substr($last, 0, 7));

        // build primary email address
        $email = $username . $x . '@' . strtolower(trim($isps[array_rand($isps)])) . '.com';

        // create phone number
        $countryData = $source->iso_country_codes->findOne(['ISO2' => $isoCode]);
        $dialCode = (isset($countryData->dialingCode) && $countryData->dialingCode)
                  ? '+' . $countryData->dialingCode . '-'
                  : '';
        $phone  = $dialCode . sprintf('%d-%03d-%04d', $x, rand(0,999), rand(0,9999));

        // pick social media at random
        if ($x % 10) {
            $soc1 = '';     // no social media
        } else {
            $soc1   = $socMedia[array_rand($socMedia)];
        }

        // title
        $title = NULL;
        $suffix = NULL;
        if ($x % 3) {
            if ($gender == 'M') $title = 'Mr';
            else $title = 'Ms';
        } else {
            if (rand(0,19) === 0 ) {
                $title = 'Dr';
                $suffix = $suffixes[array_rand($suffixes)];
            }
        }

        $name = [
            'title'  => $title,
            'first'  => $first,
            'middle' => ($x % 3) ? strtoupper($alpha[rand(0,25)]) : NULL,
            'last'   => $last,
            'suffix' => $suffix,
        ];

        $contact = [
            'phone'    => $phone,
            'email'    => $email,
            'socMedia' => ($soc1) ? [$soc1 => $username . '@' . $soc1 . '.com'] : NULL,
        ];

        //*** build RoomTypes *********************************************************
        /*
        "RoomType" : {
            "roomTypeKey"  : <string>,
            "type"         : <roomType>,
            "view"         : <string>,
            "description"  : <string>,
            "beds"         : [<bedType>,<bedType>,etc.],
            "numAvailable" : <int>,
            "numBooked"    : <int>,
            "price"        : <float>
        }
        */
        $rooms = [];
        for ($y = 0; $y < rand(1, 6); $y++ ) {
            $rooms[$y]['roomType']     = $roomType[array_rand($roomType)];
            $rooms[$y]['roomLocation'] = $roomLocation[array_rand($roomLocation)];
            $rooms[$y]['price']        = (float) rand(20,1000);
            for ($z = 0; $z < rand(1, 3); $z++ )
                $rooms[$y]['bedType'][] = $bedType[array_rand($bedType)];
            $rooms[$y]['breakfast']    = $breakfast[array_rand($breakfast)];
        }

        // build data to write
        $insert = [
            'propertyKey' => $propertyInfo,
            'partnerKey'  => $partnerKeys[array_rand($partnerKeys)],
            'propName'    => $propName,
            'propInfo'    => $propInfo,
            'address'     => $location,
            'contactName' => $name,
            'contactInfo' => $contact,
            'rooms'       => [<RoomType>,<RoomType>,etc.],
            'reviews'     => [<Review>,<Review>,etc.]
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
