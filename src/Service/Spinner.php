<?php
namespace App\Service;

class Spinner {

    private $loading = false;
    private $spinnerIndex = 0;
    public function __construct($container){
        $this->container = $container;

        $container->get('loop')->addPeriodicTimer(0.1, \Closure::bind(function (){
            if ($this->loading){
        
                $pattern = array(
                    '⠟',
                    '⠯',
                    '⠷',
                    '⠾',
                    '⠽',
                    '⠻'
                );
        
                $this->getContainer()->get('stdio')->write("\r".$pattern[$this->spinnerIndex]);
                $this->spinnerIndex += 1;
                if ($this->spinnerIndex == 6){
                    $this->spinnerIndex = 0;
                }
            }
        },$this));
    }

    public function getContainer(){
        return $this->container;
    }

    public function start(){
        $this->loading = true;
    }

    public function stop(){
        $this->loading = false;
        $this->getContainer()->get('stdio')->write("\033[2K\033[1A");
    }
}