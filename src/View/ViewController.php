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
        for ($i = 0; $i < intval(getenv('LINES')) + 2;$i++){
            $this->getContainer()->get('stdio')->write("\r\033[2K\033[1A");
        }
        (new $className($container))->build($this->getContainer()->get('stdio'),$arguments);       
    }

}