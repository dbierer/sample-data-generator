# sample-data-generator

## Notes
This project is mainly designed to generate sample data for a proof-of-concept MongoDB based application.
Please feel free to modify and use the code for your own purposes.
The data generated is "real world" in that it's based on existing world cities, postal codes, latitude and longitude.
The names are generated from a list of the most popular surnames taken from 2010 US Government Census data.
Likewise, the male and female names are drawn from census data.

## Running the Generator Code
* Install PHP 7+
* Install the latest MongoDB PHP Extension: http://pecl.php.net/package/mongodb
* Install the MongoDB PHP Library: https://docs.mongodb.com/php-library/current/tutorial/install-php-library/

## Files

### Tab Delimited
```
cities.txt
```
* Only lists cities with a population greater than 15,000
* Provided by GeoNames (http://www.geonames.org) under a Creative Commons Attribution 3.0 License
```
allCountriesCities.txt
```
* Postal code database
* Total of 1,264,940 cities listed
* Includes ISO2 country code, city name, latitude, longitude
* Additional identifying information such as state (for USA), county (UK), province (Canada), etc. are listed as `adminCode1`, `adminName1`, `adminCode2`, `adminName2`, etc.
* *Too large to store on github!* 
  * Need to download it from this location: http://opensource.unlikelysource.org/allCountriesCities.txt
```
wget http://opensource.unlikelysource.org/allCountriesCities.txt
```

### Comma Delimited

```
iso2_dialing_code.csv
```
* Contains 2 columns:
    * ISO2 country code
    * International dialing code
```
isp.csv
```
* Detailed information on Internet Service Providers and Telcos

### Just A Single Column
```
first_names_female.txt
```
* List of female first names
```
first_names_male.txt
```
* List of male first names
```
surnames.txt
```
* List of most popular surnames drawn from US 2010 census data
```
isp.txt
```
* A straight list of ISPs and Telcos
* Used to generate realistic email addresses

