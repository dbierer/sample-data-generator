<?php
/** 
 * builds $targetDb.purchases MongoDB collection
 * WARNING: drops the collection before generating sample data
 */
define('DATE_FORMAT', 'Y-m-d H:i:s');
define('SOURCE_SURNAMES', __DIR__ . '/surnames.txt');
define('SOURCE_FIRST_NAMES_MALE', __DIR__ . '/first_names_male.txt');
define('SOURCE_FIRST_NAMES_FEMALE', __DIR__ . '/first_names_female.txt');

require __DIR__ . '/vendor/autoload.php';
use Application\Client;

// init vars
$max        = 2000;         // target number of entries to generate
$inserted   = 0;
$processed  = 0;
$written    = 0;
$writeJs    = TRUE;    // set this TRUE to output JS file to perform inserts
$writeBson  = FALSE; // set this TRUE to directly input into MongoDB database
$sourceDb         = 'booksomething';
$targetDb         = 'booksomething';
$targetCollection = 'reservations';
$targetJs         = __DIR__ . '/' . $targetDb . '_' . $targetCollection . '_insert.js';
$firstNamesFemale = file(SOURCE_FIRST_NAMES_FEMALE);
$firstNamesMale   = file(SOURCE_FIRST_NAMES_MALE);
$surnames         = file(SOURCE_SURNAMES);
$idType           = ['P' => 'Passport', 'DL' => 'Driving License', 'ID' => 'ID Card'];
$checkout         = [11, 12, 13, 12, 14, 12];
$lengthStay       = [1, 2, 3, 4, 5, 7, 1, 2, 3, 4, 5, 7, 14, 30];
$arrTime          = range(0,23);

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
    $customers = $client->$sourceDb->user;
    $property  = $client->$sourceDb->property;

    // get max # customers
    $maxCust = $customers->countDocuments();
    // get max # properties
    $maxProp = $property->countDocuments();
    
    // build sample data
    for ($x = 0; $x < $max; $x++) {

        //*** BookingInfo ******************************************************
        $checkoutTime = $checkout[array_rand($checkout)] . ':00:00';
        $nowStr = date('Y-m-d') . ' ' . sprintf('%02d:%02d:00', $arrTime[array_rand($arrTime)], rand(0,59));
        $arrObj = new DateTime($nowStr);
        $interval = new DateInterval('P' . rand(1,666) . 'D');
        if (($x % 2) === 0) {
            $arrObj->add($interval);
            $bookingStatus = 'pending';
        } else {
            $arrObj->sub($interval);
            $bookingStatus = 'completed';
        }
        if (($x % 90) === 0) $bookingStatus = 'wish-list';
        if (($x % 70) === 0) $bookingStatus = 'cancelled';
        if ($bookingStatus == 'completed') {
            $confirmed = 'yes';
        } elseif ($bookingStatus == 'pending') {
            $confirmed = (($x % 3) === 0) ? 'yes' : 'no';
        } else {
            $confirmed = 'no';
        }
        if ($confirmed == 'yes' && (($x % 3) === 0)) {
            $prepaid = 'yes';
        } else {
            $prepaid = 'no';
        }            
        $depObj = new DateTime($arrObj->format('Y-m-d') . ' ' . $checkoutTime);
        $depObj->add(new DateInterval('P' . $lengthStay[array_rand($lengthStay)] . 'D'));
        $bookingInfo = [
            'arrivalDate'     => $arrObj->format(DATE_FORMAT),
            'departureDate'   => $depObj->format(DATE_FORMAT),
            'checkoutTime'    => $checkoutTime,
            'refundableUntil' => $arrObj->sub(new DateInterval('P' . rand(1,14) . 'D'))->format(DATE_FORMAT),
            'confirmed'       => $confirmed,
            'prepaid'         => $prepaid,
        ];
        //**********************************************************************

        
        //*** CustomerParty ******************************************************
        $customerParty = [];
        for ($y = 0; $y < rand(1, 4); $y ++) {
            
            // decide gender
            $gender = ((($x + rand(1,99)) % 2) == 0) ? 'M' : 'F';

            // randomly pick first and last names
            $first = ($gender == 'F') 
                ? $firstNamesFemale[array_rand($firstNamesFemale)]
                : $firstNamesMale[array_rand($firstNamesMale)];
            $last = $surnames[array_rand($surnames)];
            $first = ucfirst(strtolower(trim($first)));
            $last  = ucfirst(strtolower(trim($last)));
            $customerParty[] = [
                'firstName'            => $first, 
                'lastName'             => $last, 
                'gender'               => $gender,
                'age'                  => rand(1,79),
                'identificationType'   => array_keys($idType)[array_rand(array_keys($idType))],
                'identificationNumber' => strtoupper(bin2hex(random_bytes(rand(3,6)))),
            ];
        }
        //**********************************************************************

        
        //*** PrimaryContactInfo ******************************************************
        // pull customer at random
        $skipCust = rand(0, $maxCust);
        if ($skipCust == 0) {
            $custDoc = $customers->findOne([],['projection' => ['PrimaryContactInfo' => 1,'Address' => 1]]);
        } else {
            $custDoc = $customers->findOne([],['projection' => ['PrimaryContactInfo' => 1,'Address' => 1], 'skip' => $skipCust]);
        }
                
        // pull property at random
        $skipProp = rand(0, $maxProp);
        if ($skipProp == 0) {
            $propDoc = $property->findOne([],['projection' => ['MainPropuctInfo' => 1]]);
        } else {
            $propDoc = $property->findOne([],['projection' => ['PropertyInfo' => 1], 'skip' => $skipProp]);
        }
        
        $reservationKey = str_replace(['-',' '], '', substr($bookingInfo['arrivalDate'], 0, 10)) 
                        . strtoupper(substr($custDoc->PrimaryContactInfo->lastName, 0, 4));
        $insert = [
            'reservationKey'    => $reservationKey,
            'customer'          => $custDoc->PrimaryContactInfo,
            'property'          => $propDoc->PropertyInfo,
            'reservationStatus' => $bookingStatus,
            'CustomerParty'     => $customerParty,
            'BookingInfo'       => $bookingInfo
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
