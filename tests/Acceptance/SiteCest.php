<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

/**
 * Over real HTTP: the template pages (home, login, contact, about) must not be served by this API.
 * Needs the test server: php -S 127.0.0.1:8060 -t web web/index-test.php
 */
final class SiteCest
{
    public function templatePagesAreNotServed(AcceptanceTester $I): void
    {
        foreach (['/', 'site/index', 'site/login', 'site/contact', 'site/about', 'site/captcha'] as $path) {
            $I->sendGet($path);
            $I->seeResponseCodeIs(404);
            $I->assertStringNotContainsString('<html', strtolower($I->grabResponse()), "$path must not render a page");
        }
    }

    public function logoutIsNotReachable(AcceptanceTester $I): void
    {
        $I->sendPost('site/logout', []);
        $I->seeResponseCodeIs(404);
    }

    public function notFoundIsReportedAsJson(AcceptanceTester $I): void
    {
        $I->sendGet('site/login');
        $I->seeResponseCodeIs(404);
        $I->seeResponseIsJson();
    }
}