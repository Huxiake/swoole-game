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
        // 玩家随机分配追赶者和躲藏者角色
        $playerType = rand(1,2);
        DataCenter::pushPlayerToWaitList($playerId,$playerType);
        // 发起一个Task尝试匹配
        DataCenter::$server->task(['code' => TaskManager::TASK_CODE_FIND_PLAYER, 'player_type' => $playerType, 'player_id' => $playerId]);
        // $swoole_server->task(['code' => xxx]);
    }

    // 创建房间
    public function createRoom($seekPlayer, $hidePlayer)
    {
        $roomId = uniqid('room_');
        $this->bindRoomWorker($seekPlayer['seek_player'], $roomId, $seekPlayer['type']);
        $this->bindRoomWorker($hidePlayer['hide_player'], $roomId, $hidePlayer['type']);
    }

    private function bindRoomWorker($playerId, $roomId, $playerType)
    {
        DataCenter::setPlayerRoomId($playerId, $roomId);
        $playerFd = DataCenter::getPlayerFd($playerId);
        DataCenter::$server->bind($playerFd, crc32($roomId));
        Sender::sendMessage($playerId, Sender::MSG_ROOM_ID, ['room_id' => $roomId, 'player_type' => $playerType]);
    }

    public function startRoom($roomId, $playerId, $playerType)
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
            $gameManager->createPlayer($playerId, 6, 1, $playerType);
            print_r($gameManager->getPlayers());
            Sender::sendMessage($playerId, Sender::MSG_WAIT_PLAYER);
        } else {
            $gameManager->createPlayer($playerId, 6, 10, $playerType);
            print_r($gameManager->getPlayers());
            Sender::sendMessage($playerId, Sender::MSG_ROOM_START);
            $this->createGameTimer($roomId); // 第二个玩家进入房间就开启一个定时器
            $this->sendGameInfo($roomId);
        }
    }

    /**
     * 玩家移动
     * @param $playerId
     * @param $direction
     */
    public function movePlayer($playerId, $direction)
    {
        // 根据玩家ID找打房间
        $roomId = DataCenter::getPlayerRoomId($playerId);
        if (isset(DataCenter::$global['rooms'][$roomId])) {
            $gameManager = DataCenter::$global['rooms'][$roomId]['manager'];
            $gameManager->playerMove($playerId, $direction);
            // 判断游戏结束
            $this->sendGameInfo($roomId);
            $this->checkGameOver($roomId);
        }
    }

    /**
     * 检查游戏是否结束
     * @param $roomId
     */
    private function checkGameOver($roomId)
    {
        $gameManager = DataCenter::$global['rooms'][$roomId]['manager'];
        if ($gameManager->isGameOver()) {
            foreach ($gameManager->getPlayers() as $player) {
                DataCenter::delPlayerRoomId($player->getId());
                if ($player->getType() == 'seek') {
                    $seekId = $player->getId();
                    Sender::sendMessage($seekId, Sender::MSG_GAME_OVER, [
                        'winner' => $seekId
                    ]);
                }
                if ($player->getType() == 'hide') {
                    $hideId = $player->getId();
                    Sender::sendMessage($hideId, Sender::MSG_GAME_OVER, [
                        'loseer' => $hideId
                    ]);
                }
            }
        }
    }

    private function sendGameInfo($roomId)
    {
        $gameManager = DataCenter::$global['rooms'][$roomId]['manager'];
        $players = $gameManager->getPlayers();
        $mapData = $gameManager->getMapData();
        // 无论如何先打印影藏者，再打印追赶者
        $seekPlayerId = 0;
        foreach (array_reverse($players) as $player) {
            if ($player->getType() == 'seek') {
                $seekPlayerId = $player->getId();
                $seekX = $player->getX();
                $seekY = $player->getY();
                continue;
            } else {
                $mapData[$player->getX()][$player->getY()] = $player->getId();
            }
        }
        $mapData[$seekX][$seekY] = $seekPlayerId;

        foreach ($players as $player) {
            $data = [
                'players' => $players,
                'map_data' => $this->getNearMap($mapData, $player->getX(), $player->getY()),
                'time_limit' => GAME_TIME_LIMIT
            ];
            Sender::sendMessage($player->getId(), Sender::MSG_GAME_INFO, $data);
        }
    }

    public function createGameTimer($roomId)
    {
        // 创建一个定时器，如果在规定时间内还没结束游戏，就默认判定躲藏者赢
        return swoole_timer_tick(GAME_TIME_LIMIT * 1000, function () use ($roomId) {
            if(isset(DataCenter::$global['rooms'][$roomId])) {
                $manager = DataCenter::$global['rooms'][$roomId]['manager'];
                $players = $manager->getPlayers();
                foreach ($players as $player) {
                    DataCenter::delPlayerRoomId($player->getId());
                    if ($player->getType() == 'seek') {
                        $seekId = $player->getId();
                        Sender::sendMessage($seekId, Sender::MSG_GAME_OVER, [
                            'loseer' => $seekId
                        ]);
                    }
                    if ($player->getType() == 'hide') {
                        $hideId = $player->getId();
                        Sender::sendMessage($hideId, Sender::MSG_GAME_OVER, [
                            'winner' => $hideId
                        ]);
                    }
                }
                unset(DataCenter::$global['rooms'][$roomId]);
            }
        });
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