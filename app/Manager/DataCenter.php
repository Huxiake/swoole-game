<?php
/**
 * date 2019/10/8 16:40
 * create by PHPStrom
 */
namespace App\Manager;

use App\Lib\Redis;
use App\Model\Player;
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
        $player_id_table        = self::PREFIX_KEY . ':player_id';
        self::redis()->del($player_id_table);

        $player_fd_table        = self::PREFIX_KEY . ':player_fd';
        self::redis()->del($player_fd_table);

        $player_room_table      = self::PREFIX_KEY . ':room';
        self::redis()->del($player_room_table);

        $player_online_table    = self::PREFIX_KEY . ':online';
        self::redis()->del($player_online_table);

        $player_seek_wait_table = self::PREFIX_KEY . ':player_wait_seek_list';
        self::redis()->del($player_seek_wait_table);

        $player_hide_wait_table = self::PREFIX_KEY . ':player_wait_hide_list';
        self::redis()->del($player_hide_wait_table);

        $player_range_table     = self::PREFIX_KEY . ':range';
        self::redis()->del($player_range_table);
    }

    /**
     * 推送玩家到匹配队列
     * @param $playerId
     */
    public static function pushPlayerToWaitList($playerId, $playerType = 0)
    {
        if ($playerType == Player::PLAYER_TYPE_SEEK) {
            $key = self::PREFIX_KEY . ":player_wait_seek_list";
        } elseif ($playerType == Player::PLAYER_TYPE_HIDE) {
            $key = self::PREFIX_KEY . ":player_wait_hide_list";
        }
        self::redis()->sAdd($key, $playerId);
    }

    /**
     * 随机从追赶者等待队列中弹出一个玩家
     * @return int
     */
    public static function popPlayerFromWaitSeekList()
    {
        $key = self::PREFIX_KEY . ":player_wait_seek_list";
        return self::redis()->sPop($key);
    }

    /**
     * 随机从隐藏者等待队列中弹出一个玩家
     * @return int
     */
    public static function popPlayerFromWaitHideList()
    {
        $key = self::PREFIX_KEY . ":player_wait_hide_list";
        return self::redis()->sPop($key);
    }

    /**
     * 将玩家重匹配队列中删除
     * @return int
     */
    public static function delPlayerFromWaitList($playerId)
    {
        if (self::redis()->sIsMember(self::PREFIX_KEY . ":player_wait_seek_list", $playerId)) {
            self::redis()->sRem(self::PREFIX_KEY . ":player_wait_seek_list", $playerId);
        }
        if (self::redis()->sIsMember(self::PREFIX_KEY . ":player_wait_hide_list", $playerId)) {
            self::redis()->sRem(self::PREFIX_KEY . ":player_wait_hide_list", $playerId);
        }
    }

    /**
     * 获取匹配队列人数
     * @return int
     */
    public static function getPlayerWaitListLen($playerType)
    {
        if ($playerType == Player::PLAYER_TYPE_SEEK) {
            $key = self::PREFIX_KEY . ":player_wait_seek_list";
        } elseif ($playerType == Player::PLAYER_TYPE_HIDE) {
            $key = self::PREFIX_KEY . ":player_wait_hide_list";
        }
        return self::redis()->sCard($key);
    }

    /*-------------------------- 基础数据对应 ------------------------------------*/
    /**
     * 设置玩家客户端fd 对应 玩家ID
     * @param $playerId
     * @param $fd
     */
    public static function setPlayerId($playerId, $fd)
    {
        $key   = self::PREFIX_KEY . ':player_fd';
        $field = self::PREFIX_KEY . ':player_id:' . $fd;
        self::redis()->hSet($key, $field, $playerId);
    }

    /**
     * 根据客户端fd 获取玩家ID
     * @param $fd
     * @return bool|string
     */
    public static function getPlayerId($fd)
    {
        $key   = self::PREFIX_KEY . ':player_fd';
        $field = self::PREFIX_KEY . ':player_id:' . $fd;
        return self::redis()->hGet($key, $field);
    }

    /**
     * 删除玩家id
     * @param $fd
     */
    public static function delPlayerId($fd)
    {
        $key   = self::PREFIX_KEY . ':player_fd';
        $field = self::PREFIX_KEY . ':player_id:' . $fd;
        self::redis()->hDel($key, $field);
    }

    /**
     * 设置玩家玩家ID对应客户端fd
     * @param $playerId
     * @param $fd
     */
    public static function setPlayerFd($playerId, $fd)
    {
        $key   = self::PREFIX_KEY . ':player_id';
        $field = self::PREFIX_KEY . ':player_fd:' . $playerId;
        self::redis()->hSet($key, $field, $fd);
    }

    /**
     * 根据玩家ID获取客户端fd
     * @param $playerId
     * @return bool|string
     */
    public static function getPlayerFd($playerId)
    {
        $key   = self::PREFIX_KEY . ':player_id';
        $field = self::PREFIX_KEY . ':player_fd:' . $playerId;
        return self::redis()->hGet($key, $field);
    }

    /**
     * 删除玩家fd
     * @param $playerId
     */
    public static function delPlayerFd($playerId)
    {
        $key   = self::PREFIX_KEY . ':player_id';
        $field = self::PREFIX_KEY . ':player_fd:' . $playerId;
        self::redis()->hDel($key, $field);
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
        self::setOnlinePlayer($playerId);
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
        self::delOnlinePlayerId($playerId);
        self::delPlayerFromWaitList($playerId);
    }

    /**
     * 获取redis单例对象
     * @return \Redis
     */
    public static function redis()
    {
        return Redis::getInstance();
    }

    /* -----------------------------  将玩家与房间绑定  ------------------------------------ */
    public static function setPlayerRoomId($playerId, $roomId)
    {
        $key   = self::PREFIX_KEY . ':room';
        $field = self::PREFIX_KEY . ':room_id:' . $playerId;
        self::redis()->hSet($key, $field, $roomId);
    }

    public static function getPlayerRoomId($playerId)
    {
        $key   = self::PREFIX_KEY . ':room';
        $field = self::PREFIX_KEY . ':room_id:' . $playerId;
        return self::redis()->hGet($key, $field);
    }

    public static function delPlayerRoomId($playerId)
    {
        $key   = self::PREFIX_KEY . ':room';
        $field = self::PREFIX_KEY . ':room_id:' . $playerId;
        self::redis()->hDel($key, $field);
    }

    /*-----------------------Hash 操作---------------------------------*/

    public static function setOnlinePlayer($playerId)
    {
        $key = self::PREFIX_KEY . ':online';
        self::redis()->hSet($key, $playerId, 1);
    }

    public static function getOnlinePlayer($playerId)
    {
        $key = self::PREFIX_KEY . ':online';
        return self::redis()->hGet($key, $playerId);
    }

    public static function getAllOnlinePlayers()
    {
        $key = self::PREFIX_KEY . ':online';
        return self::redis()->hGetAll($key);
    }

    public static function delOnlinePlayerId($playerId)
    {
        $key = self::PREFIX_KEY . ':online';
        self::redis()->hDel($key, $playerId);
    }

    public static function hLenOnlinePlayer()
    {
        $key = self::PREFIX_KEY . ':online';
        return self::redis()->hLen($key);
    }
    /*-----------------排名------------------------------*/

    public static function setRangePlayer($playerId)
    {
        $key = self::PREFIX_KEY . ':range';
        self::redis()->zIncrBy($key, 1, $playerId);
    }

    public static function getRangePlayers()
    {
        $key = self::PREFIX_KEY . ':range';
        return self::redis()->zRevRange($key, 0, 1000, true);
    }

}