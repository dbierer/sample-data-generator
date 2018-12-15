<?php
namespace Application;

use Exception;
use SplFileObject;
class Delimited
{
    const ERROR_FILE = 'ERROR: file does not exist';
    protected $delimitedFile;
    protected $headers;
    protected $numHeaders;
    protected $delimiter;
    public function __construct($fn, $delimiter = ',')
    {
        if (!file_exists($fn)) throw new Exception(self::ERROR_FILE);
        $this->delimitedFile = new SplFileObject($fn, 'r');
        $this->delimiter = $delimiter;
    }
    public function getHeaders()
    {
        if (!$this->headers) {
            $this->delimitedFile->rewind();
            $this->headers = explode($this->delimiter, $this->delimitedFile->fgets());
            $this->numHeaders = count($this->headers);
        }
        return $this->headers;
    }
    public function getIterator()
    {
        $this->delimitedFile->rewind();
        while ($line = $this->delimitedFile->fgets()) {
            yield explode($this->delimiter, $line);
        }
    }
    public function getIteratorWithHeaders()
    {
        yield $this->getHeaders();
        while ($line = $this->delimitedFile->fgets())
            echo __METHOD__ . ':' . var_export($line, TRUE);
            yield explode($this->delimiter, $line);
    }
}

