<?php

namespace App\MessageRender;

use LeadThread\Bitly\Bitly;

class BitlyCache {

    private $cache = [];
    private $container;
    private $filesystem;

    public function __construct($container){

        $this->filesystem = \React\Filesystem\Filesystem::create($container->get('loop'));
        if (getenv('BITLY_TOKEN') == null){
            $container->get('logger')->warning('Bitly token not found, nothing will be shortened');
        }
        $this->bitly = new Bitly($container->get('loop'),getenv('BITLY_TOKEN'));

        if (!file_exists(__DIR__ . '/../../cache/bitly.cache')){
            $this->filesystem->file(__DIR__ . '/../../cache/bitly.cache')->open('cwt')->then( \Closure::bind(function ($stream) {
                $stream->end(serialize($this->cache));
                $this->loadCache();
            },$this));
        }else{
            $this->loadCache();
        }

        /*register_shutdown_function( \Closure::bind(function(){
            $this->writeCache();
        },$this));

        pcntl_signal(SIGINT ,function(){
            die();
        });*/
    }

    private function loadCache(){

        $this->filesystem->getContents(__DIR__ . '/../../cache/bitly.cache')->then(\Closure::bind(function($contents) {
            $this->cache = unserialize($contents);
        },$this));
        
    }
    public function writeCache(){
        $this->filesystem->file(__DIR__ . '/../../cache/bitly.cache')->open('cwt')->then(function ($stream) {
            $stream->end(serialize($this->cache));
        });
    }


    public function shorten($link){
        if (getenv('BITLY_TOKEN') != null){
            
            if (isset($this->cache[$link])){
                return \React\Promise\resolve($this->cache[$link]);
            }else{

                $deferred = new \React\Promise\Deferred();

                $this->bitly->shorten($link)->then(\Closure::bind(function($shorten)use($deferred,$link){
                    $this->cache[$link] = $shorten;
                    $this->writeCache();
                    $deferred->resolve($shorten);
                },$this));
                $promise = $deferred->promise();
                return $promise;
            }
        }else{
            return \React\Promise\resolve($link);
        }
    }



}