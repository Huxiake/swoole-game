<?php
/**
 * date 2019/10/8 16:03
 * create by PHPStrom
 */

require_once __DIR__ . '/../vendor/autoload.php';
use App\Manager\DataCenter;
class Server
{
    const HOST = '0.0.0.0';
    const PORT = 8501;
    const FRONT_PORT = 8502;
    const CONFIG = [
        'worker_num' => 4,
        'enable_static_handler' => true,
        'document_root' => '/home/vagrant/code/HideAndSeek/frontend',
        'daemonize' => 0
    ];

    private $ws;

    public function __construct()
    {
        $this->ws = new \Swoole\WebSocket\Server(self::HOST, self::PORT);
        $this->ws->set(self::CONFIG);
        $this->ws->listen(self::HOST, self::FRONT_PORT, SWOOLE_SOCK_TCP);
        $this->ws->on('start', [$this, 'onStart']);
        $this->ws->on('workerStart', [$this, 'onWorkerStart']);
        $this->ws->on('open', [$this, 'onOpen']);
        $this->ws->on('message', [$this, 'onMessage']);
        $this->ws->on('close', [$this, 'onClose']);
        $this->ws->start();
    }

    public function onStart( $server_name )
    {
        swoole_set_process_name( 'swoole-hide-seek' );
        echo sprintf("master start (listening on %s:%d)\n", self::HOST, self::PORT);
    }

    public function onWorkerStart( $server, $workId )
    {
        echo "server :onWorkerStart, worker_id: {$server->worker_id}\n";
    }

    public function onOpen( $server, $request )
    {
        DataCenter::log(sprintf("client open fd: %d", $request->fd));
    }

    public function onMessage( $server, $request )
    {
        $client_fd = $request->fd;
        DataCenter::log(sprintf("client fd %d send message: %s", $request->fd, $request->data));
        $server->push($client_fd, '同意连接');
    }

    public function onClose( $server, $fd )
    {
        DataCenter::log(sprintf("client %d close", $fd));
    }
}

new Server();