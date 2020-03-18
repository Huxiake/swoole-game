<?php

namespace App\Manager;

use App\Manager\DataCenter;
use App\Manager\Sender;

class Dispatch
{

    /**
     * 广播所有在线人数排名信息
     *
     * @desc broadcastRanger
     */
    public static function broadcastRanger()
    {
        $playerIds = array_keys(DataCenter::getAllOnlinePlayers());
        foreach ( $playerIds as $player ) {
            Sender::sendMessage($player, DISPATCH_RANGE_CODE, ['playersRange' => DataCenter::getRangePlayers()]);
        }
    }


    /**
     * 广播所有在线人数更新在线人数
     *
     * @desc broadcastOnline
     */
    public static function broadcastOnline()
    {
        $playerIds = array_keys(DataCenter::getAllOnlinePlayers());
        foreach ( $playerIds as $player ) {
            Sender::sendMessage($player, DISPATCH_ONLINE_CODE, ['onlinePlayers' => DataCenter::hLenOnlinePlayer()]);
        }
    }
}
