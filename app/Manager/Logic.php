<?php
/**
 * date 2019/10/8 16:39
 * create by PHPStrom
 */
namespace App\Manager;

class Logic
{
    public function matchPlayer($playerId)
    {
        DataCenter::pushPlayerToWaitList($playerId);
        // 发起一个Task尝试匹配
        DataCenter::$server->task(['code' => TaskManager::TASK_CODE_FIND_PLAYER]);
        // $swoole_server->task(['code' => xxx]);
    }

    public function createRoom($red_player_id, $blue_player_id)
    {
        $roomId = uniqid('room_');
        $this->bindRoomWorker($red_player_id, $roomId);
        $this->bindRoomWorker($blue_player_id, $roomId);
    }

    private function bindRoomWorker($playerId, $roomId)
    {
        $playerFd = DataCenter::getPlayerFd($playerId);
        DataCenter::$server->bind($playerFd, crc32($roomId));
        DataCenter::$server->push($playerFd, $roomId);
    }
}