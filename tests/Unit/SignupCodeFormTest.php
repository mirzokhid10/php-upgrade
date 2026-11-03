<?php

namespace Tests\Unit;

use app\models\SignupCodeForm;
use app\models\User;
use Codeception\Test\Unit;
use Tests\Support\CreatesUsers;
use Tests\Support\UnitTester;
use Yii;

class SignupCodeFormTest extends Unit
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
            'sms_code'     => '123456',
            'access_token' => 'code-test-token-' . $n,
            'email'        => 'code-test-' . $n . '@test.local',
        ], $attrs));
    }

    public function testPhoneAndCodeAreRequired(): void
    {
        $form = new SignupCodeForm();
        $this->assertFalse($form->validate());
        $this->assertSame(['Phone number is requied.'], $form->getErrors('phone')); // sic
        $this->assertSame(['SMS code is requied.'], $form->getErrors('code'));      // sic
    }

    public function testNullCodeIsRejected(): void
    {
        $this->makeUser(['sms_code' => null]);
        $form = new SignupCodeForm(['phone' => '998901112233', 'code' => null]);
        $this->assertFalse($form->validate());
        $this->assertSame(['SMS code is requied.'], $form->getErrors('code'));
    }

    public function testEmptyCodeIsRejectedEvenIfUserHasNoCode(): void
    {
        $this->makeUser(['sms_code' => null]);
        $form = new SignupCodeForm(['phone' => '998901112233', 'code' => '']);
        $this->assertFalse($form->validate());
        $this->assertFalse($form->login());
    }

    public function testUnknownPhoneIsRejected(): void
    {
        $this->makeUser();
        $form = new SignupCodeForm(['phone' => '998900000000', 'code' => '123456']);
        $this->assertFalse($form->validate());
        $this->assertSame(['Phone number not found.'], $form->getErrors('phone'));
        $this->assertFalse($form->hasErrors('code'));
    }

    public function testUnknownCodeIsRejected(): void
    {
        $this->makeUser();
        $form = new SignupCodeForm(['phone' => '998901112233', 'code' => '000000']);
        $this->assertFalse($form->validate());
        $this->assertSame(['Sms code incorrect'], $form->getErrors('code'));
        $this->assertFalse($form->hasErrors('phone'));
    }

    public function testMissingPhoneDoesNotAddExistError(): void
    {
        $form = new SignupCodeForm(['phone' => '', 'code' => '123456']);
        $form->validate();
        $this->assertSame(['Phone number is requied.'], $form->getErrors('phone')); // sic
    }

    public function testCorrectPhoneAndCodeReturnTheUser(): void
    {
        $this->makeUser();
        $form = new SignupCodeForm(['phone' => '998901112233', 'code' => '123456']);
        $user = $form->login();
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('998901112233', $user->username);
    }

    public function testIntegerCodeMatchesStoredString(): void
    {
        $this->makeUser();
        $form = new SignupCodeForm(['phone' => '998901112233', 'code' => 123456]);
        $this->assertInstanceOf(User::class, $form->login());
    }

    public function testLoginReturnsFalseWhenValidationFails(): void
    {
        $form = new SignupCodeForm();
        $this->assertFalse($form->login());
    }

    /**
     * Documents current behaviour: the "code" rule checks the code against ALL users, so a code
     * that belongs to someone else passes validation. login() is still safe because getUser()
     * matches phone AND code together, but it returns null (not false) in that case.
     *
     * @group known-gap
     */
    public function testCodeOfAnotherUserPassesValidationButLoginFails(): void
    {
        $this->makeUser(['username' => '998901112233', 'sms_code' => '111111']);
        $this->makeUser(['username' => '998904445566', 'sms_code' => '222222']);
        $form = new SignupCodeForm(['phone' => '998901112233', 'code' => '222222']);
        $this->assertTrue($form->validate());
        $this->assertNull($form->login());
    }

    public function testInactiveUserWithCorrectCodePassesValidationButLoginFails(): void
    {
        $this->makeUser(['status' => User::STATUS_INACTIVE]);
        $form = new SignupCodeForm(['phone' => '998901112233', 'code' => '123456']);
        $this->assertTrue($form->validate());
        $this->assertNull($form->login());
    }

    public function testUserLookupIsCached(): void
    {
        $this->makeUser();
        $form = new SignupCodeForm(['phone' => '998901112233', 'code' => '123456']);
        $first = $form->getUser();
        Yii::$app->db->createCommand()->delete(User::tableName(), ['username' => '998901112233'])->execute();
        $this->assertSame($first, $form->getUser());
    }
}