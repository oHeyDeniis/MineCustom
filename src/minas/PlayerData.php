<?php 

namespace minas;

use muqsit\invmenu\InvMenu;
use pocketmine\entity\Human;
use pocketmine\form\Form;
use pocketmine\Player;

class PlayerData extends Player{

    public $data = [];
    public $sdata = [];

    public function getBuyMina() : string{
    return $this->data[$this->getName()]["window"];    
    }
    public function setBuyMina(?string $menu = null) : void{
    $this->data[$this->getName()]["window"] = $menu;
    }
    public function hasBuyMina() : bool{
        $menu = $this->data[$this->getName()]["window"] ?? null;
        return is_string($menu);
    }
    public function setIntMine(Mina $mina){
        $this->sdata[$this->getName()]["mina"] = $mina;
    }
    public function getIntMina() : ?Mina{
        return $this->sdata[$this->getName()]["mina"] ?? null;
    }
    public function setForm(?Form $f){
        $this->sdata[$this->getName()]["form"] = $f;
    }
    public function getForm() : ?Form{
        return $this->sdata[$this->getName()]["form"] ?? null;
    }
    public function setMixed($value){
        $this->sdata[$this->getName()]["mixed"] = $value;
    }
    public function getMixed(){
        return $this->sdata[$this->getName()]["mixed"] ?? null;
    }
    public function getCountMinas() : int{
        return count($this->sdata[$this->getName()]["minas"] ?? []);
    }
    public function addMina(Mina $mina){
        $this->sdata[$this->getName()]["minas"][] = $mina;
    }
    public function getMinas() : array{
    return $this->sdata[$this->getName()]["minas"];
    }
}