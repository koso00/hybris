<?php
namespace App\Service;

use React\EventLoop\Factory;
use WyriHaximus\React\ChildProcess\Messenger\Factory as MessengerFactory;
use WyriHaximus\React\ChildProcess\Messenger\Messages\Factory as MessageFactory;
use WyriHaximus\React\ChildProcess\Messenger\Messages\Payload;
use WyriHaximus\React\ChildProcess\Messenger\Messenger;
use Clue\React\Stdio\Stdio;
use Evenement\EventEmitter;


class InstagramAsync extends EventEmitter {

    private $container;
    private $messenger;
    private $promiseQueue = array();
    
    public function __construct($container){
        $this->container = $container;
    
        MessengerFactory::parentFromClass(\App\Service\InstagramAsyncWorker::class, $container->get('loop'))->then(\Closure::bind(function (Messenger $messenger) {

            $this->messenger = $messenger;

            $messenger->on('message',\Closure::bind( function (Payload $payload){
                if (isset($this->promiseQueue[$payload["type"]])){
                    $this->promiseQueue[$payload["type"]]->resolve(json_decode(json_encode($payload["response"])));
                }else{
                    switch ($payload["type"]) {
                        case 'thread-created':
                        case 'thread-updated':
                        case 'thread-notify':
                        case 'thread-seen':
                        case 'thread-activity':
                        case 'thread-item-created':
                        case 'thread-item-updated':
                        case 'thread-item-removed':
                        case 'client-context-ack':
                        case 'unseen-count-update':
                        case 'presence':
                        case 'error':
                            $this->emit($payload["type"],[json_decode(json_encode($payload["response"]))]);
                            break;
                        default:
                            break;
                    }
                }
            },$this));
        
            $messenger->on('error', function ($e) {
                echo $e, PHP_EOL;
            });
        },$this),
        function($e){
            echo "error", PHP_EOL;
        });
    }

    public function stop(){
        $this->messenger->softTerminate();
    }

    public function login($username,$password){
        $deferred = new \React\Promise\Deferred();
        $this->promiseQueue["login"] = $deferred;
        $promise = $deferred->promise();
        
        $this->messenger->message(MessageFactory::message([
            'type' => 'login',
            'username' => $username,
            'password' => $password
        ]));

        return $promise;
    }

    public function getInbox($cursor = null){
        $this->messenger->message(MessageFactory::message([
            'type' => 'inbox',
            'cursor' => $cursor,
        ]));
        $deferred = new \React\Promise\Deferred();
        $this->promiseQueue["inbox"] = $deferred;
        $promise = $deferred->promise();
        return $promise;
    }

    public function getThread($threadId,$cursor = null){
        $this->messenger->message(MessageFactory::message([
            'type' => 'thread',
            'threadId' => $threadId,
            'cursor' => $cursor,
        ]));
        $deferred = new \React\Promise\Deferred();
        $this->promiseQueue["thread"] = $deferred;
        $promise = $deferred->promise();
        return $promise;
    }

    public function sendText($threadId,$text){
        $this->messenger->message(MessageFactory::message([
            'type' => 'sendText',
            'threadId' => $threadId,
            'text' => $text,
        ]));
        $deferred = new \React\Promise\Deferred();
        $this->promiseQueue["sendText"] = $deferred;
        $promise = $deferred->promise();
        return $promise;
    }

    public function markDirectItemSeen($threadId,$threadItemId){
        $this->messenger->message(MessageFactory::message([
            'type' => 'markDirectItemSeen',
            'threadId' => $threadId,
            'threadItemId' => $threadItemId,
        ]));
        $deferred = new \React\Promise\Deferred();
        $this->promiseQueue["markDirectItemSeen"] = $deferred;
        $promise = $deferred->promise();
        return $promise;
    }

    public function deleteItem($threadId,$threadItemId){
        $this->messenger->message(MessageFactory::message([
            'type' => 'deleteItem',
            'threadId' => $threadId,
            'threadItemId' => $threadItemId,
        ]));
        $deferred = new \React\Promise\Deferred();
        $this->promiseQueue["deleteItem"] = $deferred;
        $promise = $deferred->promise();
        return $promise;
    }

    private function getContainer(){
        return $this->container;
    }
}
/*
$loop = React\EventLoop\Factory::create();
$stdio = new Stdio($loop);
$loading = true;
$loadingPatternId = 0;


$loop->run();*/