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
        $realtime = $this->getContainer()->get('realtime');
        
        $realtime->on('thread-created', function ($threadId, \InstagramAPI\Response\Model\DirectThread $thread) {
            $this->updateInbox();

            /*$this->getContainer()->get("stdio")->write(json_encode(Array(
                "type" => "thread-created",
                "threadId" => $threadId
            )));*/
        });
        $realtime->on('thread-updated', function ($threadId, \InstagramAPI\Response\Model\DirectThread $thread) {
            $this->updateInbox();
            /*$this->getContainer()->get("stdio")->write(json_encode(Array(
                "type" => "thread-updated",
                "threadId" => $threadId
            )));*/
        });
        $realtime->on('thread-notify', function ($threadId, $threadItemId, \InstagramAPI\Realtime\Payload\ThreadAction $notify) {
            /*$this->getContainer()->get("stdio")->write(json_encode(Array(
                "type" => "thread-notify",
                "threadId" => $threadId,
                "threadItemId" => $threadItemId,
                "notify" => $notify
            )));*/
        });
        $realtime->on('thread-seen',\Closure::bind( function ($threadId, $userId, \InstagramAPI\Response\Model\DirectThreadLastSeenAt $seenAt) {
            //$this->updateInbox();
            /*$this->getContainer()->get("stdio")->write(json_encode(Array(
                "type" => "thread-seen",
                "userId" => $userId,
                "seenAt" => $seenAt
            )));*/
        },$this));
        $realtime->on('thread-activity', function ($threadId, \InstagramAPI\Realtime\Payload\ThreadActivity $activity) {

            //$this->threadActivity();
            /*
            $this->getContainer()->get("stdio")->write(json_encode(Array(
                "type" => "thread-activity",
                "threadId" => $threadId,
                "activity" => $activity
            )));*/
        });
        $realtime->on('thread-item-created', \Closure::bind(function ($threadId, $threadItemId, \InstagramAPI\Response\Model\DirectThreadItem $threadItem) {
            $process = new Process('notify-send "New message from '.$this->getContainer()->get('ig')->direct->getThread($threadId)->getThread()->getThreadTitle().'" "'.$threadItem->getText().'"');
            $process->start($this->getContainer()->get('loop'));
            $this->updateInbox();
            /*
            $this->getContainer()->get("stdio")->write(json_encode(Array(
                "type" => "thread-item-created",
                "threadId" => $threadId,
                "threadItemId" => $threadItemId,
                "threadItem" => $threadItem
            )));*/
        
        },$this));
        $realtime->on('thread-item-updated', function ($threadId, $threadItemId, \InstagramAPI\Response\Model\DirectThreadItem $threadItem){
            /*$this->getContainer()->get("stdio")->write(json_encode(Array(
                "type" => "thread-item-updated",
                "threadId" => $threadId,
                "threadItemId" => $threadItemId,
                "threadItem" => $threadItem
            )));*/
        });
        $realtime->on('thread-item-removed', function ($threadId, $threadItemId){
            /*$this->getContainer()->get("stdio")->write(json_encode(Array(
                "type" => "thread-item-removed",
                "threadId" => $threadId,
                "threadItemId" => $threadItemId
            )));*/
        });
        $realtime->on('client-context-ack', function (\InstagramAPI\Realtime\Payload\Action\AckAction $ack){
            $this->getContainer()->get("stdio")->write(json_encode(Array(
                "type" => "client-context-ack",
                "ack" => $ack
            )));
        });
        $realtime->on('unseen-count-update', function ($inbox, \InstagramAPI\Response\Model\DirectSeenItemPayload $payload){
            $this->getContainer()->get("stdio")->write(json_encode(Array(
                "type" => "unseen-count-update",
                "payload" => $payload
            )));
        });
        $realtime->on('presence', function (\InstagramAPI\Response\Model\UserPresence $presence) {
            $this->getContainer()->get("stdio")->write(json_encode(Array(
                "type" => "presence",
                "presence" => $presence
            )));
        });
        $realtime->on('error', function (\Exception $e){
            $this->getContainer()->get("stdio")->write(json_encode(Array(
                "type" => "error",
                "presence" => $e
            )));
            
            $realtime->stop();
        });
        
        //Clean the screen
        $stdio->write("\e[1;1H\e[2J\n");
        $stdio->write("| loading inbox ...");

        $this->updateInbox();
        
        $stdio->on("\033[A", \Closure::bind(function () {
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
            
        },$this));


        $stdio->on("\033[B", \Closure::bind(function () {
            if ($this->cursor < count($this->inbox->getInbox()->getThreads())-1 ){
                $this->cursor ++;


                $virtualScroll = 2 + ($this->cursor * 3) - $this->scroll;
                
                if ($this->scroll < count($this->viewBuffer) -3){
                    if ($virtualScroll > intval(getenv('LINES'))- 6){
                        $this->scroll += 3;
                    }
                }
                
               
               // 
                $this->drawInbox();
            }
        },$this)); 
    
        $stdio->on("\n",\Closure::bind(function () {
            $this->getContainer()->get('realtime')->removeAllListeners('thread-created');
            $this->getContainer()->get('realtime')->removeAllListeners('thread-updated');
            $this->getContainer()->get('realtime')->removeAllListeners('thread-notify');
            $this->getContainer()->get('realtime')->removeAllListeners('thread-seen');
            $this->getContainer()->get('realtime')->removeAllListeners('thread-activity');
            $this->getContainer()->get('realtime')->removeAllListeners('thread-item-created');
            $this->getContainer()->get('realtime')->removeAllListeners('thread-item-updated');
            $this->getContainer()->get('realtime')->removeAllListeners('thread-item-removed');
            $this->getContainer()->get('realtime')->removeAllListeners('client-context-ack');
            $this->getContainer()->get('realtime')->removeAllListeners('unseen-count-update');
            $this->getContainer()->get('realtime')->removeAllListeners('presence');
            $this->getContainer()->get('stdio')->removeAllListeners("\n");
            $this->getContainer()->get('stdio')->removeAllListeners("\033[A");
            $this->getContainer()->get('stdio')->removeAllListeners("\033[B");
            $this->getContainer()->get('view-controller')->go("Chat",$this->inbox->getInbox()->getThreads()[$this->cursor]->getThreadId());
        },$this));
        /*
        $stdio->on("\033[B", function () use (&$value, $stdio) {
            --$value;
            $stdio->setPrompt('Value: ' . $value);
        });
*/
        //$stdio->write(json_encode($this->inbox,JSON_PRETTY_PRINT));
        

    }

    private function updateInbox(){
        $this->inbox = $this->getContainer()->get('ig')->direct->getInbox();
        
        $this->viewerId = $this->inbox->getViewer()["pk"];
        $this->username = $this->inbox->getViewer()["username"];
        //$this->viewerId = $this->inbox->getInbox()->getViewerId();
        $this->drawInbox();
    }



    private function drawInbox(){

        $stdio = $this->getcontainer()->get('stdio');

        $stdio->write("\e[1;1H\e[2J\n");

        /**
         * 
         * Calcolo sulla lunghezza della finestra
         * 
         */
        $width = intval(getenv('COLUMNS'));
        $cursor = $this->cursor;
        //$this->scrollHeight = $this->getContainer()->get('message-render')->computeThreadHeight($this->thread);
        $memory = "- memory usage : ".str_pad($this->convert(memory_get_usage(true)),10," ");
        
        $stdio->write("\033[1m".str_pad("",$paddingLeft," ")." Hybris ".$memory."\033[0m\n");
        $stdio->write(str_pad("",$width,"-")."\n");

        $threads = $this->inbox->getInbox()->getThreads();


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
            $threadTitle = $thread->getThreadTitle();
            if ($thread->getReadState() != 0){
                $threadTitle = "# ".$threadTitle;
            }
            $lastItem = $thread->getLastPermanentItem();
            switch ($lastItem->getItemType()){
                case "text":
                    $threadMessage = str_replace("\n"," ",$lastItem->getText());

                    if ($lastItem->getUserId() == $this->viewerId){
                        $threadMessage = "You : ".$threadMessage;
                    }
                    
                    break;
                default:
                    $threadMessage = "-media-";
                    if ($lastItem->getUserId() == $this->viewerId){
                        $threadMessage = "You : ".$threadMessage;
                    }
                    break;
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
                $stdio->write(mb_strimwidth(str_pad("",$width," "),0,$width - 1)."$thumb\n");
            }else{
                $emoji = \Emoji\detect_emoji($this->viewBuffer[$x]);
                $stdio->write(mb_strimwidth($this->viewBuffer[$x].str_pad("",$width," "),0,$width - 1 - count($emoji))."$thumb\n");
                //$logger->debug($this->viewBuffer[$x]);

            }
            
            $drawnLines+=1;
        }
    }
    
}