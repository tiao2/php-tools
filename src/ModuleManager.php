<?php

declare(strict_types=1);

namespace PhpTools;

class ModuleManager
{
    /** @var array */
    private array $config;

    /** @var array<string, string[]> */
    private array $dependencies = [
        'COMMUNITY' => ['SSO'],
        'API' => ['SSO'], // actual dependency only if API_AUTH_ENABLED, but we keep it simple
    ];

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->resolveDependencies();
    }

    /**
     * Check if a top-level module is enabled.
     */
    public function isEnabled(string $moduleName): bool
    {
        $key = strtoupper($moduleName) . '_ENABLED';
        return $this->getEnvBool($key, true);
    }

    /**
     * Resolve dependencies: auto-enable required modules.
     */
    private function resolveDependencies(): void
    {
        foreach ($this->dependencies as $module => $required) {
            if ($this->isEnabled($module)) {
                foreach ($required as $reqModule) {
                    if (!$this->isEnabled($reqModule)) {
                        $envKey = strtoupper($reqModule) . '_ENABLED';
                        // Auto-enable by overriding config
                        $this->config[$envKey] = true;
                        trigger_error("Module {$reqModule} was auto-enabled because {$module} requires it.", E_USER_WARNING);
                    }
                }
            }
        }
    }

    /**
     * Helper to get boolean value from env/config.
     */
    private function getEnvBool(string $key, bool $default): bool
    {
        if (!isset($this->config[$key])) {
            return $default;
        }
        return filter_var($this->config[$key], FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Return list of enabled top-level modules.
     */
    public function getEnabledModules(): array
    {
        $modules = [];
        foreach (array_keys($this->dependencies) as $module) {
            if ($this->isEnabled($module)) {
                $modules[] = $module;
            }
        }
        return $modules;
    }
}