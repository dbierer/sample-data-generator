<?php
/**
 * builds $targetDb.$targetCol MongoDB collection
 * WARNING: drops the collection before generating sample data
 * stores password hashed BCRYPT
 * all passwords are "password";  if you want random, set $password = 'RANDOM'
 */
require __DIR__ . '/vendor/autoload.php';
use Application\Client;

// source files
define('SOURCE_ISP', __DIR__ . '/isp.txt');
define('SOURCE_COUNTRIES', __DIR__ . '/iso_codes.txt');
define('SOURCE_SURNAMES', __DIR__ . '/surnames.txt');
define('SOURCE_FIRST_NAMES_MALE', __DIR__ . '/first_names_male.txt');
define('SOURCE_FIRST_NAMES_FEMALE', __DIR__ . '/first_names_female.txt');

// init vars
$max       = 100;           // target number of entries to generate
$writeJs   = TRUE;          // set this TRUE to output JS file to perform inserts
$writeBson = FALSE;         // set this TRUE to directly input into MongoDB database
$password  = 'password';    // set this to "RANDOM" if you want random passwords generated
$sourceDb  = 'source_data';
$targetDb  = 'booksomeplace';
$targetCol = 'partners';
$targetJs  = __DIR__ . '/' . $targetDb . '_' . $targetCol . '_insert.js';
$pwdFile   = new SplFileObject($targetDb . '_' . $targetCol . '_passwords.csv', 'w');
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
$firstNamesFemale = file(SOURCE_FIRST_NAMES_FEMALE);
$firstNamesMale   = file(SOURCE_FIRST_NAMES_MALE);
$surnames         = file(SOURCE_SURNAMES);
$isps             = file(SOURCE_ISP);
$isoCodes         = file(SOURCE_COUNTRIES);
$alpha            = range('a','z');
$suffixes         = ['MD','DDS','PhD','BS','MSD','LSD'];
$weightedIso      = ['US','CA','GB','AU','IN'];
$socMedia         = ['google','twitter','facebook','line','skype','linkedin'];
$street1          = ['Winding','Blue','Red','Green','Big','Little','Long','Short'];
$street2          = ['Bough','Tree','Bridge','Creek','River','Bend','Mountain','Hill','Ditch','Gulch','Gully','Canyon','Mound','Woods','Ridge','Stream'];
$street3          = ['Way','Street','Boulevard','Avenue','Drive','Road','Circle','Ride'];
$business1        = ['Friendly','Serious','Industrious','Fat Cats'];
$business2        = ['Industries','Associates','Trust','Business'];
$business3        = ['Inc','Ltd','Company','LLC'];
$buildingName     = NULL;
$floor            = NULL;
$roomNumber       = NULL;

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
        $isoCodes[] = trim($document->_id);
    }

    // empty out target collection if write flag is set
    if ($writeBson) $target->drop();

    // build sample data
    for ($x = 100; $x < ($max + 100); $x++) {

        //************************************************************************
        // pick country code
        if (($x % 2) === 0) {
            $isoCode = $weightedIso[array_rand($weightedIso)];
        } else {
            $isoCode = $isoCodes[array_rand($isoCodes)];
        }
        //************************************************************************

        //*** Build Address ************************************************
        // build street address
        $streetAddr = rand(1,9999) . ' '
                 . $street1[array_rand($street1)] . ' '
                 . $street2[array_rand($street2)] . ' '
                 . $street3[array_rand($street3)];

        // build buildingName, floor, etc.
        $buildingName = (rand(1,10) === 1) ? 'Building ' . strtoupper(bin2hex(random_bytes(1))) : NULL;
        $floor        = (rand(1,10) === 1) ? rand(1,20) : NULL;
        $roomNumber   = (rand(1,10) === 1) ? strtoupper(bin2hex(random_bytes(1))) : NULL;

        // do a count on 'post_codes' documents for this $isoCode
        $count = $source->post_codes->count(['countryCode' => $isoCode]);
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
        //************************************************************************


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

        //************************************************************************


        //*** Build SecondaryContactInfo ************************************************
        // create secondary phone numbers
        $phone2 = [];
        $email2 = [];
        for ($y = 0; $y < rand(0,3); $y++)
            $email2[] = $username . '@' . strtolower(trim($isps[array_rand($isps)])) . '.net';
        for ($y = 0; $y < rand(0,3); $y++)
            $phone2[] = $dialCode . sprintf('%d-%03d-%04d', rand(0,999), rand(0,999), rand(0,9999));
        // choose social media at random
        $soc = [];
        if ($soc1) {
            foreach ($socMedia as $value) {
                if (rand(1,4) == 1) {
                    $soc[$value] = $username . '@' . $value . '.com';
                }
            }
        }

        $otherContact = [
            'emails'       => $email2,
            'phoneNumbers' => $phone2,
            'socMedias'    => $soc
        ];
        //************************************************************************


        //*** Build OtherInfo ************************************************
        // generate DOB at random
        $year = date('Y') - rand(16, 89);
        $month = rand(1,12);
        $day   = ($month == 2) ? rand(1,28) : rand(1,30);
        $dob   = sprintf('%4d-%02d-%02d', $year, $month, $day);

        $otherInfo = [
            'gender'      => $gender,
            'dateOfBirth' => $dob
        ];
        //************************************************************************


        //*** Build LoginInfo ************************************************
        // NOTE: all passwords are "password" unless "RANDOM" is selected
        $pwd = 'password';
        if ($password == 'RANDOM') {
            $pwd = base64_encode(random_bytes(6));
        }
        $login = [
            'username' => $username,
            'oauth2'   => ($soc1) ? $username . '@' . $soc1 . '.com' : NULL,
            'password' => password_hash($pwd, PASSWORD_BCRYPT),
        ];
        // write plain text email + username + password to CSV file
        $pwdFile->fputcsv([$email,$username,$pwd]);

        //************************************************************************


        //*** Build Stand Alone Fields ************************************************
        $businessName = $business1[array_rand($business1)]
                      . ' ' . $business2[array_rand($business2)]
                      . ' ' . $business3[array_rand($business3)];
        //************************************************************************


        //************************************************************************
        // set up document to be inserted
        $custKey = strtoupper(substr($first, 0, 4) . substr($last, 0, 4)) . substr($phone, -4);

        // Customers
        /*
        $targetCol = 'customers';
        $insert = [
            'customerKey'   => $custKey,
            'name'         => $name,
            'address'      => $address,
            'contact'      => $contact,
            'login'        => $login,
            'otherContact' => $otherContact,
            'otherInfo'    => $otherInfo,
            'login'        => $login
        ];
        */

        // Partners
        $targetCol = 'partners';
        $insert = [
            'partnerKey'   => $custKey,
            'businessName' => $businessName,
            'revenueSplit' => rand(1,50) * 0.01,
            'acctBalance'  => rand(0,99999999) * 0.01,
            'name'         => $name,
            'address'      => $address,
            'contact'      => $contact,
            'login'        => $login,
            'otherContact' => $otherContact,
            'otherInfo'    => $otherInfo,
            'login'        => $login
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

// here is the target data structure:
/*
    "partnerKey"   : <string>,
    "businessName" : <string>,
    "revenueSplit" : <float>,
    "acctBalance"  : <float>,
    "name"         : <Name>,
    "address"      : <Location>,
    "contact"      : <Contact>,
    "otherContact" : <OtherContact>,
    "otherInfo"    : <OtherInfo>,
    "login"        : <LoginInfo>
*/
