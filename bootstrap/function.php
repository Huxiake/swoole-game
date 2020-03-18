<?php
/**
 * Created by PhpStorm
 * User: slairmy
 * Date: 2020/3/18
 * Time: 1:39 下午
 */

use App\lib\Env;

/**
 * @desc env 获取全局配置
 * @param String $key
 * @param null $default
 * @return string
 */
function env(String $key, $default = null)
{
    return Env::get($key, $default);
}
