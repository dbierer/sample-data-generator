<?php
/**
 * builds $targetDb.$targetCol MongoDB collection
 * WARNING: drops the collection before generating sample data
 * stores password hashed BCRYPT
 * all passwords are "password";  if you want random, set $password = 'RANDOM'
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
$max       = 100;           // target number of entries to generate
$writeJs   = TRUE;          // set this TRUE to output JS file to perform inserts
$writeBson = FALSE;         // set this TRUE to directly input into MongoDB database
$password  = 'password';    // set this to "RANDOM" if you want random passwords generated
$sourceDb  = 'source_data';
$targetDb  = 'booksomeplace';
$targetCol = 'partners';
$targetJs  = __DIR__ . '/' . $targetDb . '_' . $targetCol . '_insert.js';
$outputJs = 'conn = new Mongo();' . PHP_EOL
          . 'db = conn.getDB("' . $targetDb . '");' . PHP_EOL
          . 'db.' . $targetCol . '.drop();' . PHP_EOL;
$openJs   = 'db.' . $targetCol . '.insertOne(' . PHP_EOL;
$closeJs  = ');' . PHP_EOL;
$pwdFile   = new SplFileObject($targetDb . '_' . $targetCol . '_passwords.csv', 'w');
$processed = 0;
$inserted  = 0;
$written   = 0;

// set up javascript
if ($writeJs) {
    $jsFile = new SplFileObject($targetJs, 'w');
    $jsFile->fwrite($outputJs);
    echo $outputJs;
}

try {

    // set up mongodb client + collections
    $params = ['host' => '127.0.0.1'];
    $client = (new Client($params))->getClient();
    $target = $client->$targetDb->$targetCol;
    $source = $client->$sourceDb;

    // build list of ISO codes
    $isoCodes = $makeFake->buildIsoCodes($source->post_codes);
    if (!$isoCodes) throw new Exception('ERROR: ISO codes');

    // empty out target collection if write flag is set
    if ($writeBson) $target->drop();

    // build sample data
    for ($x = 100; $x < ($max + 100); $x++) {

        //*** Country Code ***********************************************************
        if (($x % 2) === 0) {
            $isoCode = $makeFake->weightedIso[array_rand($makeFake->weightedIso)];
        } else {
            $isoCode = trim($isoCodes[array_rand($isoCodes)]);
        }

        //*** Build Address *********************************************************
        $location = $makeFake->makeAddress($x, $source->post_codes, $isoCode);
        if (!$location) continue;

        //*** Build Name ******************************************************
        $name = $makeFake->makeName($x);

        //*** Build Contact ******************************************************
        $contact = $makeFake->makeContact($x, $source->iso_country_codes, $isoCode);

        //*** Build SecondaryContactInfo ************************************************
        $stop = rand(0, 3);
        $otherContact = [];
        for ($i = 0; $i < $stop; $i++) {
            $temp = $makeFake->makeContact($x, $source->iso_country_codes, $isoCode, TRUE);
            $otherContact['emails'][] = $temp['email'];
            $otherContact['phoneNumbers'][] = $temp['phone'];
            if ($temp['socMedia']) {
                $otherContact['socMedias'][] = $temp['socMedia'];
            }
        }

        //*** Build OtherInfo ************************************************
        $otherInfo = $makeFake->makeOtherInfo($x);

        //*** Build LoginInfo ************************************************
        if ($password == 'RANDOM') {
            $password = base64_encode(random_bytes(6));
        }

        // write plain text email + username + password to CSV file
        // NOTE: all passwords are "password"
        $pwdFile->fputcsv([$makeFake->email,$makeFake->username,$password]);

        $login = [
            'username' => $makeFake->username,
            'oauth2'   => ($makeFake->soc1) ? $makeFake->username . '@' . $makeFake->soc1 . '.com' : NULL,
            'password' => password_hash($password, PASSWORD_BCRYPT),
        ];
        //************************************************************************

        //*** Build Stand Alone Fields ************************************************
        $businessName = $makeFake->makeBusinessName();
        //************************************************************************


        //************************************************************************
        // set up document to be inserted
        $partKey = strtoupper(
            substr(str_replace(' ', '' , $businessName), 0, 4)
            . substr($makeFake->username, 0, 4)
            . substr($contact['phone'], -4)
        );

        // Partners
        $targetCol = 'partners';
        $insert = [
            'partnerKey'   => $partKey,
            'businessName' => $businessName,
            'revenueSplit' => rand(1,50) * 0.01,
            'acctBalance'  => rand(0,99999999) * 0.01,
            'name'         => $name,
            'address'      => $location,
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
