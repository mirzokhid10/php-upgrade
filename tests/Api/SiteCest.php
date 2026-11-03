<?php

declare(strict_types=1);

namespace Tests\Api;

use Tests\Support\ApiTester;
use Tests\Support\CreatesUsers;

/**
 * SiteController is the stock Yii template controller. Strict URL parsing means no URL reaches it.
 * These tests make sure it stays that way: adding a `site` rule to config/web.php must be a deliberate act.
 */
final class SiteCest
{
    use CreatesUsers;

    private const GET_PATHS = [
        '/', 'site', 'site/index', 'site/login', 'site/contact', 'site/about', 'site/error',
        'site/captcha', 'site/captcha?refresh=1', 'index', 'login', 'contact', 'about',
    ];

    public function siteUrlsAreNotRouted(ApiTester $I): void
    {
        foreach (self::GET_PATHS as $path) {
            $I->sendGet($path);
            $I->seeResponseCodeIs(404);
        }
    }

    public function siteUrlsAreNotRoutedForLoggedInUsersEither(ApiTester $I): void
    {
        $this->createUser($I);
        $I->haveHttpHeader('Authorization', 'Bearer ' . self::TOKEN);

        foreach (self::GET_PATHS as $path) {
            $I->sendGet($path);
            $I->seeResponseCodeIs(404);
        }
    }

    public function logoutAndContactCannotBePosted(ApiTester $I): void
    {
        $this->createUser($I);
        $I->haveHttpHeader('Authorization', 'Bearer ' . self::TOKEN);

        foreach (['site/logout', 'logout', 'site/contact', 'site/login'] as $path) {
            $I->sendPost($path, ['LoginForm' => ['username' => 'x', 'password' => 'y']]);
            $I->seeResponseCodeIs(404);
        }
    }

    public function unknownUrlsAnswerWithJsonNotAnHtmlPage(ApiTester $I): void
    {
        $I->sendGet('site/login');
        $I->seeResponseCodeIs(404);
        $I->seeResponseIsJson();
        $I->assertStringNotContainsString('<html', strtolower($I->grabResponse()));
    }
}