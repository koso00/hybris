<?php
namespace App\Service;
use React\EventLoop\LoopInterface;
use WyriHaximus\React\ChildProcess\Messenger\ChildInterface;
use WyriHaximus\React\ChildProcess\Messenger\Messages\Payload;
use WyriHaximus\React\ChildProcess\Messenger\Messenger;
use WyriHaximus\React\ChildProcess\Messenger\Messages\Factory as MessageFactory;

class InstagramAsyncWorker implements ChildInterface
{


    public static function create(Messenger $messenger, LoopInterface $loop)
    {
        /*$messenger->registerRpc('isPrime', function (Payload $payload) {
            return \React\Promise\resolve([
                'isPrime' => self::isPrime($payload['number']),
            ]);
        });*/
        $ig = new \InstagramAPI\Instagram(false, false,array(
            'storage'    => 'file',
            'basefolder' => __DIR__.'/../../sessions'
        ));
        
        $realtime = null;
        $messenger->registerRpc('error', function (Payload $payload) {
            throw new \Exception('whoops');
        });

        $messenger->on('message', function (Payload $payload,Messenger $messenger) use ($ig,$loop,&$realtime){

            $response = array();
            switch($payload["type"]){
                case "login":
                    $response = $ig->login($payload["username"],$payload["password"]);
                    $realtime = new \InstagramAPI\Realtime($ig, $loop, null);
                    $realtime->on('thread-created', function ($threadId, \InstagramAPI\Response\Model\DirectThread $thread) use ($messenger) {
                        
                        $messenger->message(MessageFactory::message([
                            "type" => "thread-created",
                            "response" => array(
                                "threadId" => $threadId)
                        ]));

                    });
                    $realtime->on('thread-updated', function ($threadId, \InstagramAPI\Response\Model\DirectThread $thread) use ($messenger){
                        $messenger->message(MessageFactory::message([
                            "type" => "thread-updated",
                            "response" => array(
                                "threadId" => $threadId)
                        ]));
                    });
                    $realtime->on('thread-notify', function ($threadId, $threadItemId, \InstagramAPI\Realtime\Payload\ThreadAction $notify) use ($messenger) {
                        $messenger->message(MessageFactory::message([
                            "type" => "thread-notify",
                            "response" => array(
                                                "threadId" => $threadId,
                                                "threadItemId" => $threadItemId,
                                                "notify" => $notify)
                        ]));
                    });
                    $realtime->on('thread-seen',function ($threadId, $userId, \InstagramAPI\Response\Model\DirectThreadLastSeenAt $seenAt) use ($messenger){
                        $messenger->message(MessageFactory::message([
                            "type" => "thread-seen",
                            "response" => array(
                                "threadId" => $threadId,
                                "userId" => $userId,
                            "seenAt" => $seenAt)
                        ]));
                    });
                    $realtime->on('thread-activity', function ($threadId, \InstagramAPI\Realtime\Payload\ThreadActivity $activity) use ($messenger){
                        $messenger->message(MessageFactory::message([
                            "type" => "thread-activity",
                            "response" => array(
                                "threadId" => $threadId,
                            "activity" => $activity)
                        ]));
                    });
                    $realtime->on('thread-item-created', function ($threadId, $threadItemId, \InstagramAPI\Response\Model\DirectThreadItem $threadItem) use ($messenger){
                        $messenger->message(MessageFactory::message([
                            "type" => "thread-item-created",
                            "response" => array(
                                "threadId" => $threadId,
                            "threadItemId" => $threadItemId,
                            "threadItem" => $threadItem)
                        ]));
                    });
                    $realtime->on('thread-item-updated', function ($threadId, $threadItemId, \InstagramAPI\Response\Model\DirectThreadItem $threadItem) use ($messenger){
                        $messenger->message(MessageFactory::message([
                            "type" => "thread-item-updated",
                            "response" => array(
                                "threadId" => $threadId,
                            "threadItemId" => $threadItemId,
                            "threadItem" => $threadItem)
                        ]));
                    });
                    $realtime->on('thread-item-removed', function ($threadId, $threadItemId) use ($messenger){
                        $messenger->message(MessageFactory::message([
                            "type" => "thread-item-removed",
                            "response" => array(
                                "threadId" => $threadId,
                            "threadItemId" => $threadItemId)
                        ]));
                    });
                    $realtime->on('client-context-ack', function (\InstagramAPI\Realtime\Payload\Action\AckAction $ack) use ($messenger){
                        $messenger->message(MessageFactory::message([
                            "type" => "client-context-ack",
                            "response" => array(
                                "ack" => $ack)
                        ]));
                    });
                    $realtime->on('unseen-count-update', function ($inbox, \InstagramAPI\Response\Model\DirectSeenItemPayload $payload) use ($messenger){
                        $messenger->message(MessageFactory::message([
                            "type" => "unseen-count-update",
                            "response" => array(
                                "payload" => $payload)
                        ]));
                    });
                    $realtime->on('presence', function (\InstagramAPI\Response\Model\UserPresence $presence) use ($messenger) {
                        $messenger->message(MessageFactory::message([
                            "type" => "presence",
                            "response" => array(
                                "presence" => $presence)
                        ]));
                    });
                    $realtime->on('error', function (\Exception $e) use ($messenger,$realtime){
                        $messenger->message(MessageFactory::message([
                            "type" => "error",
                            "response" => array(
                                "error" => $e)
                        ]));
                        
                        $realtime->stop();
                    });
                    $realtime->start();                    
                    break;
                case "inbox":
                    $response = $ig->direct->getInbox($payload["cursor"]);
                    break;
                case "thread":
                    $response = $ig->direct->getThread($payload["threadId"],$payload["cursor"]);
                    break;
                case "sendText":
                    $realtime->sendTextToDirect($payload["threadId"],$payload["text"]);
                    $response = json_encode();
                    break;
                case "markDirectItemSeen":
                    $realtime->markDirectItemSeen($payload["threadId"],$payload["threadItemId"]);
                    $response = json_encode();
                    break;
            }

            $messenger->message(MessageFactory::message([
                'type' => $payload["type"],
                'response' => $response,
            ]));
            
        });
        /*
        $messenger->registerRpc('inbox', function (Payload $payload) {
            return \React\Promise\resolve([
                'isPrime' => self::isPrime($payload['number']),
            ]);
        });

        $messenger->registerRpc('thread', function (Payload $payload) {
            return \React\Promise\resolve([
                'isPrime' => self::isPrime($payload['number']),
            ]);
        });
        */
    }
    /*
    private static function isPrime(int $number)
    {
        for($i=$n>>1;$i&&$n%$i--;);return!$i&&$n>1;
    }*/
}