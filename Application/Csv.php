<?php
namespace Application;

use Exception;
use SplFileObject;
class Csv
{
    const ERROR_FILE = 'ERROR: file does not exist';
    protected $csvFile;
    protected $headers;
    protected $numHeaders;
    protected $delimiter;
    public function __construct($fn, $delimiter = ',')
    {
        if (!file_exists($fn)) throw new Exception(self::ERROR_FILE);
        $this->csvFile = new SplFileObject($fn, 'r');
        $this->delimiter = $delimiter;
    }
    public function getHeaders()
    {
        if (!$this->headers) {
            $this->csvFile->rewind();
            $this->headers = $this->csvFile->fgetcsv($this->delimiter);
            $this->numHeaders = count($this->headers);
        }
        return $this->headers;
    }
    public function getIterator()
    {
        $this->csvFile->rewind();
        while ($line = $this->csvFile->fgetcsv($this->delimiter)) {
            if (is_array($line)) yield $line;
        }
    }
    public function getIteratorWithHeaders()
    {
        yield $this->getHeaders();
        while ($line = $this->csvFile->fgetcsv($this->delimiter))
            if ($this->numHeaders == count($line)) 
                yield $line;
    }
}

