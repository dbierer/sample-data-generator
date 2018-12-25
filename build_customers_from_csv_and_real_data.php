<?php
/** 
 * builds $targetDb.$targetCol MongoDB collection
 * WARNING: drops the collection before generating sample data
 * stores password hashed BCRYPT
 * writes plain text email + username + password to CSV file PASSWORD_FILE
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
$max       = 600;         // target number of entries to generate
$writeJs   = TRUE;    // set this TRUE to output JS file to perform inserts
$writeBson = FALSE; // set this TRUE to directly input into MongoDB database
$sourceDb  = 'source_data';
$targetDb  = 'booksomething';
$targetCol = 'user';
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
$weightedIso      = ['US','CA','GB','AU','IN'];
$socMedia         = ['GO' => 'google', 'TW' => 'twitter', 'FB' => 'facebook', 'LN' => 'line', 'SK' => 'skype','LI' => 'linkedin'];
$street1          = ['Winding','Blue','Red','Green','Big','Little','Long','Short'];
$street2          = ['Bough','Tree','Bridge','Creek','River','Bend','Mountain','Hill','Ditch','Gulch','Gully','Canyon','Mound','Woods','Ridge','Stream'];
$street3          = ['Way','Street','Boulevard','Avenue','Drive','Road','Circle','Ride'];
$business1        = ['Friendly','Serious','Industrious','Fat Cats'];
$business2        = ['Industries','Associates','Trust','Business'];
$business3        = ['Inc','Ltd','Company','LLC'];
$type             = ['customer','partner','admin'];
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
            'postalCode'              => $postCode
        ];
        //************************************************************************


        //*** Build PrimaryContactInfo ************************************************
        // decide gender
        $gender = ((($x + rand(1,99)) % 2) == 0) ? 'M' : 'F';

        // randomly pick first and last names
        $first = ($gender == 'F') 
            ? $firstNamesFemale[array_rand($firstNamesFemale)]
            : $firstNamesMale[array_rand($firstNamesMale)];
        $last  = $surnames[array_rand($surnames)];
        $first = ucfirst(strtolower(trim($first)));
        $last  = ucfirst(strtolower(trim($last)));

        // username
        $username = strtolower(substr($first, 0, 1) . substr($last, 0, 7));

        // build email addresses
        $email = $username . $x . '@' . trim($isps[array_rand($isps)]) . '.com';

        // create phone number
        $countryData = $source->iso_country_codes->findOne(['ISO2' => $isoCode]);
        $dialCode = (isset($countryData->dialingCode) && $countryData->dialingCode) 
                  ? '+' . $countryData->dialingCode . '-' 
                  : '';
        $phone  = $dialCode . sprintf('%d-%03d-%04d', $x, rand(0,999), rand(0,9999));

        $primaryContactInfo = [
            'firstName'   => $first,
            'lastName'    => $last,
            'phoneNumber' => $phone,
            'email'       => $email,
            'GeoSpatialInfo' => [
                'latitude'  => $document->latitude,
                'longitude' => $document->longitude
            ]
        ];
        //************************************************************************
        

        //*** Build SecondaryContactInfo ************************************************
        // create secondary phone numbers
        $phone2 = [];
        $email2 = [];
        for ($y = 0; $y < rand(0,3); $y++)
            $email2[] = $username . '@' . trim($isps[array_rand($isps)]) . '.net';    
        for ($y = 0; $y < rand(0,3); $y++)
            $phone2[] = $dialCode . sprintf('%d-%03d-%04d', rand(0,999), rand(0,999), rand(0,9999));        
        // choose social media at random
        $soc = [];
        foreach ($socMedia as $key => $value) {
            if (rand(1,4) == 1) {
                $soc[$key] = ['label' => $value, 'url' => 'https://' . $value . '.com/' . $username];
            }
        }
        $secondaryContactInfo = [
            'secondaryPhoneNumbers'   => $phone2,
            'secondaryEmailAddresses' => $email2,
            'socialMedia'             => $soc,
        ];
        //************************************************************************


        //*** Build OtherInfo ************************************************
        // generate DOB at random
        $year = date('Y') - rand(16, 89);
        $month = rand(1,12);
        $day   = ($month == 2) ? rand(1,28) : rand(1,30);
        $dob   = $year . '-' . $month . '-' . $day;
        $otherInfo = [
            'gender' => $gender,
            'dateOfBirth' => $dob
        ];        
        //************************************************************************


        //*** Build LoginInfo ************************************************
        // create password
        $password = base64_encode(random_bytes(8));        
        // write plain text email + username + password to CSV file
        $pwdFile->fputcsv([$email,$username,$password]);        
        $loginInfo = [
            'username' => $username, 
            'password' => password_hash($password)
        ];
        //************************************************************************
        

        //*** Build Stand Alone Fields ************************************************
        $businessName = '';
        if ($x == 1) {
            $userType = 'admin';
        } elseif (($x % 50) === 0) {
            $userType = 'partner';
            $businessName = $business1[array_rand($business1)]
                          . ' ' . $business2[array_rand($business2)]
                          . ' ' . $business3[array_rand($business3)];
        } else {
            $userType = 'customer';
        }        
        //************************************************************************


        //************************************************************************
        // set up document to be inserted
        $insert = [
            // stand alone fields
            'email'        => $email,            // unique key
            'userType'     => $userType,
            'businessName' => $businessName,
            'favorites'    => [],
            // objects
            'PrimaryContactInfo'   => $primaryContactInfo,
            'SecondaryContactInfo' => $secondaryContactInfo,
            'OtherInfo'            => $otherInfo,
            'LoginInfo'            => $loginInfo,
            'Address'              => $address,
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
