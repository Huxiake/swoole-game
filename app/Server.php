<?php
/**
 * date 2019/10/8 16:03
 * create by PHPStrom
 */

require_once __DIR__ . '/../vendor/autoload.php';
use App\Manager\DataCenter;
use App\Manager\Logic;
use App\Manager\TaskManager;
use App\Manager\Sender;
use App\Model\Player;
class Server
{
    const HOST = '0.0.0.0';
    const PORT = 8501;
    const FRONT_PORT = 8502;
    const CONFIG = [
        'worker_num' => 4,
        'enable_static_handler' => true,
        'document_root' => __DIR__.'/../frontend/',
        'daemonize' => 0,
        'task_worker_num' => 4,
        'dispatch_mode' => 5
    ];
    const CLIENT_CODE_MATCH_PLAYER = 600;
    const CLIENT_CODE_BEGIN_GAME = 601;
    const CLIENT_CODE_DIRECTION = 602;
    const CLIENT_CODE_ONLINE_PLAYERS = 900;

    private $ws;
    private $logic;

    public function __construct()
    {
        $this->logic = new Logic();
        $this->ws = new \Swoole\WebSocket\Server(self::HOST, self::PORT);
        $this->ws->set(self::CONFIG);
        $this->ws->listen(self::HOST, self::FRONT_PORT, SWOOLE_SOCK_TCP);
        $this->ws->on('start', [$this, 'onStart']);
        $this->ws->on('workerStart', [$this, 'onWorkerStart']);
        $this->ws->on('open', [$this, 'onOpen']);
        $this->ws->on('message', [$this, 'onMessage']);
        $this->ws->on('close', [$this, 'onClose']);
        // http

        $this->ws->on('request', [$this, 'onRequest']);
        // task
        $this->ws->on('task', [$this, 'onTask']);
        $this->ws->on('finish', [$this, 'onFinish']);
        $this->ws->start();
    }

    public function onStart( $server_name )
    {
        DataCenter::initDataCenter();
        swoole_set_process_name( 'swoole-hide-seek' );
        echo sprintf("master start (listening on %s:%d)\n", self::HOST, self::PORT);
    }

    public function onWorkerStart( $server, $workId )
    {
        echo "server :onWorkerStart, worker_id: {$server->worker_id}\n";
        DataCenter::$server = $server;
    }

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
        $server->task(['code' => self::CLIENT_CODE_ONLINE_PLAYERS]);
    }

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
        }
    }

    public function onClose( $server, $fd )
    {
        DataCenter::log(sprintf("client %d close", $fd));
        DataCenter::delPlayerId($fd);
    }

    /*  ------------------------------        ------------------------------------   */

    public function onRequest($request, $response)
    {
        if (isset($request->get['action']) && $request->get['action'] == 'get_online_player') {
            $online_player = DataCenter::hLenOnlinePlayer();
            $response->end(json_encode(['online_player' => $online_player]));
        }
    }

    /*  -----------------------------Task异步任务---------------------------------    */
    public function onTask($server, $taskId, $srcWorkId, $data)
    {
        DataCenter::log("onTask", $data);
        $result = [];
        // 执行各种逻辑,根据投递过来的code
        switch ($data['code']) {
            case TaskManager::TASK_CODE_FIND_PLAYER: // 匹配玩家
                // 追赶者，需要匹配隐藏者
                $players = TaskManager::findPlayer($data['player_id'], $data['player_type']);
//                $players = TaskManager::findPlayer();
                if (!empty($players)) {
                    $result['data'] = $players;
                }
                // 没有return 就不会触发finish方法
                if (!empty($result)) {
                    $result['code'] = $data['code'];
                    return $result;
                }
                break;
            case self::CLIENT_CODE_ONLINE_PLAYERS: // 推送在线人数更新
                $playerIds = array_keys(DataCenter::getAllOnlinePlayers());
                foreach ( $playerIds as $player ) {
                    Sender::sendMessage($player, 9000, ['onlinePlayers' => DataCenter::hLenOnlinePlayer()]);
                }
                return true;
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

new Server();