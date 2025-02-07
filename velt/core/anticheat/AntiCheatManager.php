<?php

namespace velt\core\anticheat;

use pocketmine\plugin\PluginBase;
use velt\core\anticheat\AntiFlyListener;
use velt\core\anticheat\AntiAutoClickListener;

class AntiCheatManager {
    private $plugin;

    public function __construct(PluginBase $plugin) {
        $this->plugin = $plugin;
        $this->init();
    }

    private function init() {
        new AntiFlyListener($this->plugin);
        new AntiAutoClickListener($this->plugin);
    }
}
