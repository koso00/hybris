<?php

namespace App\Container;

class Container {
    private $container = array();

    public function register($name,$val){
        $this->container[$name] = $val;
    }

    public function get($name){
        return $this->container[$name];
    }
}