<?php

namespace Tests\Unit;

use app\models\SignupPhoneForm;
use app\models\User;
use Codeception\Test\Unit;
use Tests\Support\CreatesUsers;
use Tests\Support\UnitTester;
use Yii;

class SignupPhoneFormTest extends Unit
{
    use CreatesUsers;

    protected UnitTester $tester;

    /** Thin wrapper over the shared CreatesUsers trait; unique token per call. */
    private function makeUser(array $attrs = []): string
    {
        static $n = 0;
        $n++;
        return (string) $this->createUser($this->tester, array_merge([
            'username'     => '998901112233',
            'access_token' => 'phone-test-token-' . $n,
            'email'        => 'phone-test-' . $n . '@test.local',
        ], $attrs));
    }

    public function testPhoneIsRequired(): void
    {
        $form = new SignupPhoneForm();
        $this->assertFalse($form->validate());
        $this->assertSame(['Phone number is requied.'], $form->getErrors('phone')); // sic: typo is part of the current API text
    }

    public function testEmptyStringIsRejected(): void
    {
        $form = new SignupPhoneForm(['phone' => '']);
        $this->assertFalse($form->validate());
        $this->assertTrue($form->hasErrors('phone'));
    }

    public function testAnyNonEmptyPhoneValidates(): void
    {
        // The format and existence rules are commented out in the model: only "required" applies.
        $form = new SignupPhoneForm(['phone' => 'not-a-phone']);
        $this->assertTrue($form->validate());
    }

    public function testLoginReturnsFalseWhenInvalid(): void
    {
        $form = new SignupPhoneForm();
        $this->assertFalse($form->login());
    }

    public function testLoginReturnsActiveUser(): void
    {
        $this->makeUser();
        $form = new SignupPhoneForm(['phone' => '998901112233']);
        $user = $form->login();
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('998901112233', $user->username);
    }

    public function testUnknownPhoneValidatesButFindsNoUser(): void
    {
        $form = new SignupPhoneForm(['phone' => '998900000000']);
        $this->assertTrue($form->validate());
        $this->assertNull($form->login());
    }

    public function testInactiveUserIsNotFound(): void
    {
        $this->makeUser(['status' => User::STATUS_INACTIVE]);
        $form = new SignupPhoneForm(['phone' => '998901112233']);
        $this->assertNull($form->login());
    }

    public function testDeletedUserIsNotFound(): void
    {
        $this->makeUser(['status' => User::STATUS_DELETED]);
        $form = new SignupPhoneForm(['phone' => '998901112233']);
        $this->assertNull($form->getUser());
    }

    public function testUserLookupIsCached(): void
    {
        $this->makeUser();
        $form = new SignupPhoneForm(['phone' => '998901112233']);
        $first = $form->getUser();
        Yii::$app->db->createCommand()->delete(User::tableName(), ['username' => '998901112233'])->execute();
        $this->assertSame($first, $form->getUser());
    }

    public function testErrorMessageDefaultsToEmptyString(): void
    {
        $this->assertSame('', (new SignupPhoneForm())->error_message);
    }
}