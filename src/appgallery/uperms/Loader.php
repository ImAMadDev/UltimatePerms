<?php

declare(strict_types=1);

namespace appgallery\uperms;

use appgallery\uperms\chat\UPChatFormatter;
use appgallery\uperms\command\UPermsCommand;
use appgallery\uperms\group\GroupManager;
use appgallery\uperms\group\GroupRegistry;
use appgallery\uperms\locale\LocaleManager;
use appgallery\uperms\permission\node\NodeRegistry;
use appgallery\uperms\permission\PermissionResolver;
use appgallery\uperms\player\context\ContextManager;
use appgallery\uperms\player\ExpirationTask;
use appgallery\uperms\player\SessionFactory;
use appgallery\uperms\player\SessionManager;
use appgallery\uperms\storage\AsyncStorageManager;
use appgallery\uperms\storage\IAsyncStorage;
use appgallery\uperms\storage\IStorage;
use appgallery\uperms\storage\StorageFactory;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use appgallery\uperms\EventListener;

final class Loader extends PluginBase{
    private static self $instance;

    private IStorage $storage;
    private GroupManager $groupManager;
    private LocaleManager $localeManager;
    private SessionManager $sessionManager;
    private IAsyncStorage $storageManager;

    public static function getInstance(): self{
        return self::$instance;
    }

    protected function onLoad(): void{
        self::$instance = $this;
    }

    protected function onEnable(): void{
        // ── Recursos default ──────────────────────────────────────────
        $this->saveDefaultConfig();

        // ── Mensajes ──────────────────────────────────────────────────
        $this->localeManager = new LocaleManager();
        $this->localeManager->init($this->getConfig()->get('locale', 'en_US'));

        // ── Nodos de permisos del servidor ────────────────────────────
        NodeRegistry::getInstance()->load();

        // ── Storage ───────────────────────────────────────────────────
        $this->storage = StorageFactory::create(
            $this->getConfig()->getAll(),
            $this->getDataFolder()
        );
        $this->storage->init();

        $this->storageManager = new AsyncStorageManager(
            $this->getConfig()->getAll(),
            $this->getDataFolder()
        );

        // ── Grupos ────────────────────────────────────────────────────
        $registry = GroupRegistry::getInstance();
        $this->groupManager = new GroupManager($registry, $this->storage);
        $this->groupManager->loadAll();

        // ── Sesiones ──────────────────────────────────────────────────
        $resolver = new PermissionResolver();
        $contextManager = ContextManager::getInstance();
        $factory = new SessionFactory($this->groupManager);

        SessionManager::getInstance()->init(
            storage: $this->storage,
            groupManager: $this->groupManager,
            resolver: $resolver,
            contextManager: $contextManager
        );

        $this->sessionManager = SessionManager::getInstance();

        $chatTemplate = (string) $this->getConfig()->get(
            'chat-format',
            '{prefix}{name}{suffix}§r: {message}'
        );

        $nameTemplate = (string) $this->getConfig()->get(
            'name-format',
            '{prefix}{name}{suffix}'
        );

        $chatFormatter = new UPChatFormatter($this->groupManager, $this->sessionManager, $chatTemplate, $nameTemplate);

        $this->sessionManager->setChatFormatter($chatFormatter, $nameTemplate);


        // ── Eventos ───────────────────────────────────────────────────
        $this->getServer()->getPluginManager()->registerEvents(
            new EventListener($this->sessionManager, $chatFormatter),
            $this
        );

        // ── Expiración ────────────────────────────────────────────────
        $interval = (int)$this->getConfig()->get('expiration-check-interval', 60);
        $this->getScheduler()->scheduleRepeatingTask(
            new ExpirationTask($this->sessionManager, $this->groupManager, $this->storage),
            20 * $interval
        );

        // ── Comandos ──────────────────────────────────────────────────
        $this->getServer()->getCommandMap()->register(
            'uperms',
            new UPermsCommand($this->groupManager, $this->sessionManager)
        );

        $this->getLogger()->info(LocaleManager::get('boot-done'));
    }

    protected function onDisable(): void{
        $this->sessionManager->saveAll();
        $this->storage->close();
        $this->getLogger()->info(LocaleManager::get('boot-disabled'));
    }

    public function getStorage(): IStorage{
        return $this->storage;
    }

    /**
     * @return IAsyncStorage
     */
    public function getStorageManager(): IAsyncStorage{
        return $this->storageManager;
    }

    public function getGroupManager(): GroupManager{
        return $this->groupManager;
    }

    public function getSessionManager(): SessionManager{
        return $this->sessionManager;
    }
}