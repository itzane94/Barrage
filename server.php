<?php
/**
 * author itzane
 * Class myswoole
 */
class myswoole{
    private $ws;   //websocket连接句柄
    private $red;  //redis连接句柄
    public function __construct() {
        $this->myredis();      // 连接redis
        $this->mywebsocket(); // 开启websocket服务器
    }
    //websocket 服务端
    public function mywebsocket(){
        //创建websocket服务器对象，监听0.0.0.0:9502端口
        $this->ws = new swoole_websocket_server("0.0.0.0", 9502);
        //初始化swoole服务
        $this->ws->set(array(
            'daemonize'   => 0, //是否作为守护进程,此配置一般配合log_file使用
            'max_request' => 1000,
            'dispatch_mode' => 2,
            'debug_mode' => 1,
            'log_file' => 'swoole.log',
            // 心跳检测的设置，自动踢掉掉线的fd
            'heartbeat_idle_time' => 600,
            'heartbeat_check_interval' => 60,
        ));
        //监听WebSocket连接开启事件
        $this->ws->on('WorkerStart', function($ws , $worker_id){
            // 在Worker进程开启时绑定定时器
            echo "【date：".date("Y-m-d H:i:s",time())."】".$worker_id ." onWorkerStart... \n";
        });
        //监听连接打开时间
        $this->ws->on('open', function($ws, $request){
            echo "【date：".date("Y-m-d H:i:s",time())."】client ".$request->fd." has established...\n";
            $msg = $this->red->lrange(date("Y-m-d",time()),0,-1);
            foreach ($msg as $data){
                $this->ws->push($request->fd,json_encode((object)$data));
                usleep(500000);
            }
        });
        //监听WebSocket消息事件
        $this->ws->on('message', function($ws, $frame){
            echo "【data：".date("Y-m-d H:i:s",time())."】 Message: {$frame->data} \n";
            $this->red->lpush(date("Y-m-d",time()),$frame->data);
            foreach($this->ws->connections as $id){
                $this->ws->push($id, json_encode((object)$frame->data));
            }
        });
        //监听WebSocket连接关闭事件
        $this->ws->on('close', function($ws, $fd){
            echo "【date：".date("Y-m-d H:i:s",time())."】client ".$fd." has established...\n";
        });
        //开启服务
        $this->ws->start();
    }
    //启动redis服务
    public function myredis(){
        $this->red = new Redis();
        $this->red->connect('127.0.0.1','6379');
        $this->red->select(5);
        echo "【date：".date("Y-m-d H:i:s",time())."】redis service has started...\n";

    }
}
$myswoole = new myswoole;
