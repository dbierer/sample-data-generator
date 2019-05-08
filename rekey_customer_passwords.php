<?php

# rekey passwords

$in_file  = new SplFileObject('sweetscomplete_customers_insert.js', 'r');
$out_file = new SplFileObject('sweetscomplete_customers_insert_rekeyed.js', 'w');

while ($line = $in_file->fgets()) {
    if (strpos($line, 'password')) {
        $newHash  = password_hash('password', PASSWORD_BCRYPT);
        $line = '    "password": "' . $newHash . '",' . "\n";
    }
    if (strpos($line, 'customerKey')) echo $line;
    $out_file->fwrite($line);
}
