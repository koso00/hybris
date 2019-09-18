<?php

namespace App\MessageRender;
use App\MessageRender\RenderItem;
class MessageRender {

    private $viewerId;

    public function __construct($container){
        $this->container = $container;
    }

    public function setViewerId($viewerId){
        $this->viewerId = $viewerId;
    }
    public function getContainer(){
        return $this->container;
    }

    public function transform($item){
        return new RenderItem($item,$this->getContainer(),$this->viewerId);
    }

    public function computeThreadHeight($thread){
        $height = 0;
        foreach ($thread->items as $item) {
            $height += $this->transform($item)->getHeight();
        }
        return $height;
    }
}