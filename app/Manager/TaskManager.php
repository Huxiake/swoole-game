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
    public static function findPlayer($playerId, $playerType)
    {
        if ($playerType == 1) {
            if (DataCenter::getPlayerWaitListLen(2) < 1) {
                return false;
            }
            $seekPlayer = DataCenter::popPlayerFromWaitSeekList();
            if ( $seekPlayer != $playerId ) {
                DataCenter::lpushPlayerToWaitList($seekPlayer, 'seek');
                return false;
            }
            $hidePlayer = DataCenter::popPlayerFromWaitHideList();
        } elseif ($playerType == 2) {
            if (DataCenter::getPlayerWaitListLen(1) < 1) {
                return false;
            }
            $hidePlayer = DataCenter::popPlayerFromWaitHideList();
            if ( $hidePlayer != $playerId ) {
                DataCenter::lpushPlayerToWaitList($hidePlayer, 'hide');
                return false;
            }
            $seekPlayer = DataCenter::popPlayerFromWaitSeekList();
        }
        return [
            'seek' => ['seek_player' => $seekPlayer, 'type' => 'seek'],
            'hide' => ['hide_player' => $hidePlayer, 'type' => 'hide']
        ];
    }
}