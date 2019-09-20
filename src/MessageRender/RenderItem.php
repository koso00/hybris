<?php

namespace App\MessageRender;

const PADDING_LEFT = 2;
const PADDING_RIGHT = 20;

class RenderItem {

    private $item;
    private $height;
    private $width;
    private $viewerId;
    private $bitly;
    private $response;

    public function __construct($item,$container,$viewerId){
        $this->item = $item;
        $this->viewerId = $viewerId;
        $this->container = $container;
        $this->response = new \stdClass();
    }
    
    public function getContainer(){
        return $this->container;
    }

    public function getWidth(){
        if ($this->width == null){
            $this->computeDimensions();
        }
        return $this->width;
    }

    public function getHeight(){
        if ($this->height == null){
            $this->computeDimensions();
        }
        return $this->height;
    }

    private function getWindowHeight(){
        return intvak(getenv('LINES'));
    }


    private function getWindowWidth(){
        return intval(getenv('COLUMNS'));
    }

    private function computeDimensions(){
        $height = 0;
        $width = 0;

        //$this->container->get('stdio')->write(json_encode($this->item));
        switch($this->item->item_type){
            case "text":
                $emoji = \Emoji\detect_emoji($this->item->text);
                $wrap = $this->wrapText($this->item->text);
                if (count($wrap) == 1){
                    $this->height = 1;
                    $this->width = strlen($wrap[0]);   
                }else{
                    $this->height = count($wrap);
                    $this->width = max(array_map(function($var){return strlen($var);},$wrap));
                }
                $this->width = $this->width - count($emoji) * 2;
                break;
            case "media":
                $this->bitly  =  "      -loading-      "; 
                $this->height = 1;
                $this->response->promise = $this->getContainer()->get('bitly')->shorten($this->item->media->image_versions2->candidates[0]->url);
                $this->width = strlen($this->bitly);
                break;
            case "like":
                $this->height = 1;
                $this->width = 1;
                break;
            case "link":
                $this->bitly  = "      -loading-      "; 
                $this->height = 1;
                $this->response->promise = $this->getContainer()->get('bitly')->shorten($this->item->link->text);
                $this->width = strlen($this->bitly);
                break;
            case "media_share":
                $this->bitly  = "https://instagram.com/p/".$this->item->media_share->code;
                $this->height = 1;
                $this->width = strlen($this->bitly);
                break;
            case "voice_media":
                $this->bitly  =  "      -loading-      "; 
                $this->response->promise = $this->getContainer()->get('bitly')->shorten($this->item->voice_media->media->audio->audio_src);
                $this->height = 1;
                $this->width = strlen($this->bitly);
                break;
            case "placeholder":
                $wrap = $this->wrapText($this->item->placeholder->message);
                if (count($wrap) == 1){
                    $this->height = 1;
                    $this->width = strlen($wrap[0]);   
                }else{
                    $this->height = count($wrap);
                    $this->width = max(array_map(function($var){return strlen($var);},$wrap));
                }                
                $this->getContainer()->get('logger')->debug(json_encode( $wrap).$this->width);
                break;
            case "raven_media":
                if (isset(json_decode(json_encode($this->item))->visual_media->media->image_versions2)){
                    $this->bitly  = "      -loading-      "; 
                    $this->response->promise = $this->getContainer()->get('bitly')->shorten(json_decode(json_encode($this->item))->visual_media->media->image_versions2->candidates[0]->url);
                }else{
                    $this->bitly  = "expired";
                }
                //$this->getContainer()->get('logger')->debug(json_encode($this->item));
                
                $this->height = 1;
                $this->width = strlen($this->bitly);
                break;
            default:
                $this->getContainer()->get('logger')->debug(json_encode($this->item));
        }
    }
    
    function flatten(array $array) {
        $return = array();
        array_walk_recursive($array, function($a) use (&$return) { $return[] = $a; });
        return $return;
    }

    private function wrapText($text){
        $stdio = $this->getContainer()->get('stdio');
        
        $limit = $this->getWindowWidth() - PADDING_LEFT - PADDING_RIGHT - 2;
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

    public function render(){
        //$stdio = $this->getContainer()->get('stdio');

        //$stdio->write($this->item->getText());

        $t = array();
        
        if ($this->item->user_id != $this->viewerId){
            array_push($t,str_pad("",PADDING_LEFT," ")."\\".str_pad("",$this->getWidth() + 2,"-"));

            switch ($this->item->item_type){
                case "text":
                    $wrap = $this->wrapText($this->item->text);
                    foreach ($wrap as $value) {
                        # code...
                        $emoji = \Emoji\detect_emoji($value);
                        array_push($t,str_pad("",PADDING_LEFT," ")."| ".mb_strimwidth($value.str_pad("",$this->getWindowWidth()," "),0,$this->getWidth() - count($emoji))." |");
                    }
                    break;
                case "media":
                case "media_share":
                case "raven_media":
                    array_push($t,str_pad("",PADDING_LEFT," ")."| ".mb_strimwidth("-media-".str_pad("",$this->getWindowWidth()," "),0,$this->getWidth())." |");

                    array_push($t,str_pad("",PADDING_LEFT," ")."| ".mb_strimwidth($this->bitly.str_pad("",$this->getWindowWidth()," "),0,$this->getWidth())." |");
                    break;
                case "voice_media":
                    array_push($t,str_pad("",PADDING_LEFT," ")."| ".mb_strimwidth("-voice-".str_pad("",$this->getWindowWidth()," "),0,$this->getWidth())." |");

                    array_push($t,str_pad("",PADDING_LEFT," ")."| ".mb_strimwidth($this->bitly.str_pad("",$this->getWindowWidth()," "),0,$this->getWidth())." |");
                    break;
                case "link":
                    array_push($t,str_pad("",PADDING_LEFT," ")."| ".mb_strimwidth($this->bitly.str_pad("",$this->getWindowWidth()," "),0,$this->getWidth())." |");
                    break;
                case "placeholder":

                    $wrap = $this->wrapText($this->item->placeholder->title);
                    foreach ($wrap as $value) {
                        # code...
                        array_push($t,str_pad("",PADDING_LEFT," ")."| ".mb_strimwidth($value.str_pad("",$this->getWindowWidth()," "),0,$this->getWidth() )." |");
                    }

                    $wrap = $this->wrapText($this->item->placeholder->message);
                    foreach ($wrap as $value) {
                        # code...
                        array_push($t,str_pad("",PADDING_LEFT," ")."| ".mb_strimwidth($value.str_pad("",$this->getWindowWidth()," "),0,$this->getWidth() )." |");
                    }
                    break;
                case "like":
                    array_push($t,str_pad("",PADDING_LEFT," ")."| ".mb_strimwidth("❤️".str_pad("",$this->getWindowWidth()," "),0,$this->getWidth())." |");
                    break;
            }
            
           
            array_push($t,str_pad("",PADDING_LEFT," ")."`".str_pad("",$this->getWidth() + 2,"-")."´");
           
        }else{

            $localPaddingLeft = $this->getWindowWidth() - ($this->getWidth() + 7);
            array_push($t,str_pad("",$localPaddingLeft," ").".".str_pad("",$this->getWidth() + 2,"-")."/");

            switch ($this->item->item_type){
                case "text":

                    $wrap = $this->wrapText($this->item->text);
                    foreach ($wrap as $value) {
                        # code...
                        $emoji = \Emoji\detect_emoji($value);
                        array_push($t,str_pad("",$localPaddingLeft," ")."| ".mb_strimwidth($value.str_pad("",$this->getWindowWidth()," "),0,$this->getWidth()- count($emoji))." |");
                    }
                    break;
                case "media":
                case "media_share":
                case "raven_media":
                    array_push($t,str_pad("",$localPaddingLeft," ")."| ".mb_strimwidth("-media-".str_pad("",$this->getWindowWidth()," "),0,$this->getWidth())." |");
                    array_push($t,str_pad("",$localPaddingLeft," ")."| ".mb_strimwidth($this->bitly.str_pad("",$this->getWindowWidth()," "),0,$this->getWidth())." |");
                    break;
                case "voice_media":
                    array_push($t,str_pad("",$localPaddingLeft," ")."| ".mb_strimwidth("-voice-".str_pad("",$this->getWindowWidth()," "),0,$this->getWidth())." |");
                    array_push($t,str_pad("",$localPaddingLeft," ")."| ".mb_strimwidth($this->bitly.str_pad("",$this->getWindowWidth()," "),0,$this->getWidth())." |");
                    break;
                case "link":
                    array_push($t,str_pad("",$localPaddingLeft," ")."| ".mb_strimwidth($this->bitly.str_pad("",$this->getWindowWidth()," "),0,$this->getWidth())." |");
                    break;
                case "placeholder":
                    $wrap = $this->wrapText($this->item->placeholder->title);
                    foreach ($wrap as $value) {
                        # code...
                        //$emoji = \Emoji\detect_emoji($value);
                        array_push($t,str_pad("",$localPaddingLeft," ")."| ".mb_strimwidth($value.str_pad("",$this->getWindowWidth()," "),0,$this->getWidth())." |");
                    }
                    $wrap = $this->wrapText($this->item->placeholder->message);
                    foreach ($wrap as $value) {
                        # code...
                        //$emoji = \Emoji\detect_emoji($value);
                        array_push($t,str_pad("",$localPaddingLeft," ")."| ".mb_strimwidth($value.str_pad("",$this->getWindowWidth()," "),0,$this->getWidth())." |");
                    }
                    break;
                case "like":
                    array_push($t,str_pad("",$localPaddingLeft," ")."| ".mb_strimwidth("❤️".str_pad("",$this->getWindowWidth()," "),0,$this->getWidth())." |");
                    break;

            }
            array_push($t,str_pad("",$localPaddingLeft," ")."`".str_pad("",$this->getWidth() + 2,"-")."´");
            if (isset($this->item->deleteMenu)){
                if ($this->item->deleteMenu){
                    array_push($t,str_pad("",$this->getWindowWidth() - 8 ," ")."| x |");
                    array_push($t,str_pad("",$this->getWindowWidth() - 8," ")."`---´");
                }
            }
            
        }

       $this->response->render = $t;

       //return $t;

       return $this->response;
    }
}