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
    
    public function __construct($item,$container,$viewerId){
        $this->item = $item;
        $this->viewerId = $viewerId;
        $this->container = $container;
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
        switch($this->item->getItemType()){
            case "text":
                $emoji = \Emoji\detect_emoji($this->item->getText());
                $wrap = $this->wrapText($this->item->getText());
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
                $this->bitly  = $this->getContainer()->get('bitly')->shorten($this->item->getMedia()->getImageVersions2()->getCandidates()[0]->getUrl());
                $this->height = 1;
                $this->width = strlen($this->bitly);
                break;
            case "link":
                $this->bitly  = $this->getContainer()->get('bitly')->shorten($this->item->getLink()->getText());
                $this->height = 1;
                $this->width = strlen($this->bitly);
                break;
            case "media_share":
                $this->bitly  = "https://instagram.com/p/".$this->item->getMediaShare()->getCode();
                $this->height = 1;
                $this->width = strlen($this->bitly);
                break;
            case "voice_media":
                $this->bitly  = $this->getContainer()->get('bitly')->shorten($this->item->getVoiceMedia()->getMedia()->getAudio()->getAudioSrc());
                $this->height = 1;
                $this->width = strlen($this->bitly);
                break;
            case "raven_media":
                if (isset(json_decode(json_encode($this->item))->visual_media->media->image_versions2)){
                    $this->bitly  = $this->getContainer()->get('bitly')->shorten(json_decode(json_encode($this->item))->visual_media->media->image_versions2->candidates[0]->url);
                }else{
                    $this->bitly  = "expired";
                }
                //$this->getContainer()->get('logger')->debug(json_encode($this->item));
                
                $this->height = 1;
                $this->width = strlen($this->bitly);
                break;
            default:
                //$this->getContainer()->get('logger')->debug(json_encode($this->item));
        }
    }
 
    private function wrapText($text){
        $stdio = $this->getContainer()->get('stdio');
        
        //$stdio->write($text."\n");
        $wrap = wordwrap($text,$this->getWindowWidth() - PADDING_LEFT - PADDING_RIGHT - 2 ,"\n");
        
        return explode("\n",$wrap);
    }

    public function render(){
        //$stdio = $this->getContainer()->get('stdio');

        //$stdio->write($this->item->getText());

        $t = array();
        
        if ($this->item->getUserId() != $this->viewerId){
            array_push($t,str_pad("",PADDING_LEFT," ")."\\".str_pad("",$this->getWidth() + 2,"-"));

            switch ($this->item->getItemType()){
                case "text":
                    $wrap = $this->wrapText($this->item->getText());
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
            }
            
            array_push($t,str_pad("",PADDING_LEFT," ")."`".str_pad("",$this->getWidth() + 2,"-")."´");
        }else{

            $localPaddingLeft = $this->getWindowWidth() - ($this->getWidth() + 7);
            array_push($t,str_pad("",$localPaddingLeft," ").".".str_pad("",$this->getWidth() + 2,"-")."/");

            switch ($this->item->getItemType()){
                case "text":

                    $wrap = $this->wrapText($this->item->getText());
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
            }
            array_push($t,str_pad("",$localPaddingLeft," ")."`".str_pad("",$this->getWidth() + 2,"-")."´");
        }

       
        return $t;
    }
}