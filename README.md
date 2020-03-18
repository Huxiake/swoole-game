# HideAndSeek

**基于swoole的你追我赶的网页小游戏**

# 目录结构
```
HideAndSeek
├── app
│   ├── Lib
│   │   ├── Env.php             # 基础配置
│   │   └── Redis.php           # redis单例子
│   ├── Manager
│   │   ├── DataCenter.php      # 数据中心
│   │   ├── Dispatch.php        # 广播类
│   │   ├── Game.php            # 游戏数据类
│   │   ├── Logic.php           # 游戏逻辑类
│   │   ├── Sender.php          # 向客户端发送数据统一格式类
│   │   └── TaskManager.php     # 异步task类
│   ├── Model
│   │   ├── Map.php             # 地图
│   │   └── Player.php          # 玩家
│   ├── Server
│   │   ├── Websocket.php       # ws服务
├── bootstrap
│   ├── code.php                # 全局code
│   └── function.php            # 全局function
├── config
│   └── app.json                # 配置
├── frontend
│   └── index.html
├── composer.json
├── composer.lock
├── service                     # 服务启动入口
└── vendor
    ├── autoload.php
    └── composer
```

# app 配置
```php
"host"          :   "0.0.0.0",
"port"          :   8501,
"front_port"    :   8502
```

`port` 为ws监听端口 `front_port` 为http监听端口,根据自己需求修改

# 应用启动

进入根目录
```php
php service
```
