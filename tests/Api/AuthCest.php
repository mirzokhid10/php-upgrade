<?php

namespace Tests\Api;

use app\models\User;
use Tests\Support\ApiTester;
use Tests\Support\FakeSmsComponent;
use Yii;

class AuthCest
{
    // ---- ADJUST THESE to match your real forms / User table ----
    private const PHONE = '998901234567';
    private const PASSWORD = 'secret123';
    private const TOKEN = 'test-access-token-123';

    private const LOGIN_PHONE_FIELD = 'phone';      // field name LoginForm expects
    private const LOGIN_PASSWORD_FIELD = 'password'; // field name LoginForm expects
    private const CODE_FIELD = 'code';              // field name SignupCodeForm expects
    // ------------------------------------------------------------

    public function _before(ApiTester $I): void
    {
        // Safely clear out our fake SMS tracker before every test run
        FakeSmsComponent::$sent = [];
    }

    /**
     * Creates a user row directly in the test DB and returns its id.
     * Add/remove columns so they match your `user` table.
     */
    private function createUser(ApiTester $I, array $overrides = []): int
    {
        return $I->haveRecord(User::class, array_merge([
            'phone' => self::PHONE,
            'password_hash' => Yii::$app->security->generatePasswordHash(self::PASSWORD),
            'access_token' => self::TOKEN,
            'role_id' => 1,
            'status' => 1,
        ], $overrides));
    }

    // ===================== actionLoginCourier =====================

    public function loginCourierSuccess(ApiTester $I): void
    {
        $this->createUser($I);

        $I->sendPost('/login', [
            self::LOGIN_PHONE_FIELD => self::PHONE,
            self::LOGIN_PASSWORD_FIELD => self::PASSWORD,
        ]);

        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'status' => 'OK',
            'type' => 1,
            'code' => 200,
            'message' => 'Success',
            'data' => ['token' => self::TOKEN],
        ]);
    }

    public function loginCourierReturnsType2ForRole2(ApiTester $I): void
    {
        $this->createUser($I, ['role_id' => 2]);

        $I->sendPost('/login', [
            self::LOGIN_PHONE_FIELD => self::PHONE,
            self::LOGIN_PASSWORD_FIELD => self::PASSWORD,
        ]);

        $I->seeResponseContainsJson(['status' => 'OK', 'type' => 2]);
    }

    public function loginCourierWrongPassword(ApiTester $I): void
    {
        $this->createUser($I);

        $I->sendPost('/login', [
            self::LOGIN_PHONE_FIELD => self::PHONE,
            self::LOGIN_PASSWORD_FIELD => 'wrong-password',
        ]);

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['status' => 'ERROR']);
        $I->dontSeeResponseJsonMatchesJsonPath('$.data.token');
    }

    public function loginCourierUnknownUser(ApiTester $I): void
    {
        $I->sendPost('/login', [
            self::LOGIN_PHONE_FIELD => '998000000000',
            self::LOGIN_PASSWORD_FIELD => self::PASSWORD,
        ]);

        $I->seeResponseContainsJson(['status' => 'ERROR']);
    }

    public function loginCourierEmptyFields(ApiTester $I): void
    {
        $I->sendPost('/login', []);

        $I->seeResponseContainsJson(['status' => 'ERROR']);
    }

    // ===================== actionSignupPhone =====================

    public function signupPhoneSuccess(ApiTester $I): void
    {
        $id = $this->createUser($I);

        $I->sendPost('/auth-signup-phone', ['phone' => self::PHONE]);

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'status' => 'OK',
            'message' => 'SMS code successfully sent',
        ]);

        // The code is random, so assert its shape, then compare with the SMS
        $user = $I->grabRecord(User::class, ['id' => $id]);
        $code = (string) $user->sms_code;
        $I->assertMatchesRegularExpression('/^\d{5}$/', $code);
        $I->assertGreaterThanOrEqual(10000, (int) $code);

        $I->assertCount(1, FakeSmsComponent::$sent);
        
        // FIXED: Using matching argument labels from your actual components
        $I->assertStringContainsString(self::PHONE, (string) FakeSmsComponent::$sent[0]['phone']);
        $I->assertStringContainsString($code, FakeSmsComponent::$sent[0]['text']);
    }

    public function signupPhoneUnknownPhone(ApiTester $I): void
    {
        $I->sendPost('/auth-signup-phone', ['phone' => '998000000000']);

        $I->seeResponseContainsJson(['status' => 'ERROR']);
        $I->assertCount(0, FakeSmsComponent::$sent);
    }

    public function signupPhoneMissingPhone(ApiTester $I): void
    {
        $I->sendPost('/auth-signup-phone', []);

        $I->seeResponseContainsJson(['status' => 'ERROR']);
        $I->assertCount(0, FakeSmsComponent::$sent);
    }

    // ===================== actionSignupCode =====================

    public function signupCodeSuccess(ApiTester $I): void
    {
        $id = $this->createUser($I, ['sms_code' => 12345]);

        $I->sendPost('/auth-signup-code', [
            'phone' => self::PHONE,
            self::CODE_FIELD => 12345,
        ]);

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'status' => 'OK',
            'message' => 'Success',
            'data' => ['token' => self::TOKEN],
        ]);

        // one-time code must be cleared
        $user = $I->grabRecord(User::class, ['id' => $id]);
        $I->assertNull($user->sms_code);
    }

    public function signupCodeCannotBeReused(ApiTester $I): void
    {
        $this->createUser($I, ['sms_code' => 12345]);
        $payload = ['phone' => self::PHONE, self::CODE_FIELD => 12345];

        $I->sendPost('/auth-signup-code', $payload);
        $I->seeResponseContainsJson(['status' => 'OK']);

        $I->sendPost('/auth-signup-code', $payload);
        $I->seeResponseContainsJson(['status' => 'ERROR']);
        $I->dontSeeResponseJsonMatchesJsonPath('$.data.token');
    }

    public function signupCodeWrongCode(ApiTester $I): void
    {
        $id = $this->createUser($I, ['sms_code' => 12345]);

        $I->sendPost('/auth-signup-code', [
            'phone' => self::PHONE,
            self::CODE_FIELD => 54321,
        ]);

        $I->seeResponseContainsJson(['status' => 'ERROR']);
        $I->dontSeeResponseJsonMatchesJsonPath('$.data.token');

        // stored code must stay untouched
        $user = $I->grabRecord(User::class, ['id' => $id]);
        $I->assertEquals(12345, $user->sms_code);
    }

    public function signupCodeMissingCode(ApiTester $I): void
    {
        $this->createUser($I, ['sms_code' => 12345]);

        $I->sendPost('/auth-signup-code', ['phone' => self::PHONE]);

        $I->seeResponseContainsJson(['status' => 'ERROR']);
    }
}
