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

// init vars
$max       = 500;       // target number of entries to generate
$writeJs   = TRUE;      // set this TRUE to output JS file to perform inserts
$writeBson = FALSE;     // set this TRUE to directly input into MongoDB database
$sourceDb  = 'source_data';
$targetDb  = 'booksomething';
$targetCol = 'property';
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
$weightedIso      = ['US','CA','GB','AU','IN'];
$socMedia         = ['GO' => 'google', 'TW' => 'twitter', 'FB' => 'facebook', 'LN' => 'line', 'SK' => 'skype','LI' => 'linkedin'];
$name1            = ['Cozy','Riverside','Lakeside','Mountain','Rose','Garden','Valley','Castle','Sleepy','Amazing','Awesome','Romantic','Secluded','Peaceful','Restful','Quiet','Tranquil','Getaway','Take a Break','Famous','Destination','Travel','Voyage'];
$street1          = ['Winding','Blue','Red','Green','Big','Little','Long','Short'];
$street2          = ['Bough','Tree','Bridge','Creek','River','Bend','Mountain','Hill','Ditch','Gulch','Gully','Canyon','Mound','Woods','Ridge','Stream'];
$street3          = ['Way','Street','Boulevard','Avenue','Drive','Road','Circle','Ride'];
$bedType          = ['single', 'double', 'queen', 'king'];
$breakfast        = ['included', 'extra'];
$propertyType     = ['H' =>'Hotel', 'M' => 'Motel', 'SAPT' => 'Serviced Apartments', 'APT' => 'Apartments', 'C' => 'Condo', 'BB' => 'Bed and Breakfast', 'HS' => 'Hostel', 'GH' => 'Guest House', 'I' => 'Inn', 'L' => 'Lodge', 'R' => 'Resort'];
$refundable       = ['yes','no'];
$replicaType      = ['primary', 'secondary'];
$roomType         = ['standard', 'double', 'premium', 'VIP', 'family', 'suite'];
$roomLocation     = ['city view', 'poolside', 'riverside', 'lakeside', 'penthouse', 'mountain view'];
$socialMedia      = ['GO' => 'google', 'TW' => 'twitter', 'FB' => 'facebook', 'LN' => 'line', 'SK' => 'skype','LI' => 'linkedin'];
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
    $params = ['host' => '127.0.0.1'];
    $client = (new Client($params))->getClient();
    $target = $client->$targetDb->$targetCol;
    $source = $client->$sourceDb;

    // build list of ISO codes
    $isoCodes = [];
    $cursor   = $source->post_codes->aggregate([['$group' => ['_id' => '$countryCode']]]);
    foreach ($cursor as $document) {
        $isoCodes[] = $document->_id;
    }
        
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
    
        //*** build PropertyInfo *********************************************************
        /*
        "PropertyInfo" : {
            "propertyName"  : <string>,
            "propertyType"  : <common::propertyType>,
            "propertyBrand" : <string>,
            "numberOfRooms" : <int>,
        },
         */        
        $brand = (($x % 3) === 0) ? FALSE : TRUE;
        if ($brand) {
            $key   = trim(array_keys($branded)[array_rand(array_keys($branded))]);
            $name  = trim($branded[$key][array_rand($branded[$key])]);
            $type  = (($x % 2) === 0) ? 'hotel' : 'motel';
            $brand = $key;
        } else {
            $type  = array_keys($propertyType)[array_rand(array_keys($propertyType))];
            $name  = trim($name1[array_rand($name1)]) . ' ' . ucwords($propertyType[$type]);
            $brand = '';
        }
        $propertyInfo = [
            'propertyName'  => $name,
            'propertyType'  => $type,
            'propertyBrand' => $brand,
            'numberOfRooms' => rand(10,5000)
        ];
        
        //*** build PrimaryContactInfo *********************************************************
        /*
        "PrimaryContactInfo" : {
            "firstName"   : <string>,
            "lastName"    : <string>,
            "phoneNumber" : <string>,
            "email"       : <string>,
            "socialMedia" : [ "key" : {  "label" => <string>, "url" => <string> } ]
        },
        */

        // decide gender
        $gender = ((($x + rand(1,99)) % 2) == 0) ? 'M' : 'F';

        // randomly pick first and last names
        $first = ($gender == 'F') 
            ? $firstNamesFemale[array_rand($firstNamesFemale)]
            : $firstNamesMale[array_rand($firstNamesMale)];
        $last = $surnames[array_rand($surnames)];
        $first = ucfirst(strtolower(trim($first)));
        $last  = ucfirst(strtolower(trim($last)));
            
        // username
        $username = strtolower(substr($first, 0, 1) . substr($last, 0, 7));

        // build email address
        $email = $username . '@' . trim($isps[array_rand($isps)]) . '.com';
        
        // create phone number
        $countryData = $source->iso_country_codes->findOne(['ISO2' => $isoCode]);
        $dialCode = (isset($countryData->dialingCode) && $countryData->dialingCode) 
                  ? '+' . $countryData->dialingCode . '-' 
                  : '';
        $phone  = $dialCode . sprintf('%d-%03d-%04d', $x, rand(0,999), rand(0,9999));

        // choose social media at random
        $soc = [];
        foreach ($socMedia as $key => $value) {
            if (rand(1,4) == 1) {
                $soc[$key] = ['label' => $value, 'url' => 'https://' . $value . '.com/' . $username];
            }
        }
        
        $primaryContactInfo = [
            'firstName'   => $first,
            'lastName'    => $last,
            'phoneNumber' => $phone,
            'email'       => $email,
            'socialMedia' => $soc
        ];
        
        //*** build Address *********************************************************
        /*
        "Address" : {
            "streetAddressOfBuilding" : <string>,
            "buildingName"            : <string>,
            "floor"                   : <string>,
            "roomApartmentCondoNumber": <string>,
            "city"                    : <string>,
            "stateProvince"           : <string>,
            "country"                 : <ISO3 chosen from common::country>
            "postalCode"              : <string>,
            "GeoSpatialInfo" : {
                "Latitude"  : <string>,
                "Longitude" : <string>
            }
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

        $address = [
            'streetAddressOfBuilding' => $streetAddr,
            'buildingName'            => $buildingName,
            'floor'                   => $floor,
            'roomApartmentCondoNumber'=> $roomNumber,
            'city'                    => $city,
            'stateProvince'           => $stateProv,
            'locality'                => $locality,
            'country'                 => $isoCode,
            'postalCode'              => $postCode,
            'GeoSpatialInfo' => [
                'Latitude'  => $document->latitude,
                'Longitude' => $document->longitude
            ]
        ];

        //*** build RoomInfo *********************************************************
        /*
        "RoomInfo" : {
            "roomType"        : <common::roomType>,
            "roomLocation"    : <common::roomLocation>,
            "price"           : <float>,
            "bedType"         : [<common::bedType>],
            "breakfast"       : <common::breakfast>,
            "refundable       : <common::refundable>,
            "discount"        : <float>
        }
        */
        $roomInfo = [];
        for ($y = 0; $y < rand(1, 4); $y++ ) {
            $roomInfo[$y]['roomType']     = $roomType[array_rand($roomType)];
            $roomInfo[$y]['roomLocation'] = $roomLocation[array_rand($roomLocation)];
            $roomInfo[$y]['price']        = (float) rand(20,1000);
            for ($z = 0; $z < rand(1, 3); $z++ )
                $roomInfo[$y]['bedType'][] = $bedType[array_rand($bedType)];
            $roomInfo[$y]['breakfast']    = $breakfast[array_rand($breakfast)];
        }

        // build data to write
        $insert = [
            'PropertyInfo'       => $propertyInfo,
            'PrimaryContactInfo' => $primaryContactInfo,
            'Address'            => $address,
            'RoomInfo'           => $roomInfo
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
