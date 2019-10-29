<?php
namespace App\Manager;

use App\Manager\DataCenter;
use App\Manager\Sender;

/**
 * date 2019/10/29 11:14
 * create by PHPStrom
 */
class Dispatch
{
    const DISPATCH_RANGE_CODE  = 10001;
    const DISPATCH_ONLINE_CODE = 10002;

    /**
     * 广播所有在线人数排名信息
     */
    public static function broadcastRanger()
    {
        $playerIds = array_keys(DataCenter::getAllOnlinePlayers());
        foreach ( $playerIds as $player ) {
            Sender::sendMessage($player, self::DISPATCH_RANGE_CODE, ['playersRange' => DataCenter::getRangePlayers()]);
        }
    }

    /**
     * 广播所有在线人数更新在线人数
     */
    public static function broadcastOnline()
    {
        $playerIds = array_keys(DataCenter::getAllOnlinePlayers());
        foreach ( $playerIds as $player ) {
            Sender::sendMessage($player, self::DISPATCH_ONLINE_CODE, ['onlinePlayers' => DataCenter::hLenOnlinePlayer()]);
        }
    }
}