<?php
/**
 * date 2019/10/8 20:42
 * create by PHPStrom
 */
namespace App\Lib;

class Redis
{
    protected static $config = [
        'host' => '127.0.0.1',
        'port' => 6379
    ];

    static private $instance;

    public function __construct()
    {
    }

    public static function getInstance()
    {
        if ( empty(self::$instance) ) {
            $instance = new \Redis();
            $instance->connect( self::$config['host'], self::$config['port'] );
            self::$instance = $instance;
        }
        return self::$instance;
    }

    private function __clone()
    {
        // TODO: Implement __clone() method.
    }
}