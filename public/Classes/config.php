<?php

namespace FPL;

class Config
{
    private array $config;

    public function __construct(string $configFile = null)
    {
        $configFile = $configFile ?? __DIR__ . '/../config.php';
        $this->config = require $configFile;
    }

    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public function all(): array
    {
        return $this->config;
    }
}