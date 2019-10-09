<?php
/**
 * date 2019/10/9 15:24
 * create by PHPStrom
 */
namespace App\Manager;

class TaskManager
{
    const TASK_CODE_FIND_PLAYER = 1;

    /**
     * 匹配玩家(简单匹配)
     * @return array|bool
     */
    public static function findPlayer()
    {
        if (DataCenter::getPlayerWaitListLen() >= 2) {
            $redPlayer = DataCenter::popPlayerFromWaitList();
            $bluePlayer = DataCenter::popPlayerFromWaitList();
            return [
                'red_player' => $redPlayer,
                'blue_player' => $bluePlayer,
            ];
        }
        return false;
    }
}