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
        DataCenter::setPlayerInfo($playerId, $request->fd);
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
                $this->logic->startRoom($clientData['room_id'], $playerId);
                break;
        }
    }

    public function onClose( $server, $fd )
    {
        DataCenter::log(sprintf("client %d close", $fd));
        DataCenter::delPlayerId($fd);
    }

    /*  -----------------------------Task异步任务---------------------------------    */
    public function onTask($server, $taskId, $srcWorkId, $data)
    {
        DataCenter::log("onTask", $data);
        $result = [];
        // 执行各种逻辑,根据投递过来的code
        switch ($data['code']) {
            case TaskManager::TASK_CODE_FIND_PLAYER: // 匹配玩家
                $players = TaskManager::findPlayer();
                if (!empty($players)) {
                    $result['data'] = $players;
                }
                break;
        }
        // 没有return 就不会触发finish方法
        if (!empty($result)) {
            $result['code'] = $data['code'];
            return $result;
        }
    }

    public function onFinish($server, $taskId, $data)
    {
        DataCenter::log("Finish", $data);
        switch ($data['code']) {
            case TaskManager::TASK_CODE_FIND_PLAYER:
                $this->logic->createRoom($data['data']['red_player'], $data['data']['blue_player']);
                break;
        }
    }
}

new Server();