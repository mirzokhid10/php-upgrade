<?php

declare(strict_types=1);

namespace Tests\Unit;

use app\controllers\SiteController;
use Codeception\Test\Unit;
use Tests\Support\UnitTester;
use Yii;
use yii\web\Request;

/**
 * SiteController has no logic of its own to isolate, so these tests pin down its configuration
 * and prove that the URL rules do not expose it.
 */
final class SiteTest extends Unit
{
    protected UnitTester $tester;

    private function controller(): SiteController
    {
        return new SiteController('site', Yii::$app);
    }

    public function testLogoutIsPostOnly(): void
    {
        $behaviors = $this->controller()->behaviors();
        $this->assertSame(['logout' => ['post']], $behaviors['verbs']['actions']);
    }

    public function testOnlyLogoutNeedsALoginAndGuestsAreRefused(): void
    {
        $access = $this->controller()->behaviors()['access'];

        $this->assertSame(['logout'], $access['only']);
        $this->assertCount(1, $access['rules']);
        $this->assertTrue($access['rules'][0]['allow']);
        $this->assertSame(['@'], $access['rules'][0]['roles']);
        $this->assertSame(['logout'], $access['rules'][0]['actions']);
    }

    public function testErrorAndCaptchaActionsAreDeclared(): void
    {
        $actions = $this->controller()->actions();

        $this->assertSame('yii\web\ErrorAction', $actions['error']['class']);
        $this->assertSame('yii\captcha\CaptchaAction', $actions['captcha']['class']);
    }

    /** A fixed captcha code is a back door: it must only exist in the test environment. */
    public function testCaptchaCodeIsFixedOnlyInTheTestEnvironment(): void
    {
        $fixed = $this->controller()->actions()['captcha']['fixedVerifyCode'];

        $this->assertSame(YII_ENV_TEST ? 'testme' : null, $fixed);
    }

    public function testNoUrlRuleReachesTheSiteController(): void
    {
        $paths = ['', 'site', 'site/index', 'site/login', 'site/logout', 'site/contact', 'site/about', 'site/captcha', 'site/error', 'login', 'contact', 'about'];

        foreach ($paths as $path) {
            $request = new Request();
            $request->setPathInfo($path);
            $this->assertFalse(Yii::$app->urlManager->parseRequest($request), "'$path' must not resolve to a route");
        }
    }

    /** Sanity check for the test above: a real route does resolve. */
    public function testTheUrlManagerDoesResolveRealRoutes(): void
    {
        $request = new Request();
        $request->setPathInfo('calling-courier');

        $resolved = Yii::$app->urlManager->parseRequest($request);

        $this->assertIsArray($resolved);
        $this->assertSame('calling-courier/cities', $resolved[0]);
    }
}