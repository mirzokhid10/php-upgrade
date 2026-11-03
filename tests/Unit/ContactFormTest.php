<?php

declare(strict_types=1);

namespace Tests\Unit;

use app\models\ContactForm;
use Codeception\Test\Unit;
use Tests\Support\UnitTester;
use Yii;

/**
 * ContactForm is the template contact form (SiteController is not routed, so nothing calls it today).
 * The captcha tests rely on the fixed code 'testme', which only exists when YII_ENV is 'test'.
 */
final class ContactFormTest extends Unit
{
    protected UnitTester $tester;

    private function valid(array $override = []): ContactForm
    {
        $form = new ContactForm();
        $form->setAttributes(array_merge([
            'name' => 'Test Sender',
            'email' => 'sender@example.com',
            'subject' => 'Hello',
            'body' => 'Message body',
            'verifyCode' => 'testme',
        ], $override));

        return $form;
    }

    private function requireTestEnvironment(): void
    {
        if (!YII_ENV_TEST) {
            $this->markTestSkipped('The fixed captcha code only exists when YII_ENV is "test".');
        }
    }

    /** Replaces the mailer with a spy that records every call and never sends anything. */
    private function spyMailer(): object
    {
        $spy = new class {
            public array $calls = [];

            public function compose(): static
            {
                return $this;
            }

            public function __call(string $name, array $arguments): static
            {
                $this->calls[] = [$name, $arguments];

                return $this;
            }
        };
        Yii::$app->set('mailer', $spy);

        return $spy;
    }

    public function testNameEmailSubjectAndBodyAreRequired(): void
    {
        $form = new ContactForm();
        $form->validate();

        foreach (['name', 'email', 'subject', 'body'] as $field) {
            $this->assertArrayHasKey($field, $form->errors, $field);
        }
    }

    public function testEmailMustBeValid(): void
    {
        $this->requireTestEnvironment();

        $this->assertTrue($this->valid()->validate());
        foreach (['not-an-email', 'a@', '@example.com', 'a b@example.com'] as $bad) {
            $form = $this->valid(['email' => $bad]);
            $this->assertFalse($form->validate(), $bad);
            $this->assertArrayHasKey('email', $form->errors);
        }
    }

    public function testCaptchaCodeIsChecked(): void
    {
        $this->requireTestEnvironment();

        $this->assertTrue($this->valid(['verifyCode' => 'testme'])->validate());

        foreach (['wrong', ''] as $bad) {
            $form = $this->valid(['verifyCode' => $bad]);
            $this->assertFalse($form->validate(), json_encode($bad));
            $this->assertArrayHasKey('verifyCode', $form->errors);
        }
    }

    public function testVerifyCodeLabel(): void
    {
        $this->assertSame('Verification Code', (new ContactForm())->getAttributeLabel('verifyCode'));
    }

    public function testMailParametersAreConfigured(): void
    {
        foreach (['adminEmail', 'senderEmail', 'senderName'] as $key) {
            $this->assertArrayHasKey($key, Yii::$app->params, "params['$key'] is read by the contact form and SiteController");
        }
    }

    public function testInvalidFormSendsNothing(): void
    {
        $spy = $this->spyMailer();

        $this->assertFalse((new ContactForm())->contact('admin@example.com'));
        $this->assertSame([], $spy->calls);
    }

    public function testValidFormComposesAndSendsOneMessage(): void
    {
        $this->requireTestEnvironment();
        $spy = $this->spyMailer();

        $this->assertTrue($this->valid()->contact('admin@example.com'));

        $calls = [];
        foreach ($spy->calls as [$name, $arguments]) {
            $calls[$name] = $arguments;
        }
        $this->assertSame(['admin@example.com'], $calls['setTo']);
        $this->assertSame(['sender@example.com' => 'Test Sender'], $calls['setReplyTo'][0]);
        $this->assertSame(['Hello'], $calls['setSubject']);
        $this->assertSame(['Message body'], $calls['setTextBody']);
        $this->assertArrayHasKey('send', $calls);
    }
}