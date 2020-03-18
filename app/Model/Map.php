<?php
/**
 * date 2019/10/8 13:56
 * create by PHPStorm
 */

namespace App\Model;
// 地图类提供一个地址，地图用于宽高的属性，并且一个数据保存地图数据，0表示墙，1表示非墙
class Map
{
    private $height;
    private $width;

    private $map = [
        [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        [0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0],
        [0, 1, 0, 1, 1, 1, 1, 1, 1, 1, 0, 0],
        [0, 0, 0, 0, 1, 0, 1, 1, 0, 1, 0, 0],
        [0, 1, 1, 0, 1, 1, 1, 1, 1, 1, 1, 0],
        [0, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0],
        [0, 1, 1, 1, 0, 1, 1, 1, 1, 1, 1, 0],
        [0, 1, 1, 0, 0, 1, 0, 1, 1, 1, 1, 0],
        [0, 1, 1, 0, 1, 1, 1, 1, 0, 1, 0, 0],
        [0, 1, 1, 0, 0, 1, 1, 0, 1, 0, 1, 0],
        [0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0],
        [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
    ];

    public function __construct( $height, $width )
    {
        $this->height = $height;
        $this->width  = $width;
    }

    /**
     * 获取地图数据|二维数组
     *
     * @desc getMapData
     * @return array
     */
    public function getMapData()
    {
        return $this->map;
    }
}
