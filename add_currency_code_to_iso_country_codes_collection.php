<?php
// Adds ISO currency code to "iso_country_codes" collection

require __DIR__ . '/vendor/autoload.php';
use Application\Client;

// set up mongodb client + collection
$params = ['host' => '127.0.0.1'];
$client = (new Client($params))->getClient();
$db     = $client->source_data;

// loop through iso_country_codes
$expected = 0;
$actual   = 0;
foreach ($db->iso_country_codes->find() as $doc) {
    echo "Processing: {$doc->name}\n";
    $expected++;
    // lookup currency code
    $currencyDoc = $db->iso_currency_codes->findOne(['country' => strtoupper($doc->name)]);
    if ($currencyDoc) {
        if ($db->iso_country_codes->updateOne(
            ['ISO2' => $doc->ISO2],
            [
                '$set' => ['currencyCode' => $currencyDoc->alpha_code],
                '$unset' => ['currency_code' => '']
            ])
        ) $actual++;
    }
}
echo "\nDocuments Processed: $expected\n";
echo "Documents Updated:   $actual\n";
