<?php
namespace Application;

use Exception;
use SplFileObject;
use DateTime;
use DateInterval;

class MakeFake
{
    const ERROR_NAME       = 'ERROR: must run "makeName()" before running "makeContact()" or "makeLoginInfo()"';
    const ERROR_CONTACT    = 'ERROR: must run "makeContact()" before running "makeLoginInfo()"';
    const ERROR_BUSINESS_NAME_UNIQUE = 'ERROR: unable to generate unique business name';
    const DEFAULT_PASSWORD = 'password';

    // source data
    public $street1       = ['Winding','Blue','Red','Green','Big','Little','Long','Short'];
    public $street2       = ['Bough','Tree','Bridge','Creek','River','Bend','Mountain','Hill','Ditch','Gulch','Gully','Canyon','Mound','Woods','Ridge','Stream'];
    public $street3       = ['Way','Street','Boulevard','Avenue','Drive','Road','Circle','Ride'];
    public $weightedIso   = ['US','CA','GB','AU','IN'];
    public $socMedia      = ['google','twitter','facebook','line','skype','linkedin'];
    public $propName1     = ['Cozy','Riverside','Lakeside','Mountain','Rose','Garden','Valley','Castle','Sleepy','Amazing','Awesome','Romantic','Secluded','Peaceful','Restful','Quiet','Tranquil','Getaway','Take a Break','Famous','Destination','Travel','Voyage','Mystic','Royal','Majestic','Sterling'];
    public $propName2     = ['Lodge','Hotel','Inn','House','Stay','Resort','Destination','Keep','Hall','Inn','Hotel','Lodge'];
    public $bedType       = ['single', 'double', 'queen', 'king'];
    public $propertyType  = ['hotel','motel','inn','guest house','hostel','resort','serviced apartment','condo','b & b','lodge'];
    public $refundable    = ['yes','no'];
    public $replicaType   = ['primary', 'secondary'];
    public $roomType      = ['standard', 'double', 'premium', 'VIP', 'family', 'suite'];
    public $roomView      = ['city', 'pool', 'river', 'lake', 'mountain', 'garden', 'pleasant', 'spectacular', 'park'];
    public $facilityType  = ['outdoor pool','indoor pool','free parking','WiFi','fitness center','business center','pharmacy','sauna','jacuzzi','buffet breakfast'];
    public $checkout      = [11, 12, 13, 12, 14, 12];
    public $lengthStay    = [1, 2, 3, 4, 5, 7, 1, 2, 3, 4, 5, 7, 14, 30];
    public $rsvStatus     = ['pending','confirmed','cancelled'];
    public $payStatus     = ['pending','confirmed','refunded'];
    public $suffixes      = ['II','III','Jr','Sr'];
    public $business1     = ['Friendly','Serious','Industrious','Fat Cats','Comfort','Accomodation','Lazy Bird','Leisure','Sleep Right','Stay Awhile'];
    public $business2     = ['Industries','Associates','Trust','Business','Partners'];
    public $business3     = ['Inc','Ltd','Company','LLC',''];

    // built in __construct
    public $alpha            = [];
    public $firstNamesMale   = [];
    public $surnames         = [];
    public $firstNamesFemale = [];
    public $isps             = [];
    public $loremIpsum       = [];
    public $branded          = [];
    public $isoCodes         = [];
    public $arrTime          = [];

    // built during processing
    public $propName         = '';
    public $businessName     = '';
    public $username         = '';
    public $soc1             = '';
    public $email            = '';
    public $phone            = '';
    public $name             = [];
    public $location         = [];
    public $contact          = [];
    public $propInfo         = [];
    public $gender           = '';
    public $customerKeys     = [];
    public $partnerKeys      = [];
    public $countryData      = NULL;
    public $otherInfo        = [];
    public $loginInfo        = [];
    public $busiNameHistory  = [];

    public function __construct($config)
    {
        $this->alpha            = range('a','z');
        $this->firstNamesMale   = file($config['SOURCE_FIRST_NAMES_MALE']);
        $this->surnames         = file($config['SOURCE_SURNAMES']);
        $this->firstNamesFemale = file($config['SOURCE_FIRST_NAMES_FEMALE']);
        $this->isoCodes         = file($config['SOURCE_COUNTRIES']);
        $this->isps             = file($config['SOURCE_ISP']);
        $this->loremIpsum       = file($config['SOURCE_LOREM_IPSUM']);
        $this->arrTime          = range(0,23);
        // build array of branded hotels
        $this->branded = [];
        $brandedFile = new SplFileObject($config['SOURCE_BRANDED'], 'r');
        while ($row = $brandedFile->fgetcsv())
            if ($row && count($row) && $row[0])
                $this->branded[$row[0]] = array_slice($row, 1);
    }

    /**
     * @param MongoDB\Collection $collection
     * @return array $customerKeys
     */
    public function buildCustomerKeys($collection)
    {
        // build list of customer keys
        $this->customerKeys = [];
        $cursor = $collection->find([],['customerKey' => 1]);
        foreach($cursor as $document)
            $this->customerKeys[] = $document->customerKey;
        return $this->customerKeys;
    }

    /**
     * @param MongoDB\Collection $collection
     * @return array $partnerKeys
     */
    public function buildPartnerKeys($collection)
    {
        // build list of partner keys
        $this->partnerKeys = [];
        $cursor = $collection->find([],['partnerKey' => 1]);
        foreach($cursor as $document)
            $this->partnerKeys[] = $document->partnerKey;
        return $this->partnerKeys;
    }

    /**
     * @param MongoDB\Collection $collection
     * @return array $partnerKeys
     */
    public function buildIsoCodes($collection)
    {
        // build list of ISO codes
        $this->isoCodes = [];
        $cursor   = $collection->aggregate([['$group' => ['_id' => '$countryCode']]]);
        foreach ($cursor as $document)
            $this->isoCodes[] = $document->_id;
        return $this->isoCodes;
    }

    /**
     * @param int $x == loop index
     * @param Mongodb\Collection $collection == data source
     * @param string $isoCode == ISO2 country code
     * @return array $location == according to the document structure shown here
     */
    public function makeAddress($x, $collection, $isoCode)
    {
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
                 . $this->street1[array_rand($this->street1)] . ' '
                 . $this->street2[array_rand($this->street2)] . ' '
                 . $this->street3[array_rand($this->street3)];

        // build buildingName, floor, etc.
        $buildingName = (($x % 13) === 0) ? 'Building ' . strtoupper(bin2hex(random_bytes(1))) : NULL;
        $floor        = (($x % 9) === 0) ? rand(1,20) : NULL;
        $roomNumber   = (($x % 6) === 0) ? strtoupper(bin2hex(random_bytes(1))) : NULL;

        // do a count on "post_codes" documents for this $isoCode
        $count = $collection->count(['countryCode' => $isoCode]);
        if ($count == 0) return FALSE;

        // generate a random number between 1 and count
        $goTo  = rand(1, $count);

        // iterate until number is reached
        $document = $collection->findOne(['countryCode' => $isoCode],['skip' => $goTo]);
        if (!$document) return FALSE;
        $this->countryData = $document;

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

        $this->location = [
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
        return $this->location;
    }
    /**
     * @param int $x == loop index
     * @param MongoDB\Collection $collection
     * @param string $isoCode == ISO2 country code
     * @return array $propInfo
     */
    public function makePropInfo($x, $collection, $isoCode)
    {
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
            $key       = trim(array_keys($this->branded)[array_rand(array_keys($this->branded))]);
            $propType  = (($x % 2) === 0) ? 'hotel' : 'motel';
            $brand     = $key;
            $this->propName  = trim($this->branded[$key][array_rand($this->branded[$key])]);
        } else {
            $propType  = array_keys($this->propertyType)[array_rand(array_keys($this->propertyType))];
            $brand     = NULL;
            $this->propName  = $this->makePropName();
        }
        for ($z = 0; $z < rand(1,4); $z++) {
            $facilities  = $this->facilityType[array_rand($this->facilityType)];
        }
        $this->countryData = $this->countryData ?? $collection->findOne(['ISO2' => $isoCode]);
        if ($this->countryData) {
            $currency = $this->countryData->currencyCode;
        } else {
            $currency = 'USD';
        }
        $this->propInfo = [
            'type'        => $this->propertyType[array_rand($this->propertyType)],
            'chain'       => $brand,
            'rating'      => (int) rand(1,5),
            'photos'      => NULL,
            'facilities'  => $facilities,
            'description' => $this->loremIpsum[array_rand($this->loremIpsum)],
            'currency'    => $currency,
            'taxFee'      => rand(0,33) * 0.01,
        ];
        return $this->propInfo;
    }
    /**
     * @param int $x == loop index
     */
    public function setGender($x)
    {
        // decide gender
        $this->gender = ((($x + rand(1,99)) % 2) == 0) ? 'M' : 'F';
        $this->gender = ($x % 80 == 0) ? 'X' : $this->gender;               // account for "other"
        return $this->gender;
    }
    /**
     * @param int $x == loop index
     * @return array $name
     */
    public function makeName($x)
    {
        // Builds this structure:
        /*
        Name = {
            "title"    : <formal titles, e.g. Mr, Ms, Dr, etc.>,
            "first"    : <first name, also referred to as "given" name>,
            "middle"   : <middle name, optional>,
            "last"     : <last name, also referred to as "surname" or "family name">,
            "suffix"   : <any additional name information included after the last name>
        }
         */
        // decide gender
        $gender = $this->setGender($x);

        // randomly pick first and last names
        $first = ($gender == 'F')
            ? $this->firstNamesFemale[array_rand($this->firstNamesFemale)]
            : $this->firstNamesMale[array_rand($this->firstNamesMale)];
        $last  = $this->surnames[array_rand($this->surnames)];
        $first = ucfirst(strtolower(trim($first)));
        $last  = ucfirst(strtolower(trim($last)));

        // title
        $title = NULL;
        $suffix = NULL;
        if ($x % 3) {
            if ($gender == 'M') $title = 'Mr';
            else $title = 'Ms';
        } else {
            if (rand(0,19) === 0 ) {
                $title = 'Dr';
                $suffix = $this->suffixes[array_rand($this->suffixes)];
            }
        }

        // create username
        $this->username = strtolower(substr($first, 0, 1) . substr($last, 0, 7));

        $this->name = [
            'title'  => $title,
            'first'  => $first,
            'middle' => ($x % 3) ? strtoupper($this->alpha[rand(0,25)]) : NULL,
            'last'   => $last,
            'suffix' => $suffix,
        ];
        return $this->name;
    }
    /**
     * NOTE: must run $this->makeName() first!
     * @param int $x == loop index
     * @param MongoDB\Collection $collection
     * @param string $isoCode
     * @param bool $isOther
     * @return array $contact
     */
    public function makeContact($x, $collection, $isoCode, $isOther = FALSE)
    {
        if (!$this->name) throw new Exception(self::ERROR_NAME);
        // Builds this structure:
        /*
        Contact = {
            "email"    : <primary email address>,
            "phone"    : <primary phone number>,
            "socMedia" : <preferred social media contact>,
        }
         */
        // username
        $this->username = strtolower(substr($this->name['first'], 0, 1) . substr($this->name['last'], 0, 7));

        // build primary email address
        $email = $this->username . $x . '@' . strtolower(trim($this->isps[array_rand($this->isps)])) . '.com';

        // create phone number
        $this->countryData = $this->countryData ?? $collection->findOne(['ISO2' => $isoCode]);
        $dialCode = (isset($this->countryData->dialingCode) && $this->countryData->dialingCode)
                  ? '+' . $this->countryData->dialingCode . '-'
                  : '';
        $phone  = $dialCode . sprintf('%d-%03d-%04d', $x, rand(0,999), rand(0,9999));

        // pick social media at random
        if ($x % 3 === 0) {
            $soc1 = '';     // no social media
        } else {
            $soc1   = $this->socMedia[array_rand($this->socMedia)];
        }
        $contact = [
            'email'    => $email,
            'phone'    => $phone,
            'socMedia' => ($soc1) ? [$soc1 => $email . '@' . $soc1 . '.com'] : [],
        ];
        if (!$isOther) {
            $this->soc1 = $soc1;
            $this->email = $email;
            $this->phone = $phone;
            $this->contact = $contact;
        }
        return $contact;
    }
    /**
     * @return array $rooms
     */
    public function makeRoomTypes()
    {
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
            $rooms[$y]['roomTypeKey']  = $this->alpha[$y] . $this->alpha[$y] . rand(10,99);
            $rooms[$y]['type']         = $this->roomType[array_rand($this->roomType)];
            $rooms[$y]['view']         = $this->roomView[array_rand($this->roomView)];
            $rooms[$y]['description']  = $this->loremIpsum[array_rand($this->loremIpsum)];
            for ($z = 0; $z < rand(1, 3); $z++ )
                $rooms[$y]['beds'][] = $this->bedType[array_rand($this->bedType)];
            $rooms[$y]['numAvailable'] = (float) rand(1,100);
            $rooms[$y]['numBooked']    = (float) rand(1,50);
            $rooms[$y]['price']        = (float) rand(40,300);
        }
        $this->rooms = $rooms;
        return $rooms;
    }
    /**
     * @param int $x == current index
     * @return array $reviews
     */
    public function makeReviews($x)
    {
        /*
        Review = {
            "customerKey" : <string>,
            "staff"       : <int 1 to 5>,
            "cleanliness" : <int 1 to 5>,
            "facilities"  : <int 1 to 5>,
            "comfort"     : <int 1 to 5>,
            "goodStuff"   : <text>,
            "badStuff"    : <text>
        }
        */
        if (!$this->customerKeys) $this->buildCustomerKeys();
        $reviews = [];
        $max = rand(0, 20);
        if ($max) {
            for ($y = 0; $y < $max; $y++ ) {
                // generate weighted total booked
                switch (TRUE) {
                    // highly rated
                    case ($x % 7 === 0) :
                        $low  = 3;
                        $high = 5;
                        break;
                    // medium rating
                    case ($x % 3 === 0) :
                        $low  = 2;
                        $high = 4;
                        break;
                    // average rating
                    default :
                        $low  = 1;
                        $high = 5;
                }
                // populate $reviews with array data
                $reviews[$y]['customerKey'] = $this->customerKeys[array_rand($this->customerKeys)];
                $reviews[$y]['staff']       = rand($low,$high);
                $reviews[$y]['cleanliness'] = rand($low,$high);
                $reviews[$y]['facilities']  = rand($low,$high);
                $reviews[$y]['comfort']     = rand($low,$high);
                // 1 out of 3 will write good stuff
                $reviews[$y]['goodStuff']   = ($x % 3 === 0) ? 'Very nice ' . $this->loremIpsum[array_rand($this->loremIpsum)] : '';
                // 1 out of 2 will write bad stuff
                $reviews[$y]['badStuff']    = ($x % 2) ? 'Horrible ' . $this->loremIpsum[array_rand($this->loremIpsum)]  : '';
            }
        }
        $this->reviews = $reviews;
        return $reviews;
    }
    public function makePropName()
    {
        if (!$this->propName) {
            $this->propName =  $this->propName1[array_rand($this->propName1)]
                           . ' '
                           . $this->propName2[array_rand($this->propName2)];
        }
        return $this->propName;
    }
    public function makeBusinessName()
    {
        $status = 0;
        for ($x = 100; $x > 0; $x--) {
            $name = $this->buildBusinessName();
            if (!in_array($name, $this->busiNameHistory)) {
                $status = 1;
                break;
            }
        }
        if (!$status)
            throw new Exception(self::ERROR_BUSINESS_NAME_UNIQUE);
        $this->businessName = $name;
        $this->busiNameHistory[] = $name;
        return $name;
    }
    protected function buildBusinessName()
    {
        return $this->business1[array_rand($this->business1)]
               . ' '
               . $this->business2[array_rand($this->business2)]
               . ' '
               . $this->business3[array_rand($this->business3)];
    }
    public function makeOtherInfo($x)
    {
        // generate DOB at random
        $year = date('Y') - rand(16, 89);
        $month = rand(1,12);
        $day   = ($month == 2) ? rand(1,28) : rand(1,30);
        $dob   = sprintf('%4d-%02d-%02d', $year, $month, $day);

        if (!$this->gender) $this->setGender($x);
        $this->otherInfo = [
            'gender'      => $this->gender,
            'dateOfBirth' => $dob
        ];
        return $this->otherInfo;
    }
    public function makeLoginInfo($password)
    {
        if (!$this->username) throw new Exception(self::ERROR_NAME);
        if (!$this->contact) throw new Exception(self::ERROR_CONTACT);
        if ($password == 'RANDOM') {
            $password = base64_encode(random_bytes(6));
        } else {
            $password = self::DEFAULT_PASSWORD;
        }
        $soc1 = NULL;
        if ($this->contact['socMedia']) $soc1 = key($this->contact['socMedia']);
        $this->loginInfo = [
            'username' => $this->username,
            'oauth2'   => ($soc1) ? $this->username . '@' . $soc1 . '.com' : NULL,
            'password' => password_hash($password, PASSWORD_BCRYPT),
        ];
        return $this->loginInfo;
   }
   public function makeBookingInfo($x)
   {
        //*** BookingInfo ******************************************************
        $checkoutTime = $this->checkout[array_rand($this->checkout)] . ':00:00';
        $nowStr = date('Y-m-d') . ' ' . sprintf('%02d:%02d:00', $this->arrTime[array_rand($this->arrTime)], rand(0,59));
        $arrObj = new DateTime($nowStr);
        $interval = new DateInterval('P' . rand(1,666) . 'D');
        if (($x % 2) === 0) {
            $arrObj->add($interval);
            $bookingStatus = 'pending';
        } else {
            $arrObj->sub($interval);
            $bookingStatus = 'confirmed';
        }
        if (($x % 70) === 0) $bookingStatus = 'cancelled';
        switch ($bookingStatus) {
            case 'pending' :
                if ($x % 3) {
                    $paymentStatus = 'pending';
                } else {
                    $paymentStatus = 'confirmed';
                }
                break;
            case 'confirmed' :
                $paymentStatus = 'confirmed';
                break;
            case 'cancelled' :
                if ($x % 3) {
                    $paymentStatus = 'pending';
                } else {
                    $paymentStatus = 'refunded';
                }
                break;
            default :
                $paymentStatus = 'pending';
        }
        $depObj = new DateTime($arrObj->format('Y-m-d') . ' ' . $checkoutTime);
        $depObj->add(new DateInterval('P' . $this->lengthStay[array_rand($this->lengthStay)] . 'D'));
        $this->bookingInfo = [
            'arrivalDate'       => $arrObj->format(DATE_FORMAT),
            'departureDate'     => $depObj->format(DATE_FORMAT),
            'checkoutTime'      => $checkoutTime,
            'refundableUntil'   => $arrObj->sub(new DateInterval('P' . rand(1,14) . 'D'))->format(DATE_FORMAT),
            'reservationStatus' => $bookingStatus,
            'paymentStatus'     => $paymentStatus,
        ];
        return $this->bookingInfo;
    }
}
