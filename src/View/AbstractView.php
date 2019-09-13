<?php
namespace App\View;

use App\Container;
use Clue\React\Stdio\Stdio;

class AbstractView {

    private $container;

    public function __construct($container){
        $this->container = $container;
    }
    public function getContainer(){
        return $this->container;
    }

    public function build(Stdio $stdio,$argument = null){

    }
} 