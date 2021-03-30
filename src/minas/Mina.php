<?php

namespace minas;

use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\Config;

class Mina{

    private $data = [];
    /**
     * @var Position
     */
    private $pos1;
    /**
     * @var Position
     */
    private $pos2;
    private $upgrades = ["drops" => ["level" => 1, "max" => 3], "limit" => ["level" => 1, "max" => 3], "size" => ["level" => 1, "max" => 4]];
    /**
     * @var Main
     */
    private $m;
    /**
     * @var string
     */
    private $folder;
    /**
     * @var Config
     */
    private $c;

    public function __construct(Main $m, array $d){
        $this->data = $d;
        $this->m = $m;
        if (!isset($this->data["upgrades"])) {
            $this->data["upgrades"] = $this->upgrades;
        }
        $this->folder = $m->getDataFolder()."saved-blocks/";
        @mkdir($this->folder);
        $this->c = new Config($this->folder.$this->getId().".yml", Config::YAML, []);

    }

    public function getOwner() : string{
        return strtolower($this->data["owner"]);
    }
    public function getId(){
        return $this->data["id"];
    }
    public function getType() : string{
        return $this->data["type"];
    }
    public function setCenter(string $pos) : void{
        $this->data["center"] = $pos;
    }
    public function getCenter() : Position{
        $e = explode("_", $this->data["center"]);
        return new Position($e[0], $e[1], $e[2], $this->getLevel());
    }
    public function getBlock() : Block{
        return Block::get($this->data["bid"], $this->data["bdm"]);
    }
    public function getSize() : string{
        return $this->data["size"];
    }
    public function setSize(string $size) : void{
        $this->data["size"] = $size;
    }
    public function getMembers() : array{
        return $this->data["members"];
    }
    public function addMember(string $name) : void{
        $name = strtolower($name);
        $this->data["members"][$name] = $name;
    }
    public function removeMember(string $name) : bool{
        $name = strtolower($name);
        if($this->isMember($name)){
            unset($this->data["members"][$name]);
            return true;
        }
        return false;
    }
    public function isMember(string $name) : bool{
        $name = strtolower($name);
        if($this->isPublic()){
            if($this->getOwner() == $name){
                return false;
            }else return true;

        }
        return isset($this->data["members"][$name]);
    }
    public function isPublic() : bool{
        return $this->data["public"];
    }
    public function setPublic(bool $v) : void{
        $this->data["public"] = $v;
    }
    public function getResetTime() : int{
        return $this->data["reset"];
    }
    public function setResetTime(int $t) : void{
        if($t == -1){
            $t = $this->data["default-reset"];
        }
        $this->data["reset"] = $t;
    }
    public function getMaxDrops() : int{
        $a = [1 => 1000, 2 => 3000, 3 => 5000];
        return $a[$this->getUpgradeLevel("limit")];
    }
    public function getTotalDrops() : int{
        $i = 0;
        foreach ($this->getMineInventory() as $n => $c){
            $i += $c["count"];
        }
        return  $i;
    }
    public function getMineInventory() : array{
        return $this->data["inventory"];
    }
    public function getItemCount($i) : int{
        return $this->data["inventory"][$i instanceof Item ? $i->getName() : $i]["count"] ?? 0;
    }
    public function addItemInventory(Item $i) : bool{
        if($this->getTotalDrops() >= $this->getMaxDrops()) return false;
        $l = $this->getUpgradeLevel("drops");
        if(rand(0, (10 - $l)) == $l){
            $i->setCount($l);
        }
        $this->data["inventory"][$i->getName()] = ["count" => $this->getItemCount($i) + $i->getCount(), "item" => $i->jsonSerialize()];
    return true;
    }
    public function removeItem($i, $count){
        $n = $i instanceof Item ? $i->getName() : $i;
        $a = $this->data["inventory"][$n];
        $item = Item::jsonDeserialize($a["item"]);
        $item->setCount($count);
        $nowCount = $this->getItemCount($n) - $count;
        if($nowCount <= 0){
            unset($this->data["inventory"][$n]);
        }else {
            $this->data["inventory"][$n]["count"] = $nowCount;
        }
        return $item;
    }
    public function getLevel() : ?Level{
        return Server::getInstance()->getLevelByName($this->data["level"]);
    }
    public function isinArea(Position $pos) : bool{
        $p1e = explode("_", $this->data["pos1"]);
        $this->pos1 = new Position($p1e[0], $p1e[1], $p1e[2], $this->getLevel());
        $p2e = explode("_", $this->data["pos2"]);
        $this->pos2 = new Position($p2e[0], $p2e[1], $p2e[2], $this->getLevel());
        if((min($this->pos1->getX(),$this->pos2->getX()) <= $pos->getX()) && (max($this->pos1->getX(),$this->pos2->getX()) >= $pos->getX()) && (min($this->pos1->getY(),$this->pos2->getY()) <= $pos->getY()) && (max($this->pos1->getY(),$this->pos2->getY()) >= $pos->getY()) && (min($this->pos1->getZ(),$this->pos2->getZ()) <= $pos->getZ()) && (max($this->pos1->getZ(),$this->pos2->getZ()) >= $pos->getZ()) && ($this->getLevel()->getName() == $pos->getLevel()->getName())) {
            return true;
        }
        return false;
    }
    public function getUpgradeLevel(string $up) : int{
        return $this->data["upgrades"][$up]["level"] ?? 1;
    }
    public function setUpgradeLevel(string $up, int $l, ?Main $m = null) : void{
        $this->data["upgrades"][$up]["level"] = $l;
        if ($up == "size") {
            $sizes = [1 => "6x5x6", 2 => "10x10x10", 3 => "16x16x16", 4 => "20x20x20"];
            $times = [1 => 60*5, 2 => 60*10, 3 => 60*15, 4 => 60*20];
            $this->data["default-reset"] = $times[$this->getUpgradeLevel($up)];
            $size = $sizes[$this->getUpgradeLevel($up)];
            $this->setSize($size);
        }
    }
    public function getNextSize() : string{
        $sizes = [1 => "6x5x6", 2 => "10x10x10", 3 => "16x16x16", 4 => "20x20x20"];
        $size = $sizes[$this->getUpgradeLevel("size") + 1];
        return $size;
    }
    public function getMaxUpgradeLevel(string $up) : int{
        return $this->data["upgrades"][$up]["max"] ?? 3;
    }
    public function canUpgrade(string $up, int $l) : bool{
        if($this->getUpgradeLevel($up) == $this->getMaxUpgradeLevel($up)) return false;
        if($this->getMaxUpgradeLevel($up) >= $l) return true;
        return false;
    }
    public function getFull(string $up) : ?string{
        if($this->canUpgrade($up, ($this->getUpgradeLevel($up) + 1))){
            return null;
        }else return " &e(Full)";
    }
    public function getRandBlock(bool $air = false) : Block{
        if($air) return Block::get(0);
        $arr = $this->data["blocks"];
        $a = $arr[array_rand($arr)];
        $b = Block::get($a["id"], $a["damage"]);
        return $b;
    }
    public function compareBlock(Block $b) : bool{
        foreach ($this->data["blocks"] as $id => $a) {
            if($a["id"] == $b->getId() and $a["damage"] == $b->getDamage()){
                return true;
            }
        }
        return false;
    }
    public function saveBlock($pos, Block $b) : bool{
        $eid = $pos->getFloorX()."_".$pos->getFloorY()."_".$pos->getFloorZ();
        $c = $this->c;
        if($c->exists($eid)){
            return false;
        }
        $c->set($eid, [$b->getId(), $b->getDamage()]);
        return true;
    }
    function ok(){
        $this->c->save();
    }
    public function hasSavedBlock($pos) : bool{
        $eid = $pos->getFloorX()."_".$pos->getFloorY()."_".$pos->getFloorZ();
        $c = $this->c;
        return$c->exists($eid);
    }
    public function getSavedBlock($pos) : ?Block{
        if($this->hasSavedBlock($pos)){
            $c = $this->c;
            $eid = $pos->getFloorX()."_".$pos->getFloorY()."_".$pos->getFloorZ();
            $a = $c->get($eid);
            return Block::get($a[0], $a[1]);
        }
        return null;
    }
    public function onDelete(bool $remake = false){
        unlink($this->folder.$this->getId().".yml");
        if($remake){
            @mkdir($this->folder);
            $this->c = new Config($this->folder.$this->getId().".yml", Config::YAML, []);

        }
    }
    public function setData(array $data) : void{
        $this->data = $data;
    }
    public function getData() : array{
        return $this->data;
    }
}
?>