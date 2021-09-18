<?php

namespace Casanova\Manifold\Api\Core\Controllers;

/**
 * Class responsible for RSA keys handling
 */
class RSAController
{
    /**
     * RSA keys folder path
     *
     * @var string
     */
    protected $folderPath;
    
    /**
     * RSA keys log for lookups on case of 24+ interval access
     * 
     * @todo implement it (up to 2 keys at one time)
     * @var string
     */
    protected $logPath;
    
    /**
     * RSA keys refresh interval
     *
     * @todo implement it
     * @var int in seconds
     */
    protected $keyInterval;

    function __construct(){
        $this->folderPath = '.ssh';
        // $logPath = `${folderPath}/log.json`;
        $this->keyInterval =  1500;
        
    }

    /**
     * Return RSA keys
     * 
     * @todo implement 24hrs key rotation and look up into $logPath for log names and return them
     *
     * @return array with public and private key strings
     */
    function getKeys(){
        $pathToFile = base_path($this->folderPath);
        $publicFile = file_get_contents($pathToFile . "/rsa.public");
        $privateFile = file_get_contents($pathToFile . "/rsa.private");
        return [ "public" => $publicFile, "private" => $privateFile ];
    }

    
}
