<?php
/**
 * date 2019/10/8 16:39
 * create by PHPStrom
 */
namespace App\Manager;

use App\Model\Player;

class Logic
{
    const PLAYER_DISPLAY_LEN = 2;

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
        DataCenter::setPlayerRoomId($playerId, $roomId);
        $playerFd = DataCenter::getPlayerFd($playerId);
        DataCenter::$server->bind($playerFd, crc32($roomId));
        Sender::sendMessage($playerId, Sender::MSG_ROOM_ID, ['room_id' => $roomId]);
    }

    public function startRoom($roomId, $playerId)
    {
        if (!isset(DataCenter::$global['rooms'][$roomId])) {
            DataCenter::$global['rooms'][$roomId] = [
                'id' => $roomId,
                'manager' => new Game()
            ];
        }

        $gameManager = DataCenter::$global['rooms'][$roomId]['manager'];
        if ( count($gameManager->getPlayers()) < 1 ) {
            // 第一个玩家
            $gameManager->createPlayer($playerId, 6, 1);
            Sender::sendMessage($playerId, Sender::MSG_WAIT_PLAYER);
        } else {
            // 第二个玩家为蓝方
//            $redPlayerId = $gameManager->getPlayers()[0];
//            Sender::sendMessage($redPlayerId, Sender::MSG_ROOM_START);
            $gameManager->createPlayer($playerId, 6, 10);
            Sender::sendMessage($playerId, Sender::MSG_ROOM_START);
            $this->sendGameInfo($roomId);
        }
    }

    public function movePlayer($playerId, $direction)
    {
        // 根据玩家ID找打房间
        $roomId = DataCenter::getPlayerRoomId($playerId);
        if (isset(DataCenter::$global['rooms'][$roomId])) {
            $gameManager = DataCenter::$global['rooms'][$roomId]['manager'];
            $gameManager->playerMove($playerId, $direction);
            // 判断游戏结束
            $this->checkGameOver($roomId);
            $this->sendGameInfo($roomId);
        }
    }

    private function checkGameOver($roomId)
    {
        $gameManager = DataCenter::$global['rooms'][$roomId]['manager'];
        if ($gameManager->isGameOver()) {
            foreach ($gameManager->getPlayers() as $player) {
                DataCenter::delPlayerRoomId($player->getId());
                if ($player->getType() == Player::PLAYER_TYPE_SEEK) {
                    $seekId = $player->getId();
                }
                if ($player->getType() == Player::PLAYER_TYPE_HIDE) {
                    $hideId = $player->getId();
                }
            }
            Sender::sendMessage($seekId, Sender::MSG_GAME_OVER, json_encode([
                'winner' => $seekId
            ]));
            Sender::sendMessage($hideId, Sender::MSG_GAME_OVER, json_encode([
                'loseer' => $hideId
            ]));
        }
    }

    private function sendGameInfo($roomId)
    {
        $gameManager = DataCenter::$global['rooms'][$roomId]['manager'];
        $players = $gameManager->getPlayers();
        $mapData = $gameManager->getMapData();
        // 无论如何先打印影藏者，再打印追赶者
        foreach (array_reverse($players) as $player) {
            $mapData[$player->getX()][$player->getY()] = $player->getId();
        }

        foreach ($players as $player) {
            $data = [
                'players' => $players,
                'map_data' => $this->getNearMap($mapData, $player->getX(), $player->getY())
            ];
            Sender::sendMessage($player->getId(), Sender::MSG_GAME_INFO, $data);
        }
    }

    public function getNearMap($mapData, $x, $y)
    {
        $l_view = ($x - self::PLAYER_DISPLAY_LEN) < 0 ? 0 : $x - self::PLAYER_DISPLAY_LEN;
        $r_view = ($x + self::PLAYER_DISPLAY_LEN) > 11 ? 11 : $x + self::PLAYER_DISPLAY_LEN;

        $u_view = ($y - self::PLAYER_DISPLAY_LEN) < 0 ? 0 : $y - self::PLAYER_DISPLAY_LEN;
        $d_view = ($y + self::PLAYER_DISPLAY_LEN) > 11 ? 11 : $y + self::PLAYER_DISPLAY_LEN;
        $nearMapData = [];
        for ($id_x = $l_view; $id_x <= $r_view; $id_x++ ) {
            $temp = [];
            for ($id_y = $u_view; $id_y <= $d_view; $id_y++) {
                $temp[] = $mapData[$id_x][$id_y];
            }
            $nearMapData[] = $temp;
        }
        return $nearMapData;
    }
}