<?php
/**
 * date 2019/10/8 16:40
 * create by PHPStrom
 */
namespace App\Manager;

use App\Lib\Redis;

class DataCenter
{
    public static $global;
    public static $server;
    const PREFIX_KEY = 'game';

    public static function log( $info, $context = [], $level = 'INFO' )
    {
        if ($context) {
            echo sprintf("[%s][%s] : %s %s\n", date('Y-m-d H:i:s'), $level, $info,
                json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            echo sprintf("[%s][%s] : %s\n", date('Y-m-d H:i:s'), $level, $info);
        }
    }

    public static function initDataCenter()
    {
        $key = self::PREFIX_KEY . ":player_wait_list";
        self::redis()->del($key);

        $key = self::PREFIX_KEY . ':player_id*';
        $player_id_keys = self::redis()->keys($key);
        foreach ($player_id_keys as $val) {
            self::redis()->del($val);
        }

        $key = self::PREFIX_KEY . ':player_fd*';
        $player_fd_keys = self::redis()->keys($key);
        foreach ($player_fd_keys as $val) {
            self::redis()->del($val);
        }

    }

    /**
     * 推送玩家到匹配队列
     * @param $playerId
     */
    public static function pushPlayerToWaitList($playerId)
    {
        $key = self::PREFIX_KEY . ":player_wait_list";
        self::redis()->lPush($key, $playerId);
    }

    public static function popPlayerFromWaitList()
    {
        $key = self::PREFIX_KEY . ":player_wait_list";
        return self::redis()->rPop($key);
    }

    /**
     * 获取匹配队列人数
     * @return int
     */
    public static function getPlayerWaitListLen()
    {
        $key = self::PREFIX_KEY . ":player_wait_list";
        return self::redis()->lLen($key);
    }

    /**
     * 设置玩家客户端fd 对应 玩家ID
     * @param $playerId
     * @param $fd
     */
    public static function setPlayerId($playerId, $fd)
    {
        $key = self::PREFIX_KEY . ':player_id:' . $fd;
        self::redis()->set($key, $playerId);
    }

    /**
     * 根据客户端fd 获取玩家ID
     * @param $fd
     * @return bool|string
     */
    public static function getPlayerId($fd)
    {
        $key = self::PREFIX_KEY . ':player_id:' . $fd;
        return self::redis()->get($key);
    }

    /**
     * 删除玩家id
     * @param $fd
     */
    public static function delPlayerId($fd)
    {
        $key = self::PREFIX_KEY . ':player_id:' . $fd;
        self::redis()->del($key);
    }

    /**
     * 设置玩家玩家ID对应客户端fd
     * @param $playerId
     * @param $fd
     */
    public static function setPlayerFd($playerId, $fd)
    {
        $key = self::PREFIX_KEY . ':player_fd:' . $playerId;
        self::redis()->set($key, $fd);
    }

    /**
     * 根据玩家ID获取客户端fd
     * @param $playerId
     * @return bool|string
     */
    public static function getPlayerFd($playerId)
    {
        $key = self::PREFIX_KEY . ':player_fd:' . $playerId;
        return self::redis()->get($key);
    }

    /**
     * 删除玩家fd
     * @param $playerId
     */
    public static function delPlayerFd($playerId)
    {
        $key = self::PREFIX_KEY . ':player_fd:' . $playerId;
        self::redis()->del($key);
    }

    /**
     * 存储玩家客户端信息和id信息
     * @param $playerId
     * @param $fd
     */
    public static function setPlayerInfo($playerId, $fd)
    {
        self::setPlayerFd($playerId, $fd);
        self::setPlayerId($playerId, $fd);
    }

    /**
     * 从redis删除玩家
     * @param $fd
     */
    public static function delPlayerInfo($fd)
    {
        $playerId = self::getPlayerId($fd);
        self::delPlayerFd($playerId);
        self::delPlayerId($fd);
    }

    /**
     * 获取redis单例对象
     * @return \Redis
     */
    public static function redis()
    {
        return Redis::getInstance();
    }
}