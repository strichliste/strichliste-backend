<?php

namespace App\Service;

use App\Exception\ParameterNotFoundException;

class SettingsService {

    function __construct(private array $strichlisteSettings) {
    }

    function getAll(): array {
        return $this->strichlisteSettings;
    }

    function getOrDefault(string $path, mixed $default = null): mixed {
        try {
            return $this->get($path);
        } catch (ParameterNotFoundException $e) {
            return $default;
        }
    }

    /**
     * @throws ParameterNotFoundException
     */
    function get(string $path): mixed {
        $settings = $this->strichlisteSettings;
        foreach (explode('.', $path) as $part) {
            if (!isset($settings[$part])) {
                throw new ParameterNotFoundException($path);
            }
            $settings = $settings[$part];
        }
        return $settings;
    }
}