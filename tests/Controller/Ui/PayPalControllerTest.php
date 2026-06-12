<?php

namespace App\Tests\Controller\Ui;

use Symfony\Component\HttpFoundation\UriSigner;

class PayPalControllerTest extends AbstractUiTestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = $this->createUserDb('Alice');
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function enablePaypal(array $extra = []): void
    {
        $this->overrideSettings(['paypal' => $extra + ['enabled' => true, 'recipient' => 'treasurer@example.com', 'fee' => 0]]);
    }

    /**
     * Submits the paypal tab form. Never followRedirects(): the 303 points at
     * paypal.com and the browser-kit would replay it against the kernel.
     */
    private function startTopUp(string $amount): void
    {
        $crawler = $this->client->request('GET', "/user/{$this->userId}?tab=paypal");
        $form = $crawler->filter('form[action$="/paypal/start"]')->form(['amount' => $amount]);
        $this->client->submit($form);
    }

    /**
     * One of the signed, time-limited URLs embedded in the PayPal redirect
     * ('return' or 'cancel_return'). Asserts the redirect went to PayPal, so
     * a boundary bounce back to the user page fails loudly here.
     */
    private function signedUrlFromRedirect(string $param): string
    {
        $location = $this->client->getResponse()->headers->get('Location');
        \assert(null !== $location);
        $this->assertStringContainsString('sandbox.paypal.com', $location);

        $query = parse_url($location, PHP_URL_QUERY);
        \assert(\is_string($query));
        parse_str($query, $params);
        \assert(\is_string($params[$param] ?? null));

        return $params[$param];
    }

    private function returnUrlFromRedirect(): string
    {
        return $this->signedUrlFromRedirect('return');
    }

    public function testEverythingIs404WhileDisabled(): void
    {
        // paypal.enabled defaults to false
        $this->client->request('GET', "/user/{$this->userId}?tab=paypal");
        $this->assertResponseIsSuccessful();
        $this->assertSelectorCount(0, 'form[action$="/paypal/start"]');

        $this->client->request('POST', "/user/{$this->userId}/paypal/start", ['amount' => '5.00']);
        $this->assertResponseStatusCodeSame(404);

        $this->client->request('GET', "/user/{$this->userId}/paypal/return/500?nonce=x");
        $this->assertResponseStatusCodeSame(404);
    }

    public function testDisablingPaypalKillsIssuedReturnUrls(): void
    {
        $this->enablePaypal();
        $this->startTopUp('5.00');
        $returnUrl = $this->returnUrlFromRedirect();

        // the operator pulls the feature while a member is mid-checkout: the
        // already-issued, validly signed link must die at the enabled guard
        $this->overrideSettings(['paypal' => ['enabled' => false]]);

        $this->client->request('GET', $returnUrl);
        $this->assertResponseStatusCodeSame(404);
        $this->assertUserBalance($this->userId, 0);
    }

    public function testStartRedirectsToPaypalWithSignedReturnUrl(): void
    {
        $this->enablePaypal();
        $before = time();
        $this->startTopUp('5.00');

        $this->assertResponseStatusCodeSame(303);
        $returnUrl = $this->returnUrlFromRedirect();

        $location = $this->client->getResponse()->headers->get('Location');
        \assert(null !== $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);
        $this->assertSame('5.00', $params['amount']);
        $this->assertSame('treasurer@example.com', $params['business']);
        $this->assertSame('EUR', $params['currency_code']);

        $this->assertStringContainsString("/user/{$this->userId}/paypal/return/500", $returnUrl);

        parse_str((string) parse_url($returnUrl, PHP_URL_QUERY), $returnParams);
        $this->assertArrayHasKey('nonce', $returnParams);
        $this->assertArrayHasKey('_hash', $returnParams);

        // 30 minute TTL, bracketed by wall-clock readings so a stalled runner
        // can never flip the assertion
        $expiration = (int) $returnParams['_expiration'];
        $this->assertGreaterThanOrEqual($before + 1800, $expiration);
        $this->assertLessThanOrEqual(time() + 1800, $expiration);

        // nothing is credited until PayPal sends the member back
        $this->assertUserBalance($this->userId, 0);
    }

    public function testFeeIsChargedOnTopButNotCredited(): void
    {
        $this->enablePaypal(['fee' => 10]);
        $this->startTopUp('5.00');

        // asserts the paypal.com host before the query is trusted
        $returnUrl = $this->returnUrlFromRedirect();

        $location = $this->client->getResponse()->headers->get('Location');
        \assert(null !== $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);

        // the member pays 5.50 at PayPal …
        $this->assertSame('5.50', $params['amount']);

        // … but the account is credited the requested 5.00
        $this->client->request('GET', $returnUrl);
        $this->assertUserBalance($this->userId, 500);
    }

    public function testReturnCreditsOnceAndIgnoresReplays(): void
    {
        $this->enablePaypal();
        $this->startTopUp('5.00');
        $returnUrl = $this->returnUrlFromRedirect();

        $this->client->request('GET', $returnUrl);
        $this->assertResponseRedirects("/user/{$this->userId}", 303);
        $this->client->followRedirect();
        $this->assertFlash('success', 'confirmed');
        $this->assertUserBalance($this->userId, 500);

        // the credit is tagged for reconciliation
        $transactions = $this->requestJson('GET', "/api/user/{$this->userId}/transaction", unpackKey: 'transactions');
        $this->assertCount(1, $transactions);
        $this->assertSame('paypal', $transactions[0]['comment']);

        // the nonce is consumed: a replayed URL redirects but credits nothing
        $this->client->request('GET', $returnUrl);
        $this->assertResponseRedirects("/user/{$this->userId}", 303);
        $this->assertUserBalance($this->userId, 500);
        $this->assertCount(1, $this->requestJson('GET', "/api/user/{$this->userId}/transaction", unpackKey: 'transactions'));
    }

    public function testBoundaryFailureAtReturnKeepsTheNonceRetryable(): void
    {
        $this->enablePaypal();
        $this->startTopUp('50.00');
        $returnUrl = $this->returnUrlFromRedirect();

        // the member has already paid by now — a credit that would cross the
        // account boundary must fail WITHOUT consuming the nonce
        $this->setUserBalance($this->userId, 16000); // 16000 + 5000 > account.boundary.upper (20000)
        $this->client->request('GET', $returnUrl);
        $this->assertResponseRedirects("/user/{$this->userId}", 303);

        // the failed credit closed the shared EntityManager (disableReboot
        // keeps one container); the worker runtime would reset doctrine here
        $this->resetEntityManager();
        $this->client->followRedirect();
        $this->assertFlash('error', self::ERROR_BOUNDARY);
        $this->assertUserBalance($this->userId, 16000);

        // once there is room again, the same link books the payment
        $this->setUserBalance($this->userId, 0);
        $this->client->request('GET', $returnUrl);
        $this->client->followRedirect();
        $this->assertFlash('success', 'confirmed');
        $this->assertUserBalance($this->userId, 5000);
    }

    public function testCancelReturnRendersWithoutCrediting(): void
    {
        $this->enablePaypal();
        $this->startTopUp('5.00');
        $cancelUrl = $this->signedUrlFromRedirect('cancel_return');

        $this->client->request('GET', $cancelUrl);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Payment cancelled');
        $this->assertUserBalance($this->userId, 0);

        // the cancel page is signed too — an unsigned guess is a 404
        $this->client->request('GET', preg_replace('/[&?]_hash=[^&]+/', '', $cancelUrl) ?? '');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testTamperedReturnUrlsAre404(): void
    {
        $this->enablePaypal();
        $this->startTopUp('5.00');
        $returnUrl = $this->returnUrlFromRedirect();

        // inflate the credited amount in the signed path
        $this->client->request('GET', str_replace('/return/500', '/return/9999', $returnUrl));
        $this->assertResponseStatusCodeSame(404);

        // strip the signature
        $this->client->request('GET', preg_replace('/[&?]_hash=[^&]+/', '', $returnUrl) ?? '');
        $this->assertResponseStatusCodeSame(404);

        // swap the nonce (it is covered by the signature)
        $this->client->request('GET', preg_replace('/nonce=[a-f0-9]+/', 'nonce=ffffffffffffffffffffffffffffffff', $returnUrl) ?? '');
        $this->assertResponseStatusCodeSame(404);

        $this->assertUserBalance($this->userId, 0);
    }

    public function testExpiredReturnUrlIs404(): void
    {
        $this->enablePaypal();

        // a validly signed but expired URL: expiry is enforced before the
        // nonce lookup, so this 404s rather than redirect-without-credit
        $signer = static::getContainer()->get(UriSigner::class);
        $expired = $signer->sign("http://localhost/user/{$this->userId}/paypal/return/500?nonce=deadbeef", time() - 60);

        $this->client->request('GET', $expired);
        $this->assertResponseStatusCodeSame(404);
        $this->assertUserBalance($this->userId, 0);
    }

    public function testValidSignatureAloneNeverCredits(): void
    {
        $this->enablePaypal();
        $this->startTopUp('5.00');
        $returnUrl = $this->returnUrlFromRedirect();

        // the pending nonce lives in the starting session — a different
        // visitor with the same (validly signed) link must not credit
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', $returnUrl);

        $this->assertResponseRedirects("/user/{$this->userId}", 303);
        $this->assertUserBalance($this->userId, 0);
    }

    public function testPaymentBoundaryIsCheckedBeforeRedirectingToPaypal(): void
    {
        $this->enablePaypal();

        // 20000 cents > payment.boundary.upper (15000)
        $this->startTopUp('200.00');

        $this->assertResponseRedirects("/user/{$this->userId}", 303);
        $this->client->followRedirect();
        $this->assertFlash('error', self::ERROR_BOUNDARY);
        $this->assertUserBalance($this->userId, 0);
    }

    public function testAccountBoundaryIsCheckedBeforeRedirectingToPaypal(): void
    {
        $this->enablePaypal();
        $this->setUserBalance($this->userId, 19900);

        // 19900 + 200 > account.boundary.upper (20000)
        $this->startTopUp('2.00');

        $this->assertResponseRedirects("/user/{$this->userId}", 303);
        $this->client->followRedirect();
        $this->assertFlash('error', self::ERROR_BOUNDARY);
        $this->assertUserBalance($this->userId, 19900);
    }

    public function testInvalidAmountIsRejected(): void
    {
        $this->enablePaypal();

        $this->startTopUp('0');

        $this->assertResponseRedirects("/user/{$this->userId}", 303);
        $this->client->followRedirect();
        $this->assertFlash('error', 'Total amount must be positive.');
        $this->assertUserBalance($this->userId, 0);
    }

    public function testBadCsrfTokenIsRejected(): void
    {
        $this->enablePaypal();

        $this->client->request('POST', "/user/{$this->userId}/paypal/start", [
            '_token' => 'garbage',
            'amount' => '5.00',
        ]);

        $this->assertResponseRedirects("/user/{$this->userId}", 303);
        $this->client->followRedirect();
        $this->assertFlash('error', self::ERROR_GENERIC);
        $this->assertUserBalance($this->userId, 0);
    }

    public function testDisabledUserCannotStartATopUp(): void
    {
        $this->enablePaypal();

        // stale tab: the token stays valid, the disabled check runs after CSRF
        $crawler = $this->client->request('GET', "/user/{$this->userId}?tab=paypal");
        $form = $crawler->filter('form[action$="/paypal/start"]')->form(['amount' => '5.00']);
        $this->setUserDisabled($this->userId);

        $this->client->submit($form);

        $this->client->followRedirect();
        $this->assertFlash('error', self::ERROR_ACCOUNT_DISABLED);
        $this->assertUserBalance($this->userId, 0);
    }
}
