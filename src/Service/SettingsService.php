<?php

namespace App\Service;

use App\Exception\ParameterNotFoundException;

class SettingsService {
    public function __construct(private readonly array $strichlisteSettings) {}

    /**
     * @return array
     */
    public function getAll() {
        return $this->strichlisteSettings;
    }

    /**
     * @param null|mixed $default
     *
     * @return null|array|mixed
     */
    public function getOrDefault(string $path, $default = null) {
        try {
            return $this->get($path);
        } catch (ParameterNotFoundException) {
            return $default;
        }
    }

    /**
     * @return array|mixed
     *
     * @throws ParameterNotFoundException
     */
    public function get(string $path) {
        $parts = explode('.', $path);

        $settings = $this->settings;
        foreach ($parts as $part) {
            if (!isset($settings[$part])) {
                throw new ParameterNotFoundException($path);
            }

            $settings = $settings[$part];
        }

        return $settings;
    }
}
