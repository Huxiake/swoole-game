<?php
/**
 * date 2019/10/8 20:42
 * create by PHPStorm
 */
namespace App\Lib;

class Redis
{
    /**
     * 单例
     *
     * @var
     */
    static private $instance;

    public function __construct()
    {

    }

    /**
     * 获取实例
     *
     * @desc getInstance
     * @return \Redis
     */
    public static function getInstance()
    {
        if ( empty(self::$instance) ) {
            $instance = new \Redis();
            $instance->connect( env("redis.host"), env("redis.port") );
            if (!empty(env("redis.auth"))) {
                $instance->auth(env("redis.auth"));
            }
            $instance->select(env("redis.db"));
            self::$instance = $instance;
        }
        return self::$instance;
    }


    private function __clone()
    {
        // TODO: Implement __clone() method.
    }

}
