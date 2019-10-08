<?php
/**
 * date 2019/10/8 13:55
 * create by PHPStrom
 */

namespace App\Model;

class Player
{
    const UP    = 'up';
    const LEFT  = 'left';
    const Right = 'right';
    const DOWN  = 'down';

    const PLAYER_TYPE_SEEK = 1;
    const PLAYER_TYPE_HIDE = 2;

    private $id;
    private $type = self::PLAYER_TYPE_SEEK;
    private $x; // 游戏中的x坐标位置
    private $y; // 游戏中的y坐标位置

    public function __construct( $id, $x, $y )
    {
        $this->id = $id;
        $this->x  = $x;
        $this->y  = $y;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getX()
    {
        return $this->x;
    }

    public function getY()
    {
        return $this->y;
    }

    public function getId()
    {
        return $this->id;
    }

    public function up()
    {
        $this->x--;
    }

    public function down()
    {
        $this->x++;
    }

    public function left()
    {
        $this->y--;
    }

    public function right()
    {
        $this->y++;
    }
}