<?php
/** 
 * builds "sweetscomplete.customers" MongoDB collection
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
define('PASSWORD_FILE', __DIR__ . '/passwords.csv');

// init vars
$max = 1000;    // target number of entries to generate
$pwdFile = new SplFileObject(PASSWORD_FILE, 'w');

// set up mongodb client + collections
$params = ['host' => '127.0.0.1'];
$client = (new Client($params))->getClient();

// build arrays from source files
$firstNamesFemale = file(SOURCE_FIRST_NAMES_FEMALE);
$firstNamesMale   = file(SOURCE_FIRST_NAMES_MALE);
$surnames         = file(SOURCE_SURNAMES);
$isps             = file(SOURCE_ISP);
$isoCodes         = file(SOURCE_COUNTRIES);
$socMedia         = ['GO' => 'google', 'TW' => 'twitter', 'FB' => 'facebook', 'LN' => 'line', 'SK' => 'skype','LI' => 'linkedin'];
$street1          = ['Winding','Blue','Red','Green','Big','Little','Long','Short'];
$street2          = ['Bough','Tree','Bridge','Creek','River','Bend','Mountain','Hill','Ditch','Gulch','Gully','Canyon','Mound','Woods','Ridge','Stream'];
$street3          = ['Way','Street','Boulevard','Avenue','Drive','Road','Circle','Ride'];
$buildingName     = NULL;
$floor            = NULL;
$roomNumber       = NULL;

// build list of ISO codes
$isoCodes = [];
$cursor   = $client->source_data->post_codes->aggregate([['$group' => ['_id' => '$countryCode']]]);
foreach ($cursor as $document) {
    $isoCodes[] = $document->_id;
}

// empty out target collection
$target = $client->sweetscomplete->customers;
$target->drop();

// build sample data
$processed = 0;
$inserted  = 0;
for ($x = 100; $x < ($max + 100); $x++) {
    
    $document = [];

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

    // build email addresses
    $email = $username . '@' . trim($isps[array_rand($isps)]) . '.com';
    $email2 = $username . '@' . trim($isps[array_rand($isps)]) . '.net';
    
    // create password
    $password = base64_encode(random_bytes(8));
    
    // write plain text email + username + password to CSV file
    $pwdFile->fputcsv([$email,$username,$password]);
        
    // choose social media at random
    $soc = [];
    foreach ($socMedia as $key => $value) {
        if (rand(1,4) == 1) {
            $soc[$key] = ['label' => $value, 'url' => 'https://' . $value . '.com/' . $username];
        }
    }

    // generate DOB at random
    $year = date('Y') - rand(16, 89);
    $month = rand(1,12);
    $day   = ($month == 2) ? rand(1,28) : rand(1,30);
    $dob   = $year . '-' . $month . '-' . $day;
        
    // build street address
    $address = rand(1,9999) . ' ' 
             . $street1[array_rand($street1)] . ' ' 
             . $street2[array_rand($street2)] . ' ' 
             . $street3[array_rand($street3)];

    // build buildingName, floor, etc.
    $buildingName = (rand(1,10) === 1) ? 'Building ' . strtoupper(bin2hex(random_bytes(1))) : NULL;
    $floor        = (rand(1,10) === 1) ? rand(1,20) : NULL;
    $roomNumber   = (rand(1,10) === 1) ? strtoupper(bin2hex(random_bytes(1))) : NULL;

    // pick country code
    $isoCode = trim($isoCodes[array_rand($isoCodes)]);
    
    // do a count on "post_codes" documents for this $isoCode
    $count = $client->source_data->post_codes->countDocuments(['countryCode' => $isoCode]);
    if ($count == 0) continue;
    
    // generate a random number between 1 and count
    $goTo  = rand(1, $count);
    
    // iterate until number is reached
    $document = $client->source_data->post_codes->findOne(['countryCode' => $isoCode],['skip' => $goTo]);
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

    // create phone number
    $countryData = $client->source_data->iso_country_codes->findOne(['ISO2' => $isoCode]);
    $dialCode = (isset($countryData->dialingCode) && $countryData->dialingCode) 
              ? '+' . $countryData->dialingCode . '-' 
              : '';
    $phone  = $dialCode . sprintf('%d-%03d-%04d', $x, rand(0,999), rand(0,9999));
    $phone2 = $dialCode . sprintf('%d-%03d-%04d', rand(0,999), rand(0,999), rand(0,9999));
    $phone3 = $dialCode . sprintf('%d-%03d-%04d', rand(0,999), rand(0,999), rand(0,9999));

    // set up document to be inserted
    $insert['phone'] = $phone;            // unique key
    $insert['PrimaryContactInfo'] = [
        'firstName'   => $first,
        'lastName'    => $last,
        'phoneNumber' => $phone,
        'email'       => $email,
        'socialMedia' => $soc
    ];
    $insert['LoginInfo'] = [
        'userName' => $username,
        'password' => password_hash($password, PASSWORD_DEFAULT),
    ];        
    $insert['Address'] = [
        'streetAddressOfBuilding' =>  $address,
        'buildingName'            =>  $buildingName,
        'floor'                   =>  $floor,
        'roomApartmentCondoNumber'=>  $roomNumber,
        'city'                    =>  $city,
        'stateProvince'           =>  $stateProv,
        'locality'                =>  $locality,
        'country'                 =>  $isoCode,
        'postalCode'              =>  $postCode,
        'GeoSpatialInfo' =>  [
            'latitude'  =>  $latitude,
            'longitude' =>  $longitude
        ]
    ];        
    $insert['SecondaryContactInfo'] = [
        'secondaryPhoneNumbers'    => [$phone2, $phone3],
        'secondaryEmailAddresses'  => [$email2]
    ];        
    $insert['OtherInfo'] = [
        'dateOfBirth' => $dob,
        'gender'      => $gender,
    ];        

    if ($target->insertOne($insert)) {
        $inserted++;
    }
    $processed++;
}

try {
    echo $processed . ' documents processed' . PHP_EOL;
    echo $inserted  . ' documents inserted' . PHP_EOL;
} catch (Exception $e) {
    echo $e->getMessage();
}
