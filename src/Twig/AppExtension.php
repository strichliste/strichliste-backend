<?php

namespace App\Twig;

use App\Service\SettingsService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension {

    public function __construct(private SettingsService $settings) {
    }

    public function getFilters(): array {
        return [
            new TwigFilter('currency_format', [$this, 'currencyFormat']),
            new TwigFilter('balance_class', [$this, 'balanceClass']),
        ];
    }

    /**
     * Settings paths templates are allowed to read via the `setting()` Twig
     * function. Sensitive keys (`paypal.recipient` and any future API keys)
     * must NOT be reachable from a template — controllers consume them via
     * `SettingsService` directly.
     */
    private const TEMPLATE_SETTING_ALLOWLIST = [
        'i18n.',
        'common.',
        'article.enabled',
        'article.autoOpen',
        'payment.deposit.',
        'payment.dispense.',
        'payment.transactions.enabled',
        'payment.splitInvoice.enabled',
        'payment.undo.enabled',
        'payment.boundary.',
        'account.boundary.',
        'paypal.enabled',
        'paypal.sandbox',
        'paypal.fee',
    ];

    public function getFunctions(): array {
        return [
            new TwigFunction('setting', function (string $path, mixed $default = null) {
                $allowed = false;
                foreach (self::TEMPLATE_SETTING_ALLOWLIST as $prefix) {
                    if ($path === $prefix || str_starts_with($path, $prefix)) {
                        $allowed = true;
                        break;
                    }
                }
                if (!$allowed) {
                    // Refuse to leak any setting outside the allowlist. Returning
                    // the default silently is intentional: templates should not
                    // ask for these paths, and an exception would be a DoS.
                    return $default;
                }
                return $this->settings->getOrDefault($path, $default);
            }),
        ];
    }

    /**
     * Format an integer cent amount as the SPA does:
     * - signed (default): `+€25.77`, `-€8.52`, `€0.00` — used for balances and tx amounts
     * - unsigned         : `€1.50` — used for list prices that aren't transactions
     */
    public function currencyFormat(?int $cents, ?string $currencySymbol = null, bool $signed = true): string {
        if ($cents === null) {
            return '';
        }
        $symbol = $currencySymbol ?: $this->settings->getOrDefault('i18n.currency.symbol', '€');
        $locale = (string) $this->settings->getOrDefault('i18n.language', 'en');

        $sign = '';
        if ($signed) {
            $sign = $cents > 0 ? '+' : ($cents < 0 ? '-' : '');
        }
        $abs = abs($cents) / 100;

        $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 2);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);
        $number = $formatter->format($abs);
        if ($number === false) {
            $number = number_format($abs, 2, '.', ',');
        }

        return $sign . $symbol . $number;
    }

    public function balanceClass(?int $cents): string {
        if ($cents === null || $cents === 0) {
            return 'is-zero';
        }
        return $cents > 0 ? 'is-positive' : 'is-negative';
    }
}
