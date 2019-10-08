<?php
/**
 * date 2019/10/8 16:40
 * create by PHPStrom
 */
namespace App\Manager;

class DataCenter
{
    public static function log( $info, $context = [], $level = 'INFO' )
    {
        if ($context) {
            echo sprintf("[%s][%s] : %s %s\n", date('Y-m-d H:i:s'), $level, $info,
                json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            echo sprintf("[%s][%s] : %s\n", date('Y-m-d H:i:s'), $level, $info);
        }
    }
}