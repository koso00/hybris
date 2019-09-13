<?php
namespace App\View\Views;
use App\View\AbstractView;
use Clue\React\Stdio\Stdio;

class LoginView extends AbstractView {

    
    public function build(Stdio $stdio,$argument = null){
       
        $stdio->write("|*********************\n");
        $stdio->write("| Login ");

        $default = glob(__DIR__.'/../../../sessions/*',GLOB_ONLYDIR);
        usort($default, create_function('$a,$b', 'return filemtime($a)<filemtime($b);'));
        if (count($default) == 0){
            $stdio->setPrompt("| - Username: ");
            $first = true;
            $username = null;
            $password = null;
        }else{
            $stdio->setPrompt("| - Username (".basename($default[0]).") :");
            $first = true;
            $username = basename($default[0]);
            $password = basename($default[0]);
        }
        
        $container = $this->getContainer();

        $stdio->on('data', function ($line) use ($stdio, &$first, &$username, &$password,$container) {
            $line = rtrim($line, "\r\n");
            if ($first) {
                $stdio->setPrompt('| - Password: ');
                $stdio->setEcho('*');

                if ($line == "" && $username != null){
                }else{
                    $username = $line;
                }
                $first = false;
            } else {
                $stdio->write("| \n| Attempting login...\n");
                $stdio->setPrompt('');
                $stdio->setEcho('');
                $stdio->removeAllListeners('data');
                
                if ($line == "" && $password != null){
                }else{
                    $password = $line;
                }

                $container->get('loop')->addTimer(1,
                    function()use( &$username, &$password,$container,$stdio){
                        $ig = $container->get('ig');
                        $response = $ig->login($username,$password);
                        $container->get('logger')->debug(json_encode($response));
                        
                        $loop = $container->get('loop');
                        $realtime = new \InstagramAPI\Realtime($ig, $loop, null);
                        
                        $container->register('ig',$ig);
                        $container->register('realtime',$realtime);
        
                        $realtime->start();
        
                        $stdio->write("| Logged in! \r\n");
                        $stdio->write("|*********************\n");
        
                        $container->get('view-controller')->go('Chats',$stdio);
                    }
                );
            }
        });
        
    }

    
}


