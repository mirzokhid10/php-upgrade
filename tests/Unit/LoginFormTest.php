<?php

declare(strict_types=1);

namespace Tests\Unit;

use app\models\LoginForm;
use app\models\User;
use Codeception\Test\Unit;
use Tests\Support\CreatesRecords;
use Tests\Support\UnitTester;
use Yii;

/**
 * LoginForm only validates credentials and returns the user. It does NOT log the web user in.
 */
final class LoginFormTest extends Unit
{
    use CreatesRecords;

    protected UnitTester $tester;

    private const PASSWORD = 'secret123';
    private const BLOCKED_STATUS = 9; // hard-coded in LoginForm::validatePassword()

    /** @return array{0:int,1:string} id and username */
    private function makeUser(array $override = []): array
    {
        $username = 'lf-' . bin2hex(random_bytes(4));
        $id = $this->insertRow('user', array_merge([
            'username' => $username,
            'password_hash' => Yii::$app->security->generatePasswordHash(self::PASSWORD, 4),
            'access_token' => bin2hex(random_bytes(16)),
            'auth_key' => 'lf-auth',
            'status' => User::STATUS_ACTIVE,
        ], $override));

        return [$id, $username];
    }

    private function form(string $username, string $password): LoginForm
    {
        $form = new LoginForm();
        $form->username = $username;
        $form->password = $password;

        return $form;
    }

    public function testUsernameAndPasswordAreRequired(): void
    {
        $form = new LoginForm();

        $this->assertFalse($form->validate());
        $this->assertArrayHasKey('username', $form->errors);
        $this->assertArrayHasKey('password', $form->errors);
    }

    public function testMissingFieldsDoNotTriggerTheCredentialCheck(): void
    {
        $form = $this->form('', '');
        $form->validate();

        $this->assertSame(['Username cannot be blank.'], $form->getErrors('username'));
        $this->assertSame(['Password cannot be blank.'], $form->getErrors('password'));
    }

    public function testCorrectCredentialsReturnTheUser(): void
    {
        [$id, $username] = $this->makeUser();
        $form = $this->form($username, self::PASSWORD);

        $this->assertTrue($form->validate(), json_encode($form->errors));

        $user = $form->login();
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($id, $user->id);
        $this->assertSame('', $form->error_message);
    }

    public function testWrongPasswordIsRejected(): void
    {
        [, $username] = $this->makeUser();
        $form = $this->form($username, 'not-the-password');

        $this->assertFalse($form->validate());
        $this->assertSame(['Incorrect username or password.'], $form->getErrors('password'));
        $this->assertFalse($form->login());
    }

    public function testUnknownUserIsRejectedWithTheSameMessage(): void
    {
        $form = $this->form('nobody-' . bin2hex(random_bytes(4)), self::PASSWORD);

        $this->assertFalse($form->validate());
        $this->assertSame(['Incorrect username or password.'], $form->getErrors('password'));
        $this->assertNull($form->getUser());
    }

    public function testBlockedUserCannotLogInWithTheCorrectPassword(): void
    {
        [, $username] = $this->makeUser(['status' => self::BLOCKED_STATUS]);
        $form = $this->form($username, self::PASSWORD);

        $this->assertFalse($form->validate());
        $this->assertFalse($form->login());
    }

    /** Pins existing behaviour: even a wrong password produces the "required" message. */
    public function testFailedLoginSetsTheLegacyErrorMessage(): void
    {
        [, $username] = $this->makeUser();
        $form = $this->form($username, 'wrong');

        $form->login();

        $this->assertSame('Username and password is required', $form->error_message);
    }

    public function testLoginDoesNotStartASession(): void
    {
        [, $username] = $this->makeUser();

        $this->form($username, self::PASSWORD)->login();

        $this->assertTrue(Yii::$app->user->isGuest, 'LoginForm::login() must not log the web user in');
    }

    public function testUserLookupIsCached(): void
    {
        [, $username] = $this->makeUser();
        $form = $this->form($username, self::PASSWORD);

        $this->assertSame($form->getUser(), $form->getUser());
    }
}