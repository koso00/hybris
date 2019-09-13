<?php

namespace App\Model;

class Item {
    private $item_type = "text";
    private $text;
    private $user_id;

    public function setItemType($type){
        switch ($type){
            case "text":
            $this->item_type = "text";
                break;
            default:
                $this->item_type = "text";
            return $this;
        }
        return $this;
            
    }

    public function setUserId($user_id){
        $this->user_id = $user_id;
        return $this;
    }
    public function getUserId(){
        return $this->user_id;
    }
    
    public function setText($text){
        $this->text = $text;
        return $this;
    }
    public function getText(){
        return $this->text;
    }

    public function getItemType(){
        return $this->item_type;
    }
}