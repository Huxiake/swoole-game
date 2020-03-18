<?php
/**
 * Created by PhpStorm
 * User: slairmy
 * Date: 2020/3/17
 * Time: 11:49 下午
 */
namespace App\Server;

use App\Manager\DataCenter;
use App\Manager\Logic;
use App\Manager\TaskManager;
use App\Manager\Dispatch;
use App\Manager\Sender;

class Websocket{

    const CLIENT_CODE_MATCH_PLAYER  = 600;
    const CLIENT_CODE_BEGIN_GAME    = 601;
    const CLIENT_CODE_DIRECTION     = 602;

    /**
     * ws配置信息
     *
     * @var array
     */
    private $_config = [];

    /**
     * ws对象
     *
     * @var \Swoole\WebSocket\Server
     */
    private $ws;

    /**
     * logic实例
     *
     * @var Logic
     */
    private $logic;

    /**
     * 构造函数
     *
     * Websocket constructor.
     */
    public function __construct()
    {
        // 游戏逻辑
        $this->logic = new Logic();
        $this->_config = env("websock");
        if (empty($this->_config)) {
            echo "请检查websock基础配置";
            exit();
        }
        // 添加静态处理页面
        $this->_config['enable_static_handler'] = true;
        $this->_config['document_root'] = ROOT_PATH . "/frontend/";
        $this->ws = new \Swoole\WebSocket\Server(env("app.host"), env("app.port"));
        $this->ws->set($this->_config);
        $this->ws->listen(env("app.host"), env("app.front_port"), SWOOLE_SOCK_TCP);
    }

    /**
     * @desc start
     */
    public function start() :void
    {
        $this->ws->on('start', [$this, 'onStart']);
        $this->ws->on('workerStart', [$this, 'onWorkerStart']);
        $this->ws->on('open', [$this, 'onOpen']);
        $this->ws->on('message', [$this, 'onMessage']);
        $this->ws->on('close', [$this, 'onClose']);
        $this->ws->on('request', [$this, 'onRequest']);
        $this->ws->on('task', [$this, 'onTask']);
        $this->ws->on('finish', [$this, 'onFinish']);
        $this->ws->start();
    }

    /**
     * 服务开启监听事件
     *
     * @desc onStart
     * @param $server_name
     */
    public function onStart( $server_name )
    {
        DataCenter::initDataCenter();
        // mac端这个函数有错误
        //swoole_set_process_name( 'swoole-hide-seek' );
        echo sprintf("master start (listening on %s:%d)\n", env("app.host"), env("app.port"));
    }

    /**
     * worker进程启动监听事件
     *
     * @desc onWorkerStart
     * @param $server
     * @param $workId
     */
    public function onWorkerStart( $server, $workId )
    {
        echo "server :onWorkerStart, worker_id: {$server->worker_id}\n";
        DataCenter::$server = $server;
    }

    /**
     * 客户端连接监听事件
     *
     * @desc onOpen
     * @param $server
     * @param $request
     */
    public function onOpen( $server, $request )
    {
        DataCenter::log(sprintf("client open fd: %d", $request->fd));
        $playerId = $request->get['player_id'];
        if ( !empty(DataCenter::getOnlinePlayer($playerId)) ) {
            // 主动断开连接
            $server->disconnect($request->fd, 1000, "该玩家已经在线");
        } else {
            DataCenter::setPlayerInfo($playerId, $request->fd);
        }
        // 开启一个异步任务推送在线人数的更新
        $server->task(['code' => DISPATCH_ONLINE_CODE]);
        $server->task(['code' => DISPATCH_RANGE_CODE]);
    }

    /**
     * 接受客户端消息监听事件
     *
     * @desc onMessage
     * @param $server
     * @param $request
     */
    public function onMessage( $server, $request )
    {
        DataCenter::log(sprintf("client fd %d send message: %s", $request->fd, $request->data));
        // 根据当前fd获取到player_id
        $clientData = json_decode($request->data, true);
        $playerId = DataCenter::getPlayerId($request->fd);
        switch( $clientData['code'] )
        {
            case self::CLIENT_CODE_MATCH_PLAYER: // 匹配玩家时的code
                $this->logic->matchPlayer($playerId);
                break;
            case self::CLIENT_CODE_BEGIN_GAME:
                $this->logic->startRoom($clientData['room_id'], $playerId, $clientData['player_type']);
                break;
            case self::CLIENT_CODE_DIRECTION:
                $this->logic->movePlayer($playerId, $clientData['direction']);
                break;
            case DIS_MATCH_PLAYER_CODE: // 取消匹配
                $this->logic->dismatchPlayer($playerId);
                break;
            case 603: // 玩家邀请
                $opponentId = $clientData['opponent_id'];
                $this->logic->makeChallange($opponentId, $playerId);
                break;
            case 604: // 接收挑战
                // 创建房间并开始游戏
                $challengerId = $clientData['challenger_id'];
                $this->logic->acceptChallenge($challengerId, $playerId);
                break;
            case 605: // 玩家拒绝挑战
                $challengerId = $clientData['challenger_id'];
                Sender::sendMessage($challengerId, 1007, ['msg' => '对方拒绝了你的挑战']);
                break;
        }
    }

    /**
     * 客户端断开连接监听事件
     *
     * @desc onClose
     * @param $server
     * @param $fd
     */
    public function onClose( $server, $fd )
    {
        DataCenter::log(sprintf("client %d close", $fd));
        $this->logic->closeRoom($fd);
        DataCenter::delPlayerInfo($fd);
        Dispatch::broadcastOnline();
    }


    /**
     * http请求监听事件
     *
     * @desc onRequest
     * @param $request
     * @param $response
     */
    public function onRequest($request, $response)
    {
        if (isset($request->get['action']) && $request->get['action'] == 'get_online_player') {
            $online_player = DataCenter::hLenOnlinePlayer();
            $response->end(json_encode(['online_player' => $online_player]));
        }
    }

    /**
     * task 异步任务监听事件
     *
     * @desc onTask
     * @param $server
     * @param $taskId
     * @param $srcWorkId
     * @param $data
     * @return array
     */
    public function onTask($server, $taskId, $srcWorkId, $data)
    {
        DataCenter::log("onTask", $data);
        $result = [];
        // 执行各种逻辑,根据投递过来的code
        switch ($data['code']) {
            case TaskManager::TASK_CODE_FIND_PLAYER: // 匹配玩家
                $players = TaskManager::findPlayer($data['player_id'], $data['player_type']);
                if (!empty($players)) {
                    $result['data'] = $players;
                }
                // 没有return 就不会触发finish方法
                if (!empty($result)) {
                    $result['code'] = $data['code'];
                    return $result;
                }
                break;
            case DISPATCH_ONLINE_CODE: // 推送在线人数更新
                Dispatch::broadcastOnline();
                break;
            case DISPATCH_RANGE_CODE: // 实时推送排名数据
                Dispatch::broadcastRanger();
                break;
        }
    }

    // onTask 执行完回调  如果onTask返回false 则不会回调次函数
    public function onFinish($server, $taskId, $data)
    {
        DataCenter::log("Finish", $data);
        switch ($data['code']) {
            case TaskManager::TASK_CODE_FIND_PLAYER:
                $this->logic->createRoom($data['data']['seek'], $data['data']['hide']);
                break;
        }
    }
}
