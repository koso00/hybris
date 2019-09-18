<?php
namespace App\View\Views;
use App\View\AbstractView;
use Clue\React\Stdio\Stdio;
use App\Model\Item;
class ChatView extends AbstractView {
    
    private $chat;
    private $threadId;
    private $scroll = 0;
    private $scrollHeight = 0;
    private $height = 0;
    private $width = 0;
    private $items = [];
    private $rendered_items = [];
    private $users = [];
    private $prevCursor;
    private $hasOlder = false;
    private $viewerId;
    private $viewBuffer = [];
    private $prompt = "| Write message > ";
    private $activity = false;
    private $seen = false;
    private $inputLines = array();
    public function build(Stdio $stdio,$argument = null){
       
        $this->threadId = $argument;
        /*$username = new Label([
            'text' => 'username',
            'top'  => 20,
            'left' => 20
        ]);*/
        $this->updateThread();
        $stdio->setPrompt($this->prompt);
        $stdio->setEcho(true);

        $this->scrollHeight = count($this->viewBuffer);
        
        $width = intval(getenv('COLUMNS'));
        $this->getContainer()->get('loop')->addPeriodicTimer(0.1,\Closure::bind(function () use ($width) {
            $stdio = $this->getContainer()->get('stdio');
            $line = $stdio->getReadLine()->getInput();
            $wrap = $this->wrapText($line);
            if (count($this->wrapText($line)) > 1 ){
                $this->getContainer()->get('logger')->debug(json_encode($wrap));

                $lastChar = array_pop($wrap);

                $this->inputLines = array_merge($this->inputLines, $wrap);

                //$this->getContainer()->get('logger')->debug(json_encode($this->inputLines));
                $stdio->getReadLine()->setInput($lastChar);
                $this->drawChat();
            }else{
                if (count($this->inputLines) != 0 && $line == ''){
                    $line = array_pop($this->inputLines);
                    $stdio->setInput($line);
                    $this->drawChat();
                }
            }
        },$this));
        
        $stdio->on("\033[B", \Closure::bind(function () {
            if ($this->scroll > 0){
                $this->scroll -= 3;//= max($this->scroll-2,0);
                if ($this->scroll < 0){
                    $this->scroll = 0;
                }
                $this->drawChat();
                //$this->drawInbox();
            }
            
        },$this));

        $stdio->on("\033[A", \Closure::bind(function () {
            if ($this->scroll < $this->scrollHeight - $this->getChatHeight()){
                $this->scroll = min($this->scrollHeight - $this->getChatHeight(),$this->scroll + 3);
                $this->drawChat();
                if ($this->scroll > $this->scrollHeight - $this->getChatHeight() - 10){
                   if ($this->hasOlder){
                    $this->loadMore();
                   } 
                }
                //$this->drawInbox();
            }
        },$this)); 

        $stdio->on("\x05", \Closure::bind(function () {
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
            $this->getContainer()->get('stdio')->removeAllListeners("\x05");
            $this->getContainer()->get('stdio')->removeAllListeners("data");
            $this->getContainer()->get('stdio')->removeAllListeners("\033[A");
            $this->getContainer()->get('stdio')->removeAllListeners("\033[B");
            $this->getContainer()->get('view-controller')->go("Chats");
        },$this)); 


        // SEND A MESSAGE
        $stdio->on("data", \Closure::bind(function ($input) {
            $logger = $this->getContainer()->get('logger');
            
            $input = rtrim($input);

            $inputLine = "";
            foreach ($this->inputLines as $line){
                $inputLine = $inputLine.$line;
            }
            $input = $inputLine.$input;

            if ($input == ""){
                $this->drawChat();
                return;
            }
            $this->inputLines = [];
            $item = new Item();
            $item->setUserId($this->viewerId)->setItemType("text")->setText(rtrim($input));
            //$logger->debug($input);
            $this->seen = false;
            //$this->getContainer()->get('ig')->direct->sendText(array("thread" => $this->threadId),$input);
            $this->getContainer()->get('realtime')->sendTextToDirect($this->threadId,$input);

            array_unshift($this->items,$item);

            $this->drawChat();
            /*
            if ($this->scroll > 0){
                $this->scroll --;
                $this->drawChat();
                //$this->drawInbox();
            }*/
            
        },$this));

        $realtime = $this->getContainer()->get('realtime');
        
        $realtime->on('thread-created', function ($threadId, \InstagramAPI\Response\Model\DirectThread $thread) {
            $this->getContainer()->get('logger')->debug(json_encode(Array(
                "type" => "thread-created",
                "threadId" => $threadId
            )));
        });
        $realtime->on('thread-updated', function ($threadId, \InstagramAPI\Response\Model\DirectThread $thread) {
            $this->getContainer()->get('logger')->debug(json_encode(Array(
                "type" => "thread-updated",
                "threadId" => $threadId
            )));
        });
        $realtime->on('thread-notify', function ($threadId, $threadItemId, \InstagramAPI\Realtime\Payload\ThreadAction $notify) {
            $this->getContainer()->get('logger')->debug(json_encode(Array(
                "type" => "thread-notify",
                "threadId" => $threadId,
                "threadItemId" => $threadItemId,
                "notify" => $notify
            )));
        });
        $realtime->on('thread-seen',\Closure::bind( function ($threadId, $userId, \InstagramAPI\Response\Model\DirectThreadLastSeenAt $seenAt) {
            
            $this->getContainer()->get('logger')->debug(json_encode(Array(
                "type" => "thread-seen",
                "threadId" => $threadId,
                "seenAt" => $seenAt,
                "userId" => $userId
            )));
            if ($threadId == $this->threadId && $userId != $this->viewerId){
                $this->seen = true;
                $this->drawChat();
            }
            //$this->updateInbox();
            /*$this->getContainer()->get('logger')->debug(json_encode(Array(
                "type" => "thread-seen",
                "userId" => $userId,
                "seenAt" => $seenAt
            )));*/
        },$this));
        $realtime->on('thread-activity', \Closure::bind(function ($threadId, \InstagramAPI\Realtime\Payload\ThreadActivity $activity) {

            //$this->threadActivity();
            if ($threadId == $this->threadId){
                $this->activity = true;
                $this->getContainer()->get('loop')->addTimer(5,\Closure::bind(function(){$this->activity = false;$this->drawChat();},$this));
                $this->drawChat();
            }
            /*
            $this->getContainer()->get('logger')->debug(json_encode(Array(
                "type" => "thread-activity",
                "threadId" => $threadId,
                "activity" => $activity
            )));*/
        },$this));
        $realtime->on('thread-item-created', \Closure::bind(function ($threadId, $threadItemId, \InstagramAPI\Response\Model\DirectThreadItem $threadItem) {
            
            if ($threadId == $this->threadId){
               /////////////////// MANDARE NOTIFICA
                //$this->getContainer()->get('stdio')->write("\07");
                $this->getContainer()->get('notification')->send($this->thread,$threadItem);

                $this->activity = false;
                $this->seen = false;
                array_unshift($this->items,$threadItem);

                $this->getContainer()->get('realtime')->markDirectItemSeen($threadId,$threadItemId);
                $this->drawChat();
            }else{
                $this->getContainer()->get('notification')->send($this->getContainer()->get('ig')->direct->getThread($threadId),$threadItem);
                //$process->start($this->getContainer()->get('loop'));
            }
            
            /*
            $this->getContainer()->get('logger')->debug(json_encode(Array(
                "type" => "thread-item-created",
                "threadId" => $threadId,
                "threadItemId" => $threadItemId,
                "threadItem" => $threadItem
            )));*/
        
        },$this));
        $realtime->on('thread-item-updated', function ($threadId, $threadItemId, \InstagramAPI\Response\Model\DirectThreadItem $threadItem){
            $this->getContainer()->get('logger')->debug(json_encode(Array(
                "type" => "thread-item-updated",
                "threadId" => $threadId,
                "threadItemId" => $threadItemId,
                "threadItem" => $threadItem
            )));
        });
        $realtime->on('thread-item-removed', function ($threadId, $threadItemId){
            $this->getContainer()->get('logger')->debug(json_encode(Array(
                "type" => "thread-item-removed",
                "threadId" => $threadId,
                "threadItemId" => $threadItemId
            )));
        });
        $realtime->on('client-context-ack', function (\InstagramAPI\Realtime\Payload\Action\AckAction $ack){
            $this->getContainer()->get('logger')->debug(json_encode(Array(
                "type" => "client-context-ack",
                "ack" => $ack
            )));
        });
        $realtime->on('unseen-count-update', function ($inbox, \InstagramAPI\Response\Model\DirectSeenItemPayload $payload){
            $this->getContainer()->get('logger')->debug(json_encode(Array(
                "type" => "unseen-count-update",
                "payload" => $payload
            )));
        });
        $realtime->on('presence', \Closure::bind(function (\InstagramAPI\Response\Model\UserPresence $presence) {
            $this->getContainer()->get('logger')->debug(json_encode(Array(
                "type" => "presence",
                "presence" => $presence
            )));
        },$this));
        $realtime->on('error', function (\Exception $e){
            $this->getContainer()->get('logger')->debug(json_encode(Array(
                "type" => "error",
                "presence" => $e
            )));
            
            $realtime->stop();
        });
        

        //$this->drawView();
        $this->drawChat();
    }
    function flatten(array $array) {
        $return = array();
        array_walk_recursive($array, function($a) use (&$return) { $return[] = $a; });
        return $return;
    }
    private function wrapText($text){
        $stdio = $this->getContainer()->get('stdio');
        $width = intval(getenv('COLUMNS'));
        $limit = $width - strlen($this->prompt) - 3;
        //$stdio->write($text."\n");

        //$this->getContainer()->get('logger')->debug($text);

        $wrap = wordwrap($text, $limit,"\n");

        //$this->getContainer()->get('logger')->debug(json_encode($wrap));

        $exploded = explode("\n",$wrap);

        $shouldFlatten = false;
        foreach ($exploded as &$s){
            if (strlen($s) > $limit){
                $shouldFlatten = true;
                $s = explode("\n",wordwrap($s, $limit,"\n",true));
                //$this->getContainer()->get('logger')->debug(json_encode($s));
            }
        }     

        if ($shouldFlatten){
            $exploded = $this->flatten($exploded);
        }

        return $exploded;
    }
    
    private function getChatHeight(){
        return intval(getenv('LINES')) - 5;
    }
    
    private function loadMore(){
        $more = $this->getContainer()->get('ig')->direct->getThread($this->threadId,$this->prevCursor);
        $this->prevCursor = $more->getThread()->getPrevCursor();
        $this->hasOlder = $more->getThread()->getHasOlder();
        $this->items = array_merge($this->items,$more->getThread()->getItems());
        $this->drawChat();
    }
    private function updateThread(){
        $stdio = $this->getContainer()->get('stdio');
        $logger = $this->getContainer()->get('logger');
        $this->thread = $this->getContainer()->get('ig')->direct->getThread($this->threadId);
        //$logger->debug(json_encode($this->thread));
        $this->hasOlder = $this->thread->getThread()->getHasOlder();
        $this->prevCursor = $this->thread->getThread()->getPrevCursor();
        $this->items = $this->thread->getThread()->getItems();

        if (isset($this->items[0])){
            if ($this->items[0]->getUserId() != $this->viewerId){
                $this->getContainer()->get('realtime')->markDirectItemSeen($this->threadId,$this->items[0]->getItemId());
            }
        }
        //
        $this->viewerId = $this->thread->getThread()->getViewerId();
        $this->getContainer()->get('message-render')->setViewerId($this->viewerId);

        $lastSeenAt = json_decode(json_encode($this->thread->getThread()->getLastSeenAt()));
        //$logger->debug(json_encode($lastSeenAt));

        $lastSeenItem = null;
        foreach ($lastSeenAt as $key => $item){
            if ($key != $this->viewerId){
                $lastSeenItem = $item;
            }
        }
        //$logger->debug(json_encode($lastSeenItem));

        foreach($this->items as $item){
            if ($item->getUserId() == $this->viewerId){
                if ($item->getItemId() == $lastSeenItem->item_id){
                    $this->seen = true;
                }else{
                    $this->seen = false;
                }
                break;
            }
        }
        

        //$stdio->write(json_encode($this->thread,JSON_PRETTY_PRINT));
        //$this->drawChat();
    }

    private function drawChat(){

        $stdio = $this->getcontainer()->get('stdio');
        //$stdio->write("\e[1;1H\e[2J\n");
        for ($i = 0; $i < intval(getenv('LINES'));$i++){
            $stdio->write("\033[2K\033[1A");
        }

        /**
         * 
         * Calcolo sulla lunghezza della finestra
         * 
         */
        $width = intval(getenv('COLUMNS'));
        $threadName = $this->thread->getThread()->getThreadTitle();
        $memory =  str_pad(" memory usage : ".$this->convert(memory_get_usage(true)),10," ");

        $paddingLeft = intval($width / 2 - strlen($threadName) / 2) - strlen($memory);

        if ($this->activity){
            $threadName = $threadName." [typing] ";
        }
        //$this->scrollHeight = $this->getContainer()->get('message-render')->computeThreadHeight($this->thread);

        $stdio->write("\033[1m".$memory.str_pad("",$paddingLeft," ").$threadName."\033[0m\n");
        $stdio->write(str_pad("",$width,"-")."\n");

        $this->viewBuffer = [];

        if ((count($this->items) != count($this->rendered_items)) || count($this->rendered_items) == 0){
            $this->rendered_items = [];
            foreach (array_reverse($this->items) as $item){
                array_push($this->rendered_items ,$this->getContainer()->get('message-render')->transform($item)->render());
            }
        }

        $this->viewBuffer = array_reduce($this->rendered_items,'array_merge',array());
        //$this->getcontainer()->get('logger')->debug(json_encode($this->viewBuffer));
        
        if ($this->seen){
            array_push($this->viewBuffer,str_pad("",$width - 8," ")."-seen-");
        }

        $this->drawView();
        $stdio->write(".".str_pad("",$width - 2,"-").".\n");
        foreach($this->inputLines as $line){
            $stdio->write("| ".str_pad("",strlen($this->prompt) - 2," ").$line."\n");
        }   
    }
    
    private function invalidateRenderCache(){
        $this->rendered_items = [];
    }

    private function sendMessage(){
        
    }

    private function convert($size)
    {
        $unit=array('b','kb','mb','gb','tb','pb');
        return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
    }



    private function drawView(){

        $stdio = $this->getContainer()->get('stdio');
        $logger = $this->getContainer()->get('logger');

        $this->scrollHeight = count($this->viewBuffer);

        //$stdio->write("\e[1;1H\e[2J\n");
        $height = $this->getChatHeight() - count($this->inputLines);
        $width = intval(getenv('COLUMNS'));

        $offsetBufferIndex = max($this->scrollHeight - $height - $this->scroll,0);

        $scrollerThumbHeight = max(intval($height / ($this->scrollHeight / $height)),1);

        //$logger->debug("thumb height $scrollerThumbHeight ,height $height , scroll height".$this->scrollHeight);
        
        // top to down scroll
        ///$scrollerThumbPosition = intval((($height - $scrollerThumbHeight) * ($this->scroll / ($this->scrollHeight - $height))));

        $scrollerThumbPosition =  intval($height - $scrollerThumbHeight) - intval((($height - $scrollerThumbHeight) * ($this->scroll / ($this->scrollHeight - $height))));


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
            }
            
            $drawnLines+=1;
        }
    }
}


