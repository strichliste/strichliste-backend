<?php

declare(strict_types=1);

namespace App\Runtime;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Runtime\RunnerInterface;
use Symfony\Component\Runtime\SymfonyRuntime;

/**
 * A runtime for FrankenPHP.
 *
 * Vendored from php-runtime/frankenphp-symfony (originally by Kévin Dunglas),
 * which has no Symfony 8 release and still pins Symfony 7. The code only uses
 * stable Runtime/HttpKernel APIs, so it runs unchanged on Symfony 8.
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
class FrankenPhpRuntime extends SymfonyRuntime
{
    /**
     * @param array{
     *   frankenphp_loop_max?: int,
     * } $options
     */
    public function __construct(array $options = [])
    {
        $options['frankenphp_loop_max'] = (int) ($options['frankenphp_loop_max'] ?? $_SERVER['FRANKENPHP_LOOP_MAX'] ?? $_ENV['FRANKENPHP_LOOP_MAX'] ?? 500);

        parent::__construct($options);
    }

    public function getRunner(?object $application): RunnerInterface
    {
        if ($application instanceof HttpKernelInterface && ($_SERVER['FRANKENPHP_WORKER'] ?? false)) {
            return new FrankenPhpRunner($application, $this->options['frankenphp_loop_max']);
        }

        return parent::getRunner($application);
    }
}
