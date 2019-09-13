<?php

namespace App\MessageRender;

use LeadThread\Bitly\Bitly;

class BitlyCache {

    private $cache = [];
    private $container;
    
    public function __construct($container){

        if (!file_exists(__DIR__ . '/../../cache')){
            file_put_contents(__DIR__ . '/../../cache', serialize($this->cache));
        }

        if (getenv('BITLY_TOKEN') == null){
            $container->get('logger')->warning('Bitly token not found, nothing will be shortened');
        }
        $this->bitly = new Bitly(getenv('BITLY_TOKEN'));
        $this->cache = unserialize(file_get_contents(__DIR__ . '/../../cache'));
        /*register_shutdown_function( \Closure::bind(function(){
            $this->writeCache();
        },$this));

        pcntl_signal(SIGINT ,function(){
            die();
        });*/
    }

    public function writeCache(){
        file_put_contents(__DIR__ . '/../../cache', serialize($this->cache));
    }


    public function shorten($link){
        if (getenv('BITLY_TOKEN') != null){
            
            if (isset($this->cache[$link])){
                return $this->cache[$link];
            }else{
                $shorten = $this->bitly->shorten($link);
                $this->cache[$link] = $shorten;
                $this->writeCache();
                return $shorten;
            }
        }else{
            return $link;
        }
    }



}