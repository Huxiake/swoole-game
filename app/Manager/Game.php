<?php
/**
 * date 2019/10/8 13:54
 * create by PHPStrom
 */

namespace App\Manager;
use App\Model\Map;
use App\Model\Player;

class Game
{
    private $gameMap = [];
    private $players = [];

    public function __construct()
    {
        $this->gameMap = new Map(12, 12);
    }

    public function getPlayers()
    {
        return $this->players;
    }

    public function getMapData()
    {
        return $this->gameMap->getMapData();
    }

    /**
     * 创建玩家(追赶着和躲藏者先来后到，先来的为追赶者,并且只有一个追赶者)
     * @param $playerId
     * @param $x
     * @param $y
     */
    public function createPlayer( $playerId, $x, $y )
    {
        $player = new Player($playerId, $x, $y);
        if (!empty($this->players)) {
            $player->setType(Player::PLAYER_TYPE_HIDE);
        }
        $this->players[$playerId] = $player;
    }

    /**
     * 玩家移动
     * @param $playerId
     * @param $direction
     */
    public function playerMove( $playerId, $direction )
    {
        if ($this->canMoveToDirection( $this->players[$playerId], $direction )) {
            $this->players[$playerId]->{$direction}();
        }
    }

    /**
     * 打印地图
     */
    public function printGameMap()
    {
        $font = [2 => '追', 3 => '躲'];
        $gameMap = $this->gameMap->getMapData();
        foreach ( $this->players as $player ) {
            $gameMap[$player->getX()][$player->getY()] = $player->getType()+1;
        }
        foreach ($gameMap as $line){
            foreach ($line as $row){
                if (0 == $row) {
                    echo '* ';
                } elseif (1 == $row) {
                    echo '  ';
                } else {
                    echo $font[$row];
                }
            }
            echo PHP_EOL;
        }
    }

    /**
     * 判断移动的方向是否能走 ? 如果下一步移动的方向是HIDE 或者 SEEK 怎么办
     * @param $player
     * @param $direction
     * @return bool
     */
    public function canMoveToDirection(Player $player, $direction)
    {
        $x = $player->getX();
        $y = $player->getY();

        switch ($direction) {
            case Player::UP:
                $x--;
                break;
            case Player::DOWN:
                $x++;
                break;
            case Player::LEFT:
                $y--;
                break;
            case Player::Right:
                $y++;
                break;
        }
        if ( $this->gameMap->getMapData()[$x][$y] == 0 ) {
            return false;
        }
        if ( $this->gameMap->getMapData()[$x][$y] == 3 ) {
            unset($this->players[$player->getId()]);
        }
        return true;
    }

    /**
     * 判断游戏是否结束
     * @return bool
     */
    public function isGameOver()
    {
        $isOver = false;
        $x = -1;
        $y = -1;
        $players = array_values($this->players);
        foreach ( $players as $key => $player ) {
            if (0 == $key) {
                $x = $player->getX();
                $y = $player->getY();
            } elseif ( $x == $player->getX() && $y == $player->getY() ) {
                $isOver = true;
            }
        }
        return $isOver;
    }
}