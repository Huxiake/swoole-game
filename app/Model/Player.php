<?php
/**
 * date 2019/10/8 13:55
 * create by PHPStorm
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

    /**
     * 设置玩家类型(hide or seek)
     *
     * @desc setType
     * @param $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * 获取玩家类型(hide or seek)
     *
     * @desc getType
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * 获取玩家X轴坐标
     *
     * @desc getX
     * @return mixed
     */
    public function getX()
    {
        return $this->x;
    }

    /**
     * 获取玩家Y轴坐标
     *
     * @desc getY
     * @return mixed
     */
    public function getY()
    {
        return $this->y;
    }

    /**
     * 获取玩家id
     *
     * @desc getId
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     *  x,y坐标系
     *
     *   —— —— —— —— —— —— —— ——> y
     *  |
     *  |
     *  |
     *  |
     *  |
     *  V
     *  x
     */

    /**
     * 上移
     *
     * @desc up
     */
    public function up()
    {
        $this->x--;
    }

    /**
     * 下移
     *
     * @desc up
     */
    public function down()
    {
        $this->x++;
    }

    /**
     * 左移
     *
     * @desc up
     */
    public function left()
    {
        $this->y--;
    }

    /**
     * 右移
     *
     * @desc up
     */
    public function right()
    {
        $this->y++;
    }
}
