<?php
namespace App\Service;

class Spinner {

    private $loading = false;
    private $spinnerIndex = 0;
    private $prompt;
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
                $stdio = $this->getContainer()->get('stdio');
                $stdio->setPrompt($this->prompt.$pattern[$this->spinnerIndex]." ");
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
        if ($this->loading){
            return;
        }
        $this->prompt = "".$this->getContainer()->get('stdio')->getPrompt();
        $this->loading = true;
    }

    public function stop(){
        $this->loading = false;
        $this->getContainer()->get('stdio')->setPrompt($this->prompt);
        $this->prompt = '';
    }
}