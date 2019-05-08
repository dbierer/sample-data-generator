<?php
namespace Application;


/**
 * Builds tables from source data files
 */

use Throwable;
use Exception;
use SplFileObject;
use MongoDB\Collection;

class Build
{
    const ALLOWED_DELIMS = ",\t";
    const ERROR_DELIMS = 'ERROR: only comma (",") or tab ("\\t") allowed for delimiters';
    const ERROR_FILE   = 'ERROR: source file not found';
    const ERROR_TARGET = 'ERROR: target must be an instance of MongoDB\Collection';

    /**
     * Creates MongoDB collection from source text file
     *
     * @param MongoDB\Collection $target
     * @param string $sourceFile           // comma or tab delimited text file
     * @param string $delimiter            // "," or "\t"
     * @param string $exclusionFile        // file which will contain excluded lines
     * @param callable $fix                // callable which "fixes" any mismatch between headers and data
     * @return string $output
     */
    public static function populate(Collection $target, $sourceFile, $delimiter, $exclusionFile, $fix = NULL)
    {

        // sanitize input
        if (strpos(self::ALLOWED_DELIMS, $delimiter) === FALSE) {
            throw new Exception(self::ERROR_DELIMS);
        }
        if (!file_exists($sourceFile)) {
            throw new Exception(self::ERROR_FILE);
        }

        // init vars
        $output = '';

        // wipe out exclusion file
        if (file_exists($exclusionFile)) unlink($exclusionFile);

        // set up delimited file processing
        $delimitedFile = new SplFileObject($sourceFile, 'r');
        $extension     = $delimitedFile->getExtension();

        // empty out collection
        $target->drop();

        // iterate through file
        try {
            $inserted = 0;
            $headers  = [];
            if ($extension == 'csv') {
                $total = self::parseCsvFile($delimitedFile, $target, $delimiter, $exclusionFile, $headers, $inserted);
            } else {
                $total = self::parseTextFile($delimitedFile, $target, $delimiter, $exclusionFile, $headers, $inserted);
            }
            $output .= 'Total documents processed: ' . $total . PHP_EOL;
            $output .= 'Total documents inserted:  ' . $inserted . PHP_EOL;
        } catch (Exception $e) {
            $output .= $e->getMessage() . PHP_EOL;
        }
        return $output;
    }
    protected static function parseTextFile($delimitedFile, $target, $delimiter, $exclusionFile, &$headers, &$inserted)
    {
        $total = 0;
        $split = function ($line) use ($delimiter) { return explode($delimiter, trim($line)); };
        $headers = $split($delimitedFile->fgets());
        $headCount = count($headers);
        while ($line = $delimitedFile->fgets()) {
            $insert = $split(trim($line));
            if (count($insert) !== $headCount) {
                if ($fix) {
                    $data = $fix($headers, $insert);
                    if ($target->insertOne($data)) {
                        $inserted++;
                    }
                } else {
                    // write line to exclusion file
                    file_put_contents($exclusionFile, implode(',', $insert) . PHP_EOL, FILE_APPEND);
                }
            } else {
                try {
                    if ($target->insertOne(array_combine($headers, $insert))) {
                        $inserted++;
                    }
                } catch (Exception $e) {
                    error_log($e->getMessage());
                }
            }
            $total++;
        }
        return $total;
    }
    protected static function parseCsvFile($delimitedFile, $target, $delimiter, $exclusionFile, &$headers, &$inserted)
    {
        $total = 0;
        $headers = $delimitedFile->fgetcsv();
        $headCount = count($headers);
        while ($line = $delimitedFile->fgetcsv()) {
            if (count($line) !== $headCount) {
                // write line to exclusion file
                file_put_contents($exclusionFile, implode(',', $line) . PHP_EOL, FILE_APPEND);
            } else {
                if ($target->insertOne(array_combine($headers, $line))) {
                    $inserted++;
                }
            }
            $total++;
        }
        return $total;
    }
}
