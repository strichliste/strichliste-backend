<?php

namespace App\Service;

use App\Exception\ParameterNotFoundException;

class SettingsService
{
    /**
     * @param array<string, mixed> $strichlisteSettings
     */
    public function __construct(private readonly array $strichlisteSettings)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        return $this->strichlisteSettings;
    }

    public function getOrDefault(string $path, mixed $default = null): mixed
    {
        try {
            return $this->get($path);
        } catch (ParameterNotFoundException) {
            return $default;
        }
    }

    /**
     * @throws ParameterNotFoundException
     */
    public function get(string $path): mixed
    {
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
