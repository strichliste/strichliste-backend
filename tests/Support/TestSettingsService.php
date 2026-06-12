<?php

namespace App\Tests\Support;

use App\Exception\ParameterNotFoundException;
use App\Service\SettingsService;

/**
 * Test-env replacement for SettingsService (wired via when@test in
 * services.yaml). Settings can be overridden on the live instance, which
 * sidesteps an initialization-order trap: any EntityManager flush touches the
 * ux-turbo onFlush listener, which instantiates Twig and with it the settings
 * service — so by the time a test runs, the container refuses to replace it.
 */
class TestSettingsService extends SettingsService
{
    /** @var array<string, mixed> */
    private array $settings;

    /**
     * @param array<string, mixed> $strichlisteSettings
     */
    public function __construct(array $strichlisteSettings)
    {
        parent::__construct($strichlisteSettings);
        $this->settings = $strichlisteSettings;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public function setOverrides(array $overrides): void
    {
        $this->settings = self::mergeSettings($this->settings, $overrides);
    }

    /**
     * Like array_replace_recursive, except lists replace wholesale — merging
     * by integer index would turn a `steps: [100]` override into
     * [100, 100, 200, 500, 1000].
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    public static function mergeSettings(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (\is_array($value) && !array_is_list($value) && \is_array($base[$key] ?? null)) {
                $base[$key] = self::mergeSettings($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    public function getAll(): array
    {
        return $this->settings;
    }

    public function get(string $path): mixed
    {
        $settings = $this->settings;
        foreach (explode('.', $path) as $part) {
            if (!isset($settings[$part])) {
                throw new ParameterNotFoundException($path);
            }
            $settings = $settings[$part];
        }

        return $settings;
    }
}
