<?php
/**
 * Created by PhpStorm
 * User: slairmy
 * Date: 2020/3/17
 * Time: 11:19 下午
 */

namespace App\lib;

use Noodlehaus\Config;

class Env {

    /**
     *  加载配置项
     *
     * @var
     */
    private static $_config;

    /**
     *
     *
     * @desc getInstance
     */
    public static function getInstance()
    {
        if( !isset(self::$_config) ) {
            $config_arr = [];
            $config_dir = ROOT_PATH . '/config/';

            if ( !is_dir($config_dir) ) {
                echo "配置文件夹不存在";
                exit();
            }
            $dir_arr = scandir($config_dir);
            foreach ( $dir_arr as $dir ) {
                $filename = $config_dir . $dir;
                if (is_file($filename)) {
                    $file_info = pathinfo($filename);
                    if ($file_info['extension']) {
                        $config_arr[] = $filename;
                    }
                }
            }
            self::$_config = Config::load($config_arr);
        }
        return self::$_config;
    }

    /**
     * 获取配置项
     * @desc get
     * @param String $key
     * @param null $default
     * @return mixed|null
     */
    public static function get(String $key, $default = null)
    {
        $value = self::getInstance()->get($key);
        if (isset($value)) {
            return $value;
        }
        return $default;
    }

}

