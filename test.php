<?php

require_once __DIR__.'/vendor/autoload.php';
use App\Manager\Game;
$redId = "red_player";

$blueId = "blue_player";
// 游戏控制器
$game = new Game();
// 创建玩家
$game->createPlayer($redId, 1, 1);
$game->createPlayer($blueId, 2, 9);
// 随机移动知道游戏结束
$direction = ['up','down','left','right'];
while (1) {
    $game->playerMove($redId, $direction[rand(0,3)]);
    $game->printGameMap();
    if ( $game->isGameOver() ) {
        echo '抓到你了' . PHP_EOL;
        break;
    }
    sleep(1);
}
// 移动位置
// 打印地图
//$game->printGameMap();