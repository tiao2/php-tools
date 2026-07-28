<?php

declare(strict_types=1);

namespace PhpTools\Community;

class ModuleManager
{
    private array $config;
    /** @var array<string, string[]> */
    private array $dependencies = [
        'CONTENT' => ['USER', 'ACL'],
        'API' => ['CONTENT'],
    ];

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->resolveDependencies();
    }

    public function isEnabled(string $subModule): bool
    {
        $key = 'COMMUNITY_' . strtoupper($subModule) . '_ENABLED';
        return $this->getEnvBool($key, true);
    }

    private function resolveDependencies(): void
    {
        foreach ($this->dependencies as $module => $required) {
            if ($this->isEnabled($module)) {
                foreach ($required as $req) {
                    if (!$this->isEnabled($req)) {
                        $this->config['COMMUNITY_' . $req . '_ENABLED'] = true;
                        trigger_error("Community sub-module {$req} was auto-enabled because {$module} requires it.", E_USER_WARNING);
                    }
                }
            }
        }
    }

    private function getEnvBool(string $key, bool $default): bool
    {
        if (!isset($this->config[$key])) {
            return $default;
        }
        return filter_var($this->config[$key], FILTER_VALIDATE_BOOLEAN);
    }
}