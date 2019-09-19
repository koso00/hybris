<?php
namespace App\View\Views;
use App\View\AbstractView;

use Clue\React\Stdio\Stdio;
use React\ChildProcess\Process;

const PADDING = 4;
class ChatsView extends AbstractView {

    private $inbox;
    private $cursor = 0;
    private $username;
    private $viewerId;
    private $viewBuffer = [];
    private $scroll = 0;
    private $hasOlder = false;
    private $threads = [];
    private $nextCursor;

    private function getChatHeight(){
        return intval(getenv('LINES')) - 4;
    }

    private function convert($size)
    {
        $unit=array('b','kb','mb','gb','tb','pb');
        return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
    }
    public function build(Stdio $stdio,$argument = null){
       
        /*$username = new Label([
            'text' => 'username',
            'top'  => 20,
            'left' => 20
        ]);*/
        
        $stdio->setPrompt('');
        $stdio->setEcho('');
        $stdio->hideCursor(); // hide cursor

        $ig = $this->getContainer()->get('ig');
        
        $ig->on('thread-created',\Closure::bind( function () {
            $this->updateInbox();
        },$this));
        $ig->on('thread-updated', \Closure::bind(function ($response) {
            $this->updateInbox();
        },$this));
        $ig->on('thread-item-created', \Closure::bind(function ($response) {

            $this->getContainer()->get('ig')->getThread($response->threadId)->done(\Closure::bind(function($thread) use ($response){
                $this->getContainer()->get('notification')->send($thread,$response->threadItem);
            },$this));
           
            $this->updateInbox();
        },$this));

        
        //Clean the screen
        for ($i = 0; $i < intval(getenv('LINES'));$i++){
            $stdio->write("\033[2K\033[1A");
        }
        //$stdio->write("| loading inbox ...");

        $this->updateInbox();
        
        $stdio->on("\033[A", \Closure::bind(function () {
            $this->scrollUp();
        },$this));

        $stdio->on('mouse',\Closure::bind(function($event){
            switch($event["type"]){
                case "wheel_up":
                    $this->scrollUp();
                    break;
                case "wheel_down":
                    $this->scrollDown();
                    break;
                case "left_click":
                    if ($event["y"] > 2){
                        /*$clickOffset = ($event["x"] - 3) % 3;
                        if ($clickOffset == 2){
                            return;
                        }*/

                        $cursor = intval(($this->scroll + ($event["y"] - 3) )/ 3);
                        if ($cursor > count($this->threads) -1){
                            return;
                        }
                        $this->cursor = $cursor;
                        //echo $this->cursor;
                        $this->openChat();
                        //$this->cursor = 
                        


                    }
            }
        },$this));
        $stdio->on("\033[B", \Closure::bind(function () {
            $this->scrollDown();
        },$this)); 
    
        $stdio->on("\n",\Closure::bind(function () {
            $this->openChat();
        },$this));
        /*
        $stdio->on("\033[B", function () use (&$value, $stdio) {
            --$value;
            $stdio->setPrompt('Value: ' . $value);
        });
*/
        //$stdio->write(json_encode($this->inbox,JSON_PRETTY_PRINT));
        

    }

    private function openChat(){
        $this->getContainer()->get('ig')->removeAllListeners('thread-created');
        $this->getContainer()->get('ig')->removeAllListeners('thread-updated');
        $this->getContainer()->get('ig')->removeAllListeners('thread-notify');
        $this->getContainer()->get('ig')->removeAllListeners('thread-seen');
        $this->getContainer()->get('ig')->removeAllListeners('thread-activity');
        $this->getContainer()->get('ig')->removeAllListeners('thread-item-created');
        $this->getContainer()->get('ig')->removeAllListeners('thread-item-updated');
        $this->getContainer()->get('ig')->removeAllListeners('thread-item-removed');
        $this->getContainer()->get('ig')->removeAllListeners('client-context-ack');
        $this->getContainer()->get('ig')->removeAllListeners('unseen-count-update');
        $this->getContainer()->get('ig')->removeAllListeners('presence');
        $this->getContainer()->get('stdio')->removeAllListeners("\n");
        $this->getContainer()->get('stdio')->removeAllListeners("mouse");            
        $this->getContainer()->get('stdio')->removeAllListeners("\033[A");
        $this->getContainer()->get('stdio')->removeAllListeners("\033[B");
        $this->getContainer()->get('stdio')->write("\033[?25h");
        $this->getContainer()->get('view-controller')->go("Chat",$this->threads[$this->cursor]->thread_id);
    }
    private function scrollUp(){
        if ($this->cursor > 0){
            $this->cursor --;
            $virtualScroll = 2 + ($this->cursor * 3) - $this->scroll;

            if ($this->scroll > 0){
                if ($virtualScroll < intval(getenv('LINES')) - 6){
                    $this->scroll -= 3;
                }
            }

           
            $this->drawInbox();
        }
        
    }
    private function scrollDown(){
        if ($this->cursor < count($this->threads)-1 ){
            $this->cursor ++;


            $virtualScroll = 2 + ($this->cursor * 3) - $this->scroll;
            
            if ($this->scroll < count($this->viewBuffer) -3){
                if ($virtualScroll > intval(getenv('LINES'))- 6){
                    $this->scroll += 3;
                }
            }
            
           if ($this->cursor == count($this->threads) -1 && $this->hasOlder){
                $this->loadMore();
           }else{
                $this->drawInbox();
           }
           // 
        }
    }
    
    private function loadMore(){
        $this->getContainer()->get('spinner')->start();
        $this->getContainer()->get('ig')->getInbox($this->nextCursor)->done(\Closure::bind(function($response){
            $this->getContainer()->get('spinner')->stop();
            $this->hasOlder = $response->inbox->has_older;
            $this->nextCursor = $response->inbox->prev_cursor;
            $this->threads = array_merge($this->threads,$response->inbox->threads);
            $this->drawInbox();
        },$this));
        //$this->getContainer()->get('stdio')->write(json_encode($more->getInbox()->getThreads(),JSON_PRETTY_PRINT));       
    }


    private function updateInbox(){
        $this->getContainer()->get('spinner')->start();

        $this->getContainer()->get('ig')->getInbox()->done(\Closure::bind(function($response){

            $this->getContainer()->get('spinner')->stop();

            $this->inbox = $response->inbox;
            $this->threads = $response->inbox->threads;
            //$this->getContainer()->get('logger')->debug(json_encode($response->inbox));
            $this->viewerId = $response->viewer->pk;
            $this->username = $response->viewer->username;
            $this->hasOlder = $response->inbox->has_older;
            $this->nextCursor = $response->inbox->prev_cursor;
            $this->drawInbox();
        },$this));


       
        //$this->getContainer()->get('logger')->debug($this->hasOlder);
        //$this->viewerId = $this->inbox->getInbox()->getViewerId();
       
    }



    private function drawInbox(){

        $stdio = $this->getcontainer()->get('stdio');

        for ($i = 0; $i < intval(getenv('LINES'));$i++){
            $stdio->write("\033[2K\033[1A");
        }

        /**
         * 
         * Calcolo sulla lunghezza della finestra
         * 
         */
        $width = intval(getenv('COLUMNS'));
        $cursor = $this->cursor;
        //$this->scrollHeight = $this->getContainer()->get('message-render')->computeThreadHeight($this->thread);
        $memory = "- memory usage : ".str_pad($this->convert(memory_get_usage(true)),10," ");
        
        $stdio->write("\033[1m Hybris ".$memory."\033[0m\n");
        $stdio->write(str_pad("",$width,"-")."\n");

        $threads = $this->threads;


        $background = "";
        $colored = "\e[0;47m\e[30m";
        $bold = "\033[1m";
        $reset = "\033[0m";

        $this->viewBuffer = [];
        foreach ($threads as $key => $thread) {
            $t = array();

            //$background = $key == $this->cursor ? "" : '';
            

            if ($key != 0){
                array_push($t,str_pad("",$width,"-"));
            }
            //checks the unread
            $threadTitle = $thread->thread_title;
            if ($thread->read_state != 0){
                $threadTitle = "# ".$threadTitle;
            }
            $threadTitle = $bold.$threadTitle.$reset;
            $lastItem = $thread->last_permanent_item;

            if ($lastItem != null){
                switch ($lastItem->item_type){
                    case "text":
                        $threadMessage = str_replace("\n"," ",$lastItem->text);
    
                        if ($lastItem->user_id == $this->viewerId){
                            $threadMessage = "You : ".$threadMessage;
                        }
                        
                        break;
                    default:
                        $threadMessage = "-media-";
                        if ($lastItem->user_id == $this->viewerId){
                            $threadMessage = "You : ".$threadMessage;
                        }
                        break;
                }
    
            }else{
                //$this->getContainer()->get('logger')->debug(json_encode($thread));
                $threadMessage = "";
            }
            
            if ($cursor === $key){
                $preTitle = "  >> ";
                $preMessage = "  >> ";
            }else{
                $preTitle = "  ";
                $preMessage = "  ";
            }
                   
            array_push($t,$preTitle.$threadTitle.str_pad("",$width," "));
            array_push($t,$preMessage.$threadMessage.str_pad("",$width," "));

            $this->viewBuffer = array_merge($this->viewBuffer,$t);
            //$stdio->write($t);
        }

        $this->drawView();
        //$stdio->write(str_pad("",$width,"-"));
    }

    private function drawView(){

        $stdio = $this->getContainer()->get('stdio');
        $logger = $this->getContainer()->get('logger');

        $this->scrollHeight = count($this->viewBuffer);

        //$stdio->write("\e[1;1H\e[2J\n");
        $height = $this->getChatHeight();
        $width = intval(getenv('COLUMNS'));

        $offsetBufferIndex = max($this->scroll,0);

        $scrollerThumbHeight = max(intval($height / ($this->scrollHeight / $height)),1);

        //$logger->debug("thumb height $scrollerThumbHeight ,height $height , scroll height".$this->scrollHeight);
        
        // top to down scroll
        ///$scrollerThumbPosition = intval((($height - $scrollerThumbHeight) * ($this->scroll / ($this->scrollHeight - $height))));

        $scrollerThumbPosition =  intval((($height - $scrollerThumbHeight) * ($this->scroll / ($this->scrollHeight - $height))));


        $highDensity = "▓";
        $lowDensity  = "░";

        //$logger->debug("thumb height $scrollerThumbHeight ,  thumb position $scrollerThumbPosition");
        
        $drawnLines = 0;
        for ($x = $offsetBufferIndex; $x < $offsetBufferIndex + $height; $x ++) {
            //$scrollerThumbHeight = 5;
            //$scrollerThumbPosition = 0;
            //$logger->debug($drawnLines);
            
            if ($scrollerThumbPosition - 1 < $drawnLines && $drawnLines < $scrollerThumbPosition + $scrollerThumbHeight){
                $thumb = $highDensity;
            }else{
                $thumb = $lowDensity;
            }
            
            if (!isset($this->viewBuffer[$x])){

                $stdio->write(mb_strimwidth(str_pad("",$width," "),0,$width - 1).$boldcompensate.$thumb."\n");
            }else{
                $emoji = \Emoji\detect_emoji(mb_strimwidth($this->viewBuffer[$x].str_pad("",$width," "),0,$width - 1 ));
                $boldcompensate = strpos($this->viewBuffer[$x],"\033[1m") !== false ? '        ' : '';
                $stdio->write(mb_strimwidth($this->viewBuffer[$x].str_pad("",$width," "),0,$width - 1 - count($emoji)).$boldcompensate."$thumb\n");
                //$logger->debug($this->viewBuffer[$x]);
            }
            
            $drawnLines+=1;
        }
        
    }
    
}