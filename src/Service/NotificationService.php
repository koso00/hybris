<?php

namespace App\Service;
use \React\ChildProcess\Process;
use \React\Filesystem\Filesystem;
use \React\Promise\Stream;
use \React\HttpClient\Client;
use \React\HttpClient\Response;
class NotificationService {
    
    public function __construct($container){
        $this->container = $container;
    }

    public function getContainer(){
        return $this->container;
    }

    public function send($thread,$item){
        $loop = $this->getContainer()->get('loop');

        $url = '';
        $profilePicId = '';
        if (isset($thread->thread)){
            $users = $thread->thread->users;
        }else{
            $users = $thread->users;
        }
        foreach($users as $user){
            //var_dump($user);
            if ($user->pk == $item->user_id){
                $url = $user->profile_pic_url;
                $profilePicId = $user->profile_pic_id;
                break;
            }
        }
        
        // $process = new Process();
        if (file_exists(__DIR__ . '/../../cache/'.$profilePicId)){
            switch($item->item_type){
                case "text":
                    $text = $item->text;
                    break;
                default:
                    $text = '-media-';
            }

            if (isset($thread->thread)){
                $thread_title = $thread->thread->thread_title;
            }else{
                $thread_title = $thread->thread_title;
            }
            
            $this->_send('notify-send "'.$thread_title.'" "'.$text.'"'.' -i '.__DIR__.'/../../cache/'.$profilePicId);
        }
        
        else{
            $filesystem = Filesystem::create($loop);
            $file = Stream\unwrapWritable($filesystem->file(__DIR__ . '/../../cache/'.$profilePicId.'.jpg')->open('cw'));
            $client = new Client($loop);

            
            $request = $client->request('GET', $url);

            $request->on('response', \Closure::bind(function (Response $response) use ($file,$thread,$item,$profilePicId) {
                $response->pipe($file);

                $response->on('end',\Closure::bind(function() use ($thread,$item,$profilePicId){

                    switch($item->item_type){
                        case "text":
                            $text = $item->text;
                            break;
                        default:
                            $text = '-media-';
                    }
                    if (isset($thread->thread)){
                        $thread_title = $thread->thread->thread_title;
                    }else{
                        $thread_title = $thread->thread_title;
                    }
                    
                    $this->_send('notify-send "'.$thread_title.'" "'.$text.'"'.' -i '.__DIR__.'/../../cache/'.$profilePicId);
                },$this));
            },$this));
            $request->end();
        }
    }

    private function _send($cmd){
        $process = new Process($cmd);
        $process->start($this->getContainer()->get('loop'));
    }
}