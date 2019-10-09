<?php
/**
 * date 2019/10/9 17:46
 * create by PHPStrom
 */
namespace App\Manager;

class Sender
{
    const MSG_ROOM_ID = 1001;

    const CODE_MSG = [
        self::MSG_ROOM_ID => '房间ID'
    ];

    public function sendMessage($playerId, $code, $data = [])
    {
        $message = [
            'code' => $code,
            'msg'  => self::CODE_MSG[$code] ?? '',

        ];
        $playerFd = DataCenter::getPlayerFd($playerId);
        DataCenter::$server->push($playerFd, );
    }
}