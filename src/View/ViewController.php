<?php
namespace App\View;
use Clue\React\Stdio\Stdio;

class ViewController {

    private $container;
    
    public function __construct($container){
        $this->container = $container;
    }
    public function getContainer(){
        return $this->container;
    }

    public function go($viewName,$arguments = null){
        
        $container = $this->getContainer();
        $className = 'App\View\Views\\'.$viewName."View";
        (new $className($container))->build($this->getContainer()->get('stdio'),$arguments);       
    }

}