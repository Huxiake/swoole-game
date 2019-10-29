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

    public function disMatchPlayer($playerId)
    {
        DataCenter::delPlayerFromWaitList($playerId);
    }

    /**
     * 当玩家在没有结束游戏断开连接时执行
     * @param $playerFd
     */
    public function closeRoom($playerFd)
    {
        $playerId = DataCenter::getPlayerId($playerFd);
        $roomId   = DataCenter::getPlayerRoomId($playerId);
        $gameManager = ['manager'];
        if ( isset(DataCenter::$global['rooms'][$roomId]) ) {
            $gameManager = DataCenter::$global['rooms'][$roomId]['manager'];
            $players = $gameManager->getPlayers();
            foreach ( $players as $player => $obj ) {
                if ( $player != $playerId ) {
                    Sender::sendMessage($player, NOTICE_ESCAPE, ['msg' => '你的对手跑啦!!']);
                    DataCenter::setRangePlayer($player);
                    DataCenter::$server->task(['code' => Dispatch::DISPATCH_RANGE_CODE]);
                }
                DataCenter::delPlayerRoomId($player);
            }
            unset(DataCenter::$global['rooms'][$roomId]);
        }
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
        // 这里的bind需要注意，因为同一个连接后续的请求都会发送到前一个请求处理的进程，进程间的内存隔离使得这里需要将两个请求
        // 发送到同一个进程里面去处理。
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
            Sender::sendMessage($playerId, Sender::MSG_WAIT_PLAYER);
        } else {
            $gameManager->createPlayer($playerId, 6, 10, $playerType);
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
                    DataCenter::setRangePlayer($seekId);
                    DataCenter::$server->task(['code' => Dispatch::DISPATCH_RANGE_CODE]);
                }
                if ($player->getType() == 'hide') {
                    $hideId = $player->getId();
                    Sender::sendMessage($hideId, Sender::MSG_GAME_OVER, [
                        'loseer' => $hideId
                    ]);
                }
            }
            unset(DataCenter::$global['rooms'][$roomId]);
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

    /**
     * 创建一个定时器,在规定时间内判断游戏结果
     * @param $roomId
     * @return int
     */
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
                        DataCenter::setRangePlayer($hideId);
                        DataCenter::$server->task(['code' => Dispatch::DISPATCH_RANGE_CODE]);
                    }
                }
                unset(DataCenter::$global['rooms'][$roomId]);
            }
        });
    }

    /**
     * 发起挑战
     * @param $opponentId
     * @param $playerId
     */
    public function makeChallange($opponentId, $playerId)
    {
        // 判断opponent是否在线及是否在游戏中
        if ( !DataCenter::getOnlinePlayer($opponentId) ) {
            Sender::sendMessage($playerId, 1007, ['msg' => '对方不在线']);
            return;
        }
        if ( DataCenter::getPlayerRoomId($opponentId) ) {
            Sender::sendMessage($playerId, 1007, ['msg' => '对方正在游戏中']);
            return;
        }
        Sender::sendMessage($opponentId, 1008, ['challenger_id' => $playerId ]);
    }

    /**
     * 接收挑战  随机分配角色给挑战者和被挑战者
     * @param $challengeId
     * @param $challengedId
     */
    public function acceptChallenge($challengeId, $challengedId)
    {
        // 检测玩家是否在线
        if ( !DataCenter::getOnlinePlayer($challengeId) ) {
            Sender::sendMessage($challengedId, 1007, ['msg' => '对方掉线了!!']);
            return;
        }
        // 随机角色分配给两个玩家
        $playersArr = [$challengeId, $challengedId];
        shuffle($playersArr);
        list($challengePLayer,$challengedPLayer) = $playersArr;
        $seek_player = ['seek_player' => $challengePLayer,  'type' => 'seek'];
        $hide_player = ['hide_player' => $challengedPLayer, 'type' => 'hide'];
        $this->createRoom($seek_player, $hide_player);
    }

    /**
     * 获取地图数据
     * @param $mapData
     * @param $x
     * @param $y
     * @return array
     */
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