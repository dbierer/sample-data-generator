<?php
namespace Application;

use InfiniteIterator;
use ArrayIterator;

/**
 * Generates descriptions from an array of Lorem Ipsum sentences
 */

class LoremIpsum
{
    /**
     * Generates Lorem Ipsum descriptions
     * 
     * @param array $lorem == generated from "https://lipsum.com/" "descriptions"
     * @param int $lines == number of lines to choose from $lorem
     * @return string $ipsum
     */
    public static function generateIpsum(array $lorem, $lines)
    {
        $ipsum   = '';
        $start   = rand(1,10);
        $skip    = rand(1,4);
        $iterator = new InfiniteIterator(new ArrayIterator($lorem));
        // get to the start
        for ($x = 0; $x < $start; $x++) {
            $iterator->next();
        }
        $ipsum = trim($iterator->current());
        for ($x = 0; $x < $lines; $x++) {
            // skip lines
            for ($y = 0; $y < $skip; $y++) {
                $iterator->next();
            }
            $ipsum .= ' ' . trim($iterator->current());
        }
        return $ipsum;        
    }
}
