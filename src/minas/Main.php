<?php

namespace minas;

use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\ModalForm;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\block\Block;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;

use pocketmine\command\CommandSender;
use pocketmine\command\Command;

use pocketmine\event\player\PlayerCreationEvent;

use pocketmine\item\Item;

use pocketmine\Player;
use pocketmine\scheduler\Task;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class Main extends PluginBase implements Listener
{

    public $money;
    public $cash;

    public $minas = [], $arrows = [], $maxminas = [], $config = [], $mines = [];
    /**
     * @var Config
     */
    private $miness;
    /**
     * @var Config
     */
    private $savedmines;


    public function onEnable()
    {

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $c = new Config($this->getDataFolder() . "Items.yml", Config::YAML, [
            "minas" => [
                "ferro" => [
                    "name" => "Mina de Ferro \n Clique Para ver",
                    "id" => 1,
                    "damage" => 0,
                    "cash" => 1,
                    "money" => 2,
                    "imagem" => "textures/blocks/iron_block",
                    "blocks" => [
                        "#0" => [
                            "id" => Block::STONE,
                            "damage" => 0
                        ],
                        "#1" => [
                            "id" => Block::IRON_ORE,
                            "damage" => 0
                        ]
                    ]
                ],
                "esmeralda" => [
                    "name" => "Mina de Esmeralda \n Coins: 0 | Cash: 0",
                    "id" => 2,
                    "damage" => 0,
                    "cash" => 1,
                    "money" => 2,
                    "imagem" => "textures/blocks/emerald_block",
                    "blocks" => [
                        "#0" => [
                            "id" => Block::STONE,
                            "damage" => 0
                        ],
                        "#1" => [
                            "id" => Block::IRON_ORE,
                            "damage" => 0
                        ]
                    ]
                ]
            ],
            "config" => [
                "anti-lag" => false,
                "save-mine-blocks" => true,
                "default-mine-size" => "6x5x6",
                "upgrades" => [
                    "drops" => [
                        1 => ["cash" => 100, "money" => 1000],
                        2 => ["cash" => 200, "money" => 2000],
                        3 => ["cash" => 300, "money" => 3000],
                        "desc" => "Aumentar Chance de duplicar Drops"
                    ],
                    "limit" => [
                        1 => ["cash" => 100, "money" => 1000],
                        2 => ["cash" => 200, "money" => 2000],
                        3 => ["cash" => 300, "money" => 3000],
                        "desc" => "Aumentar Limite do inventario da Mina"
                    ],
                    "size" => [
                        1 => ["cash" => 100, "money" => 1000],
                        2 => ["cash" => 200, "money" => 2000],
                        3 => ["cash" => 300, "money" => 3000],
                        "desc" => "Aumentar Tamanho da Mina"
                    ]

                ],
                "eval-verifycation" => "-",
                "mina-replace-blocks" => [2, 0, Block::DIRT, Block::STONE, Block::COBBLESTONE]
            ],
            "minas-por-tag" => [
                "player" => 3,
                "mvp" => 5
            ]
        ]);
        $this->minas = $c->get("minas");
        $this->miness = new Config($this->getDataFolder() . "Minas.json", Config::JSON, []);
        $this->savedmines = new Config($this->getDataFolder() . "Saved-mines.json", Config::JSON, []);
        $this->arrows = $c->get("pages");
        $this->maxminas = $c->get("minas-por-tag");
        $this->config = $c->get("config");
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->money = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
        $this->cash = $this->getServer()->getPluginManager()->getPlugin("Cash");
        $this->loadMinas();
        $this->getScheduler()->scheduleRepeatingTask(new TimeMinaUpdate($this), 20);
    }

    public function getUniqueId(): string
    {
        return rand(-99999, 99999) . "_eid";
    }

    public function processMinaTimes()
    {
        foreach ($this->mines as $id => $mine) {
            if ($mine instanceof Mina) {

                if ($mine->getResetTime() <= 0) {
                    $l = $mine->getLevel();
                    if ($l instanceof Level) {
                        $center = $mine->getCenter();
                        $c = $l->getChunk($center->getX() >> 4, $center->getZ() >> 4);
                        if ($c instanceof Chunk) {
                            $this->makeMina($center, $mine->getSize(), $mine);
                        }
                    }
                    $mine->setResetTime(-1);
                } else {
                    $mine->setResetTime($mine->getResetTime() - 1);
                }
            }
        }
    }

    public function onDisable()
    {
        $all = [];
        foreach ($this->mines as $id => $mina) {
            if ($mina instanceof Mina) {
                $all[$mina->getOwner()][$id] = $mina->getData();
            }
        }
        $d = $this->miness;
        $d->setAll($all);
        $d->save();
        parent::onDisable();
    }


    public function onCommand(CommandSender $p, Command $c, string $label, array $a): bool
    {
        if (in_array(strtolower($c->getName()), ["mina", "mymina"]) and $p instanceof Player) {
            $this->setPage($p, 0);
        }
        return true;
    }

    public function setPage(Player $p, int $page = 0): void
    {
        if ($page < 0) $page = 0;
        $this->pages[$p->getName()] = $page;
        $form = new SimpleForm([$this, "onSelect"]);
        $form->setTitle("Selecionar Mina");
        if ($page > 0) {
            $form->addButton("Pagina Anterior", 0, "textures/items/arrow", "back");
        }
        $i = 1;
        $max = 8;
        $slot = 10;
        $current = 1;
        $start = 1;
        if ($page > 0) {
            $start = (8 * $page);
        }
        foreach ($this->minas as $mina => $arr) {
            if ($i > $max or $current < $start) {
                $current++;
                continue;
            }
            $form->addButton($arr["name"], 0, $arr["imagem"], $mina);
            $i++;
            $slot++;
            $current++;
        }
        $form->addButton("Proxima Pagina", 0, "textures/items/arrow", "proxima");
        $form->sendToPlayer($p);
    }

    public function onSelect(Player $p, $id)
    {
        if (is_null($id)) return;
        if ($id == "proxima") {
            $this->setPage($p, $this->getPage($p) + 1);
        } elseif ($id == "back") {
            $this->setPage($p, $this->getPage($p) - 1);
        } else {
            $arr = $this->minas[$id];
            $p->setBuyMina($id);
            $form = new SimpleForm([$this, "onBuy"]);
            $form->setTitle("Comprar Mina de " . ucfirst($id));
            $form->setContent(TextFormat::colorize("&6&lInformações: \n\n&r&cTipo de Mina: &b" . ucfirst($id) . "\n&cTamanho: &b" . $this->config["default-mine-size"] . "\n\n&6&lMetódo de pagamento:"));
            $form->addButton("Comprar Por Cash\n$ " . $arr["cash"], 0, "textures/items/gold_ingot", "cash");
            $form->addButton("Comprar Por Coins\n$ " . $arr["money"], 0, "textures/items/iron_ingot", "coins");
            $form->addButton("Cancelar Compra", 0, "textures/blocks/barrier", "cancel");
            $form->sendToPlayer($p);
        }
    }

    public function onBuy(Player $p, $id)
    {
        if ($id == null) return;
        if ($id == "cancel") {
            $this->setPage($p);
            $p->setBuyMina(null);
            return;
        }
        if ($this->config["eval-verifycation"] !== "-") {
            eval($this->config["eval-verifycation"]);
        }
        if ($p->hasBuyMina()) {

            $arr = $this->minas[$p->getBuyMina()];

            $n = $p->getName();
            if ($id == "cash") {
                $c = $this->cash;
                $rc = $arr["cash"];
                if ($c->myCash($n) >= $rc) {
                    $c->removeCash($n, $rc);
                } else {
                    $p->sendMessage(TextFormat::colorize("&l&6Mina >&r &cVoce nao tem cash sulficiente!"));
                    return;
                }
            } else {
                $m = $this->money;
                $rm = $arr["money"];
                if ($m->myMoney($n) >= $rm) {
                    $m->reduceMoney($n, $rm);
                } else {
                    $p->sendMessage(TextFormat::colorize("&l&6Mina >&r &cVoce nao tem Coins sulficientes"));
                    return;
                }
            }
            if ($this->processBuy($p, $p->getBuyMina())) {
                $p->sendMessage(TextFormat::colorize("&l&6Mina >&r &aMina comprada com sucesso"));
            } else {
                $p->sendMessage(TextFormat::colorize("&l&6Mina >&r &cSeu inventario esta cheio, Mina dropada no chao!"));
            }
        } else {
            $p->sendMessage("Error ao obter informaçoes da mina!");
        }
    }

    public function processBuy(Player $p, string $type): bool
    {
        $arr = $this->minas[$type];
        $i = Item::get($arr["id"], $arr["damage"]);
        $i->setCustomName("a");
        $n = TextFormat::colorize("&6Mina de " . ucfirst($type) . "\n\n&eTamanho: &7" . $this->config["default-mine-size"] . "\n&eJa Usada: &cNao");
        $i->setCustomName($n);
        $tag = $i->getNamedTag();
        $tag->setString("type", $type);
        $tag->setString("size", $this->config["default-mine-size"]);
        $tag->setString("saved-config", "-");
        $i->setNamedTag($tag);
        $inv = $p->getInventory();
        if ($inv->canAddItem($i)) {
            $inv->addItem($i);
            return true;
        } else {
            $p->getLevel()->dropItem($p, $i);
        }
        return false;
    }

    public function onMinePlace(PlayerInteractEvent $e)
    {
        if ($e->isCancelled()) return;
        $p = $e->getPlayer();
        $i = $e->getItem();
        $tag = $i->getNamedTag();
        if ($tag->hasTag("type")) {
            $b = $e->getBlock();
            if ($b->getId() !== 0) {
                $t = explode("x", $tag->getString("size"));

                if (!$this->verifyArea($b, $t[0], $t[1], $t[2])) {
                    $p->sendMessage(TextFormat::colorize("&l&6Mina >&r &cA Area que voce esta tem blocos que a mina nao pode remover!"));
                    $e->setCancelled();
                    return;
                }
                $type = $tag->getString("type");
                $this->processMina($p, $type, $b, $tag->getString("saved-config"));
                $i->setCount($i->getCount() - 1);
                $e->setCancelled(true);
                $p->getInventory()->setItemInHand($i);
            } else {
                $p->sendMessage(TextFormat::colorize("&l&6Mina >&r &cVoce deve clicar em um bloco para colocar a mina"));
            }
        }
    }

    public function processMina(Player $p, string $type, Position $center, string $savedId)
    {
        $isSavedMine = false;
        if ($savedId == "-") {
            $arr = $this->minas[$type];
            $a = [];
            $a["blocks"] = $arr["blocks"];
            $a["owner"] = $p->getName();
            $a["size"] = $this->config["default-mine-size"];
            $a["members"] = [];
            $a["bid"] = $arr["id"];
            $a["bdm"] = $arr["damage"];
            $a["public"] = false;
            $a["type"] = $type;
            $a["id"] = $this->getUniqueId();
            $a["maxdrops"] = 1000;
            $a["default-reset"] = $a["reset"] = 60 * 5;
            $a["inventory"] = [];
            $t = explode("x", $this->config["default-mine-size"]);
            $pos1 = $center->add(($t[0] / 2) + 1, 1, ($t[2] / 2) + 1);
            $pos2 = $center->add(-(($t[0] / 2) + 1), -$t[1], -(($t[2] / 2) + 1));
            $a["pos1"] = $pos1->getFloorX() . "_" . $pos1->getFloorY() . "_" . $pos1->getFloorZ();
            $a["pos2"] = $pos2->getFloorX() . "_" . $pos2->getFloorY() . "_" . $pos2->getFloorZ();
            $a["level"] = $p->getLevel()->getName();
            $a["center"] = $center->getFloorX() . "_" . $center->getFloorY() . "_" . $center->getFloorZ();
        } else {
            $a = $this->savedmines->get($savedId);
            $this->savedmines->remove($savedId);
            $this->savedmines->save();
            $isSavedMine = true;
            $t = explode("x", $a["size"]);
            $pos1 = $center->add(($t[0] / 2) + 1, 1, ($t[2] / 2) + 1);
            $pos2 = $center->add(-(($t[0] / 2) + 1), -$t[1], -(($t[2] / 2) + 1));
            $a["pos1"] = $pos1->getFloorX() . "_" . $pos1->getFloorY() . "_" . $pos1->getFloorZ();
            $a["pos2"] = $pos2->getFloorX() . "_" . $pos2->getFloorY() . "_" . $pos2->getFloorZ();
            $a["level"] = $p->getLevel()->getName();
            $a["center"] = $center->getFloorX() . "_" . $center->getFloorY() . "_" . $center->getFloorZ();
        }
        $mina = new Mina($this, $a);
        $this->makeMina($center, $mina->getSize(), $mina, $isSavedMine, $isSavedMine);
        $p->addMina($mina);
        $this->addMina($p, $mina->getData());
        $this->mines[$mina->getId()] = $mina;
        return true;
    }

    public function addMina(Player $p, array $data)
    {
        $n = strtolower($p->getName());
        $d = $this->miness;
        $arr = [];
        if ($d->exists($n)) {
            $arr = $d->get($n);
        }
        $arr[$data["id"]] = $data;
        $d->set($n, $arr);
        $d->save();
    }

    public function loadMinas()
    {
        $d = $this->miness;
        foreach ($d->getAll() as $n => $id) {
            foreach ($d->get($n) as $id => $arr) {
                $mina = new Mina($this, $arr);
                $this->mines[$arr["id"]] = $mina;
            }
        }
    }

    public function onInteract(PlayerInteractEvent $e)
    {
        if ($e->isCancelled()) return;
        $b = $e->getBlock();
        if ($b->getId() == 54) {
            if ($this->verifyInteraction($e)) {
                if ($b->getDamage() == 3) {
                    $m = $this->verifyInteraction($e, true);
                    if ($m instanceof Mina) {
                        $e->getPlayer()->setIntMine($m);
                        $e->setCancelled();
                        $this->sendMineUi($e->getPlayer(), "start");
                    }
                }
            }
        }
    }

    public function upgradeMina(Player $p)
    {
        $m = $p->getIntMina();
        $a = $m->getData();
        $center = $m->getCenter();
        $t = explode("x", $m->getSize());
        $pos1 = $center->add(($t[0] / 2) + 1, 1, ($t[2] / 2) + 1);
        $pos2 = $center->add(-(($t[0] / 2) + 1), -$t[1], -(($t[2] / 2) + 1));
        $a["pos1"] = $pos1->getFloorX() . "_" . $pos1->getFloorY() . "_" . $pos1->getFloorZ();
        $a["pos2"] = $pos2->getFloorX() . "_" . $pos2->getFloorY() . "_" . $pos2->getFloorZ();
        $m->setData($a);
        $m->onDelete(true);
        $this->makeMina($m->getCenter(), $m->getSize(), $m);
    }

    public function onMineResponse(Player $p, $d): void
    {
        if (is_null($d)) return;
        $m = $p->getIntMina();

        $f = $p->getForm();
        if (is_array($p->getMixed())) {
            $a = $p->getMixed();
            if (isset($a["type"]) and isset($a["arr"])) {
                if ($d == "upgrades") {
                    $this->sendMineUi($p, $d);
                    return;
                }
                $type = $a["type"];
                if ($m->canUpgrade($type, $m->getUpgradeLevel($type) + 1) == false) {
                    $p->sendMessage(TextFormat::colorize("&l&6Mina >&r &eEsse upgrade ja esta no nivel maximo"));
                    return;
                }
                $arr = $a["arr"];
                if ($type == "size" and !$this->verifyBorder($m->getCenter(), $m->getNextSize())) {
                    $p->sendMessage(TextFormat::colorize("&l&6Mina >&r &cA Mina vai ocupar um espaço que contem blocos que nao podem ser alterados!"));
                    return;
                }
                if ($d == "money" and $this->processEconomy($p, $arr["money"]) == true or $d == "cash" and $this->processEconomy($p, $arr["cash"], false) == true) {
                    if ($type == "size") $this->makeMina($m->getCenter(), $m->getSize(), $m, true);
                    $m->setUpgradeLevel($type, $m->getUpgradeLevel($type) + 1, $this);
                    if ($type == "size") $this->upgradeMina($p);
                    $p->sendMessage(TextFormat::colorize("&l&6Mina >&r &aUpgrade foi feito com sucesso"));
                } else {
                    $p->sendMessage(TextFormat::colorize("&l&6Mina >&r &cVoce nao tem $d sulficiente!"));
                }
            }
        }
        if ($f instanceof CustomForm) {
            $t = $f->getTitle();
            if ($t == "Adicionar Membros") {
                if (!is_null($d["nome"])) {
                    if (($pp = $this->getServer()->getPlayer($d["nome"])) instanceof Player) {
                        $d["nome"] = $pp->getName();
                    }
                    $m->addMember($d["nome"]);
                    $p->sendMessage(TextFormat::colorize("&l&6Mina >&r &aO Jogador &e" . $d["nome"] . "&a foi adicionado!"));
                }
            }
        }
        if ($f instanceof SimpleForm) {
            if ($f->getTitle() == "Inventario de drops") {
                $this->sendMineUi($p, "get_drops");
            }
            if ($f->getTitle() == "Remover Membros") {
                if ($m->isMember($d)) {
                    $m->removeMember($d);
                    $p->sendMessage(TextFormat::colorize("&l&6Mina >&r &aJogador &e$d &anao e mais membro"));
                    $this->sendMineUi($p, "m_remove");
                }
            }
        }
        switch ($d) {
            case "upgrades":
            case "start":
            case "inv":
            case "perm":
            case "m_add":
            case "m_remove":
            case "get_drops":
            case "move":
                $this->sendMineUi($p, $d);
                break;
            case "drops":
            case "limit":
            case "size":
                $p->setMixed($d);
                $this->sendMineUi($p, $d);
                break;
            case "m_public":
                $m->setPublic($m->isPublic() ? false : true);
                $this->sendMineUi($p, "perm");
                break;
        }
    }

    public function onInvResponse(Player $p, $d)
    {
        if (is_null($d)) return;
        if ($d == "start") {
            return $this->sendMineUi($p);
        }
        $this->sendMineUi($p, "get_drops", $d);
    }

    public function recolherItem(Player $p, $d)
    {
        if (is_null($d)) return;
        $m = $p->getIntMina();
        $count = $d["quantia"];
        $n = $p->getMixed();
        if ($count > $m->getItemCount($n)) {
            $p->sendMessage("Quantidade maior do que voce tem na mina!");
            return;
        }
        $i = $m->removeItem($n, $count);
        if ($p->getInventory()->canAddItem($i)) {
            $p->getInventory()->addItem($i);
        } else {
            $p->getLevel()->dropItem($p, $i);
        }
    }


    public function modalResponse(Player $p, $d)
    {
        if (is_null($d)) return;
        switch ((bool)$d) {
            case true:
                $m = $p->getIntMina();
                $arr = $m->getData();
                $mid = $m->getId();
                $this->savedmines->set($mid, $m->getData());
                $this->savedmines->save();
                $i = Item::get($arr["bid"], $arr["bdm"]);
                $i->setCustomName("a");
                $n = TextFormat::colorize("&6Mina de " . $m->getType() . "\n\n&eTamanho: &7" . $m->getSize() . "\n&eJa Usada: &aSim");
                $i->setCustomName($n);
                $tag = $i->getNamedTag();
                $tag->setString("type", $m->getType());
                $tag->setString("size", $m->getSize());
                $tag->setString("saved-config", $mid);
                $i->setNamedTag($tag);
                $inv = $p->getInventory();
                if ($inv->canAddItem($i)) {
                    $inv->addItem($i);
                } else {
                    $p->getLevel()->dropItem($p, $i);
                    $p->sendMessage(TextFormat::colorize("&cInventario cheio! mina foi dropada"));
                }
                $this->makeMina($m->getCenter(), $m->getSize(), $m, true);
                $m->onDelete();
                unset($this->mines[$m->getId()]);

                if ($this->miness->exists($m->getOwner())) {
                    $a = $this->miness->get($m->getOwner());
                    if (isset($a[$m->getId()])) {
                        unset($a[$m->getId()]);
                    }
                    $this->miness->set($m->getOwner(), $a);
                    $this->miness->save();
                }
                $p->sendMessage(TextFormat::colorize("&aMina foi Retirada"));
                break;
            case false:
                $this->sendMineUi($p);
                break;
        }
    }

    public function sendMineUi(Player $p, string $page = "start", $required = null)
    {
        $m = $p->getIntMina();
        $form = null;
        $p->setMixed(null);
        switch ($page) {
            case "start":
                $form = new SimpleForm([$this, "onMineResponse"]);
                $form->setTitle("Menu da Mina");
                $reset = floor((float)($m->getResetTime() / 60));
                $form->setContent(TextFormat::colorize("&aReseta em: &e$reset Minuto(s)\n\n&6&lEscolha uma opção:\n"));
                $form->addButton("Inventário da Mina", 0, "textures/ui/emote_wheel_base", "inv");
                $form->addButton("Upgrades da Mina", 0, "textures/ui/anvil_icon", "upgrades");
                $form->addButton("Permissões da Mina", 0, "textures/ui/dressing_room_skins", "perm");
                $form->addButton("Mover a Mina", 0, "textures/ui/cartography_table_zoom", "move");
                $form->sendToPlayer($p);
                break;
            case "upgrades":
                $form = new SimpleForm([$this, "onMineResponse"]);
                $form->setTitle("Menu de upgrades");
                $form->setContent(TextFormat::colorize("\n&6Selecione um upgrade:"));
                $form->addButton(TextFormat::colorize("&lMultiplicação de Drops&r\nLevel: " . $m->getUpgradeLevel("drops") . $m->getFull("drops")), 0, "textures/ui/dust_selectable_3", "drops");
                $form->addButton(TextFormat::colorize("&lLimite de Inventário&r\nLevel: " . $m->getUpgradeLevel("limit") . $m->getFull("limit")), 0, "textures/ui/icon_blackfriday", "limit");
                $form->addButton(TextFormat::colorize("&lTamanho da Mina&r\nLevel: " . $m->getUpgradeLevel("size") . $m->getFull("size")), 0, "textures/ui/move", "size");
                $form->addButton("Voltar", 0, "textures/blocks/barrier", "start");
                $form->sendToPlayer($p);
                break;
            case "drops":
            case "limit":
            case "size":
                $form = new SimpleForm([$this, "onMineResponse"]);
                $form->setTitle("Upgrade de " . ucfirst($page));
                $arr = $this->config["upgrades"][$page][$m->getUpgradeLevel($page)];
                $p->setMixed(["type" => $page, "arr" => $arr]);
                $desc = $this->config["upgrades"][$page]["desc"];
                $form->setContent(TextFormat::colorize("$desc\n\n&6&lSelecione o metódo de pagamento:"));
                $form->addButton(TextFormat::colorize("Comprar Por Cash\n&c$ &6" . $arr["cash"]), 0, "textures/items/gold_ingot", "cash");
                $form->addButton(TextFormat::colorize("Comprar Por Coins\n&c$ &6" . $arr["money"]), 0, "textures/items/iron_ingot", "money");
                $form->addButton("Voltar", 0, "textures/blocks/barrier", "upgrades");
                $form->sendToPlayer($p);
                break;
            case "inv":
                $form = new SimpleForm([$this, "onInvResponse"]);
                $form->setTitle("Inventário de drops");
                $form->setContent(TextFormat::colorize("&6Informações: \n&eTotal de Drops: &c" . $m->getTotalDrops() . "\n&eLimite de Drops: &c" . $m->getMaxDrops() . "\n\n&6Escolha um item para recolher:"));
                foreach ($m->getMineInventory() as $name => $arr) {
                    $nn = strtolower(str_replace(" ", "_", $name));
                    $form->addButton("$name \n {$arr["count"]} Drops", 0, "textures/blocks/$nn", $name);
                }
                $form->addButton("Voltar", 0, "textures/blocks/barrier", "start");
                $form->sendToPlayer($p);
                break;
            case "perm":
                if ($m->isMember($p->getName())) {
                    $p->sendMessage(TextFormat::colorize("&6&lMina > &cMembros nao podem alterar essas opções!"));
                    break;
                }
                $form = new SimpleForm([$this, "onMineResponse"]);
                $form->setTitle("Permissões da Mina");
                $form->addButton("Adicionar Membro", 0, "textures/ui/dressing_room_capes", "m_add");
                $form->addButton("Remover Membro", 0, "textures/ui/warning_alex", "m_remove");
                $public = "Privada";
                if ($m->isPublic()) $public = "Publica";
                $form->addButton("Mina: $public", 0, "textures/ui/World", "m_public");
                $form->addButton("Voltar", 0, "textures/blocks/barrier", "start");
                $form->sendToPlayer($p);
                break;
            case "move":
                if ($m->isMember($p->getName())) {
                    $p->sendMessage(TextFormat::colorize("&6&lMina > &cMembros nao podem alterar essas opções!"));
                    break;
                }
                $form = new ModalForm([$this, "modalResponse"]);
                $form->setTitle("Remover Mina");
                $form->setContent(TextFormat::colorize("&e&lDeseja realmente remover?\n\n&r&6> Quando a mina for &ccolocada novamante &6ela estará &csem blocos &6e irá resetar no &ctempo atual dessa mina&6, os upgrades e inventario estaram &csalvos &6e sera recuperado assim que for &ccolocada novamente&6!"));
                $form->setButton1("Continuar");
                $form->setButton2("Cancelar");
                $form->sendToPlayer($p);
                break;
            case "m_add":
                $form = new CustomForm([$this, "onMineResponse"]);
                $form->setTitle("Adicionar Membros");
                $form->addInput("Nome do Jogador", "", "", "nome");
                $form->sendToPlayer($p);
                $p->setForm($form);
                break;
            case "m_remove":
                $form = new SimpleForm([$this, "onMineResponse"]);
                $form->setTitle("Remover Membros");
                foreach ($m->getMembers() as $n) {
                    $form->addButton(ucfirst($n), 0, "textures/ui/", $n);
                }
                $form->addButton("Voltar", 0, "textures/blocks/barrier", "perm");
                $form->sendToPlayer($p);
                $p->setForm($form);
                break;
            case "get_drops":
                $form = new CustomForm([$this, "recolherItem"]);
                $form->setTitle("Recolher Item");
                $form->addLabel("Recolher $required \n\nQual quantidade?");
                $form->addSlider("Quantia", 1, $m->getItemCount($required), 1, 1, "quantia");
                $p->setMixed($required);
                $form->sendToPlayer($p);
                break;
        }
        $p->setForm($form);
    }

    /**
     * @param BlockBreakEvent $e
     * @priority MONITOR
     */
    public function onBreak(BlockBreakEvent $e)
    {
        if ($e->isCancelled()) return;
        if ($this->verifyInteraction($e)) {
            $m = $this->verifyInteraction($e, true);
            if ($m instanceof Mina) {
                if ($m->compareBlock($e->getBlock())) {
                    $drops = $e->getDrops();
                    $e->setDrops([]);
                    if (isset($drops[0]) and ($i = $drops[0]) instanceof Item) {
                        if (!$m->addItemInventory($i)) {
                            $e->getPlayer()->sendTip(TextFormat::colorize("&6&lMina > &cInventario da mina esta cheio"));
                        }
                    }
                }
            }
        }
    }

    public function onPlace(BlockPlaceEvent $e)
    {
        if ($e->isCancelled()) return;
        if ($this->verifyInteraction($e)) {

        }
    }

    private function verifyInteraction(Event $e, bool $r = false)
    {
        if ($e instanceof BlockBreakEvent or $e instanceof BlockPlaceEvent or $e instanceof PlayerInteractEvent or $e instanceof EntityDamageEvent) {
            if ($e instanceof EntityDamageEvent) {
                $p = $e->getEntity();
                $b = $p;
            } else {
                $p = $e->getPlayer();
                $b = $e->getBlock();
            }
            foreach ($this->mines as $mine) {

                if ($mine instanceof Mina) {

                    if ($mine->isinArea($b)) {

                        if ($r) return $mine;
                        if (!$mine->isPublic() and $mine->getOwner() !== strtolower($p->getName())) {
                            if (!$mine->isMember($p->getName())) {
                                $e->setCancelled();
                                $p->sendMessage(TextFormat::colorize("&6&lMina > &cVoce nao tem permissao de alterar esta area!"));
                                return false;
                            }
                        }
                    }
                }
            }
        }
        return true;
    }

    public function verifyArea(Position $pos, $x, $y, $z): bool
    {
        $xx = $x / 2 + 1;
        $zz = $z / 2 + 1;
        $l = $pos->getLevel();
        $rblocks = $this->config["mina-replace-blocks"];
        for ($xi = 0; $xi < $xx; $xi++) {
            for ($zi = 0; $zi < $zz; $zi++) {
                for ($yi = 0; $yi < $y; $yi++) {
                    $id = $l->getBlock($pos->add($xi, -$yi, $zi))->getId();
                    if (!in_array($id, $rblocks)) {
                        return false;
                    }
                    $id = $l->getBlock($pos->add(-$xi, -$yi, -$zi))->getId();
                    if (!in_array($id, $rblocks)) {
                        return false;
                    }
                    $id = $l->getBlock($pos->add(-$xi, -$yi, $zi))->getId();
                    if (!in_array($id, $rblocks)) {
                        return false;
                    }
                    $id = $l->getBlock($pos->add($xi, -$yi, -$zi))->getId();
                    if (!in_array($id, $rblocks)) {
                        return false;
                    }
                }
            }
        }
        return $this->verifyBorder($pos, $x, $y, $z);
    }

    public function verifyBorder($pos, $x, $y = null, $z = null): bool
    {
        if (is_string($x) and is_null($z)) {
            $t = explode("x", $x);
            $x = $t[0];
            $y = $t[1];
            $z = $t[2];
        }
        $xx = $x / 2 + 1;
        $zz = $z / 2 + 1;
        $l = $pos->getLevel();
        $pmx = $pos->add($xx);
        $psx = $pos->add(-$xx);
        $pmz = $pos->add(0, 0, $zz);
        $psz = $pos->add(0, 0, -$zz);
        $xx = $x / 2 + 2;
        $zz = $z / 2 + 2;
        $ids = [];
        $rblocks = $this->config["mina-replace-blocks"];
        for ($xi = 0; $xi < $xx; $xi++) {
            for ($zi = 0; $zi < $zz; $zi++) {
                for ($yi = 0; $yi < $y; $yi++) {
                    $ids[] = $l->getBlock($pmx->add(0, -$yi, $zi))->getId();
                    $ids[] = $l->getBlock($pmx->add(0, -$yi, -$zi))->getId();
                    $ids[] = $l->getBlock($psx->add(0, -$yi, $zi))->getId();
                    $ids[] = $l->getBlock($psx->add(0, -$yi, -$zi))->getId();
                    $ids[] = $l->getBlock($pmz->add($xi, -$yi))->getId();
                    $ids[] = $l->getBlock($pmz->add(-$xi, -$yi))->getId();
                    $ids[] = $l->getBlock($psz->add($xi, -$yi))->getId();
                    $ids[] = $l->getBlock($psz->add(-$xi, -$yi))->getId();

                    $ids[] = $l->getBlock($pos->add($xi, -$y, $zi))->getId();
                    $ids[] = $l->getBlock($pos->add(-$xi, -$y, -$zi))->getId();
                    $ids[] = $l->getBlock($pos->add(-$xi, -$y, $zi))->getId();
                    $ids[] = $l->getBlock($pos->add($xi, -$y, -$zi))->getId();
                }
            }
        }
        foreach ($ids as $id) {
            if (!in_array($id, $rblocks)) {
                return false;
            }
        }
        return true;
    }

    public function makeMina($pos, string $size, Mina $m, bool $reset = false, bool $ignorePlace = false): array
    {
        $t1 = true;
        $t2 = false;
        if ($this->config["anti-lag"] == "true" or (bool)$this->config["anti-lag"]) {
            $t1 = false;
        }
        $t = explode("x", $size);
        $x = $t[0];
        $y = $t[1];
        $z = $t[2];
        $xx = $x / 2 + 1;
        $zz = $z / 2 + 1;
        if ($this->config["save-mine-blocks"] == "true" or (bool)$this->config["save-mine-blocks"]) {
            $l = new SaveBlock($pos->getLevel(), $m, $ignorePlace ? false : $reset);
        } else {
            $l = $pos->getLevel();
        }
        for ($xi = 0; $xi < $xx; $xi++) {
            for ($zi = 0; $zi < $zz; $zi++) {
                for ($yi = 0; $yi < $y; $yi++) {
                    $l->setBlock($pos->add($xi, -$yi, $zi), $m->getRandBlock($reset), $t1, $t2);
                    $l->setBlock($pos->add(-$xi, -$yi, -$zi), $m->getRandBlock($reset), $t1, $t2);
                    $l->setBlock($pos->add(-$xi, -$yi, $zi), $m->getRandBlock($reset), $t1, $t2);
                    $l->setBlock($pos->add($xi, -$yi, -$zi), $m->getRandBlock($reset), $t1, $t2);
                }
            }
        }
        $id = $reset === false ? 7 : 0;
        if ($ignorePlace) {
            $id = 7;
        }
        $this->makeBorder($pos, $x, $y, $z, $id, $t1, $t2, $l);
        return [$xx, $y, $zz];
    }

    public function makeBorder($pos, $x, $y, $z, $id = 7, $t1 = true, $t2 = false, $l = null)
    {
        $xx = $x / 2 + 1;
        $zz = $z / 2 + 1;
        $pmx = $pos->add($xx);
        $psx = $pos->add(-$xx);
        $pmz = $pos->add(0, 0, $zz);
        $psz = $pos->add(0, 0, -$zz);
        $xx = $x / 2 + 2;
        $zz = $z / 2 + 2;
        $bau = Block::get($id == 7 ? 54 : 0);
        $bau->setDamage(3);
        $l->setBlock($pos->add(($xx - 1), 1), $bau, $t1, $t2);
        for ($xi = 0; $xi < $xx; $xi++) {
            for ($zi = 0; $zi < $zz; $zi++) {
                for ($yi = 0; $yi < $y; $yi++) {
                    $l->setBlock($pmx->add(0, -$yi, $zi), Block::get($id), $t1, $t2);
                    $l->setBlock($pmx->add(0, -$yi, -$zi), Block::get($id), $t1, $t2);
                    $l->setBlock($psx->add(0, -$yi, $zi), Block::get($id), $t1, $t2);
                    $l->setBlock($psx->add(0, -$yi, -$zi), Block::get($id), $t1, $t2);
                    $l->setBlock($pmz->add($xi, -$yi), Block::get($id), $t1, $t2);
                    $l->setBlock($pmz->add(-$xi, -$yi), Block::get($id), $t1, $t2);
                    $l->setBlock($psz->add($xi, -$yi), Block::get($id), $t1, $t2);
                    $l->setBlock($psz->add(-$xi, -$yi), Block::get($id), $t1, $t2);

                    $l->setBlock($pos->add($xi, -$y, $zi), Block::get($id), $t1, $t2);
                    $l->setBlock($pos->add(-$xi, -$y, -$zi), Block::get($id), $t1, $t2);
                    $l->setBlock($pos->add(-$xi, -$y, $zi), Block::get($id), $t1, $t2);
                    $l->setBlock($pos->add($xi, -$y, -$zi), Block::get($id), $t1, $t2);
                }
            }
        }
        if ($l instanceof SaveBlock) {
            $l->ok();
        }
    }

    public $pages = [];

    public function getPage(Player $p): int
    {
        return $this->pages[$p->getName()] ?? 0;
    }

    public function getId(Item $item, string $tag = "id", $type = ""): string
    {
        $tag = $item->getNamedTag();

        if (!$tag->hasTag($tag)) {
            return "-";
        }
        if (is_string($type)) {
            return $tag->getString($tag);
        }
        return $tag->getInt($tag) . "";
    }

    public function getMaxMinas(Player $p): int
    {
        $pp = $this->getServer()->getPluginManager()->getPlugin("PurePerms");
        if ($pp == null) {
            return 5;
        }
        $tag = $pp->getUserDataMgr()->getGroup($p)->getName();
        return $this->maxminas[$tag] ?? 5;
    }

    public function onCreateP(PlayerCreationEvent $e)
    {
        $e->setPlayerClass(PlayerData::class);
    }

    public function processEconomy(Player $p, int $price, $money = true): bool
    {
        if ($money) {
            if ($this->money->myMoney($p->getName()) < $price) {
                return false;
            }
            $this->money->reduceMoney($p->getName(), $price);
            return true;
        } else {
            if ($this->cash->myCash($p->getName()) < $price) {
                return false;
            }
            $this->cash->removeCash($p->getName(), $price);
            return true;
        }
    }

    public function onSufoque(EntityDamageEvent $e)
    {

        if ($e->getCause() == EntityDamageEvent::CAUSE_SUFFOCATION and ($p = $e->getEntity()) instanceof Player) {

            if ($this->verifyInteraction($e)) {
                $e->setCancelled();
                $m = $this->verifyInteraction($e, true);
                if ($m instanceof Mina) {
                    $p->teleport($m->getCenter()->add(0, 1));
                }
            }
        }
    }
}
class TimeMinaUpdate extends Task{

    /**
     * @var Main
     */
    private $m;

    public function __construct(Main $m){
        $this->m = $m;
    }
    public function onRun(int $currentTick){
        $this->m->processMinaTimes();
    }
}
class SaveBlock
{

    /**
     * @var Level
     */
    private $l;
    /**
     * @var Mina
     */
    private $mina;
    /**
     * @var bool
     */
    private $reset;

    public function __construct(Level $l, Mina $m, bool $reset = false)
    {
        $this->l = $l;
        $this->mina = $m;
        $this->reset = $reset;
    }

    public function setBlock($pos, Block $b, $a, $c)
    {
        if (!$this->reset) {
            $this->mina->saveBlock($pos, $this->l->getBlock($pos));
            $this->l->setBlock($pos, $b, $a, $c);
        } else {
            $b = $this->mina->getSavedBlock($pos);
            if ($b == null) $b = Block::get(0);
            $this->l->setBlock($pos, $b, $a, $c);
        }
    }

    public function ok()
    {
        $this->mina->ok();
    }
}