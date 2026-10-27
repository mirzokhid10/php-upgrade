<?php

declare(strict_types=1);

namespace Tests\Api;

use app\models\Sms;
use app\models\User;
use Codeception\Attribute\Group;
use Tests\Support\ApiTester;
use Yii;

/**
 * Functional (HTTP-level) tests for app\controllers\AuthController.
 *
 *   POST /login               -> actionLoginCourier  (username + password)
 *   POST /auth-signup-phone   -> actionSignupPhone   (phone)
 *   POST /auth-signup-code    -> actionSignupCode    (phone + code)
 *
 * In this app the phone number IS the `username` column of the `user` table.
 * Run:  vendor/bin/codecept run Api AuthCest
 */
class AuthCest
{
    private const PHONE = '998901234567';
    private const OTHER_PHONE = '998907654321';
    private const PASSWORD = 'secret123';
    private const TOKEN = 'test-access-token-123';
    private const OTHER_TOKEN = 'test-access-token-456';

    public function _before(ApiTester $I): void
    {
        // bcrypt cost 13 (Yii default) makes every test ~0.5s slower
        Yii::$app->security->passwordHashCost = 4;
    }

    // ------------------------------------------------------------------ helpers

    /**
     * Inserts a user directly into the TEST database and returns its id.
     * Every NOT NULL column that has no default (e.g. `email`) and is not given here is
     * filled automatically from the table schema, so the helper keeps working when the
     * table gets new required columns.
     */
    private function createUser(ApiTester $I, array $overrides = []): int
    {
        $attributes = array_merge([
            'username' => self::PHONE,
            'password_hash' => Yii::$app->security->generatePasswordHash(self::PASSWORD),
            'access_token' => self::TOKEN,
            'auth_key' => 'test-auth-key',
            'fullname' => 'Test Courier',
            'role_id' => 1,
            'status' => User::STATUS_ACTIVE,
        ], $overrides);

        return (int) $I->haveRecord(User::class, $this->fillRequiredColumns($attributes));
    }

    private function fillRequiredColumns(array $attributes): array
    {
        $seed = (string) ($attributes['username'] ?? self::PHONE);

        foreach (User::getTableSchema()->columns as $name => $column) {
            if (
                array_key_exists($name, $attributes)
                || $column->autoIncrement
                || $column->allowNull
                || $column->defaultValue !== null
            ) {
                continue;
            }

            if ($name === 'email') {
                $attributes[$name] = $seed . '@test.local';
                continue;
            }

            if (!empty($column->enumValues)) {
                $attributes[$name] = $column->enumValues[0];
                continue;
            }

            $attributes[$name] = match ($column->type) {
                'tinyint', 'smallint', 'integer', 'bigint', 'boolean',
                'float', 'double', 'decimal', 'money' => 0,
                'date' => '2000-01-01',
                'time' => '00:00:00',
                'datetime', 'timestamp' => '2000-01-01 00:00:00',
                'json' => '{}',
                // unique-ish text that fits the column
                default => substr(md5($seed . $name), 0, max(1, min((int) ($column->size ?? 8), 16))),
            };
        }

        return $attributes;
    }

    private function json(ApiTester $I): array
    {
        $data = json_decode($I->grabResponse(), true);

        return is_array($data) ? $data : [];
    }

    private function assertNoToken(ApiTester $I): void
    {
        $data = $this->json($I)['data'] ?? [];
        $I->assertArrayNotHasKey('token', is_array($data) ? $data : [], 'response must not contain a token');
    }

    // ============================================================ POST /login

    public function loginSucceedsWithCorrectCredentials(ApiTester $I): void
    {
        $this->createUser($I);

        $I->sendPost('/login', ['username' => self::PHONE, 'password' => self::PASSWORD]);

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

    public function loginReturnsType2ForRole2(ApiTester $I): void
    {
        $this->createUser($I, ['role_id' => 2]);

        $I->sendPost('/login', ['username' => self::PHONE, 'password' => self::PASSWORD]);

        $I->seeResponseContainsJson(['status' => 'OK', 'type' => 2]);
    }

    public function loginAcceptsJsonBody(ApiTester $I): void
    {
        $this->createUser($I);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/login', json_encode(['username' => self::PHONE, 'password' => self::PASSWORD]));

        $I->seeResponseContainsJson(['status' => 'OK', 'data' => ['token' => self::TOKEN]]);
    }

    public function loginRejectsWrongPassword(ApiTester $I): void
    {
        $this->createUser($I);

        $I->sendPost('/login', ['username' => self::PHONE, 'password' => 'wrong-password']);

        $I->seeResponseCodeIs(200); // this API reports errors in the body, not via HTTP status
        $I->seeResponseContainsJson(['status' => 'ERROR']);
        $this->assertNoToken($I);
    }

    public function loginRejectsUnknownUser(ApiTester $I): void
    {
        $I->sendPost('/login', ['username' => '998000000000', 'password' => self::PASSWORD]);

        $I->seeResponseContainsJson(['status' => 'ERROR']);
        $this->assertNoToken($I);
    }

    public function loginRejectsInactiveUser(ApiTester $I): void
    {
        $this->createUser($I, ['status' => User::STATUS_INACTIVE]);

        $I->sendPost('/login', ['username' => self::PHONE, 'password' => self::PASSWORD]);

        $I->seeResponseContainsJson(['status' => 'ERROR']);
        $this->assertNoToken($I);
    }

    public function loginRejectsEmptyBody(ApiTester $I): void
    {
        $I->sendPost('/login', []);

        $I->seeResponseContainsJson(['status' => 'ERROR']);
        $this->assertNoToken($I);
    }

    public function loginRouteIsPostOnly(ApiTester $I): void
    {
        $I->sendGet('/login');

        $I->seeResponseCodeIs(404);
    }

    // ================================================== POST /auth-signup-phone

    public function signupPhoneGeneratesCodeAndQueuesSms(ApiTester $I): void
    {
        $id = $this->createUser($I);

        $I->sendPost('/auth-signup-phone', ['phone' => self::PHONE]);

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['status' => 'OK', 'message' => 'SMS code successfully sent']);

        $code = (string) $I->grabRecord(User::class, ['id' => $id])->sms_code;
        $I->assertMatchesRegularExpression('/^\d{5}$/', $code);
        $I->assertGreaterThanOrEqual(10000, (int) $code);

        // SmsComponent::send() stores the message in the `sms` table
        $sms = $I->grabRecord(Sms::class, ['phone' => self::PHONE]);
        $I->assertStringContainsString($code, (string) $sms->text);
    }

    public function signupPhoneRejectsUnknownPhone(ApiTester $I): void
    {
        $I->sendPost('/auth-signup-phone', ['phone' => '998000000000']);

        $I->seeResponseContainsJson(['status' => 'ERROR']);
        $I->dontSeeRecord(Sms::class, ['phone' => '998000000000']);
    }

    public function signupPhoneRejectsMissingPhone(ApiTester $I): void
    {
        $I->sendPost('/auth-signup-phone', []);

        $I->seeResponseContainsJson(['status' => 'ERROR']);
        $I->assertNotEmpty($this->json($I)['message'] ?? null, 'an error message is expected');
    }

    public function signupPhoneRejectsInactiveUser(ApiTester $I): void
    {
        $id = $this->createUser($I, ['status' => User::STATUS_INACTIVE]);

        $I->sendPost('/auth-signup-phone', ['phone' => self::PHONE]);

        $I->seeResponseContainsJson(['status' => 'ERROR']);
        $I->assertNull($I->grabRecord(User::class, ['id' => $id])->sms_code);
        $I->dontSeeRecord(Sms::class, ['phone' => self::PHONE]);
    }

    // =================================================== POST /auth-signup-code

    public function signupCodeSucceedsAndClearsTheCode(ApiTester $I): void
    {
        $id = $this->createUser($I, ['sms_code' => 12345]);

        $I->sendPost('/auth-signup-code', ['phone' => self::PHONE, 'code' => 12345]);

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'status' => 'OK',
            'message' => 'Success',
            'data' => ['token' => self::TOKEN],
        ]);
        $I->assertNull($I->grabRecord(User::class, ['id' => $id])->sms_code, 'one-time code must be cleared');
    }

    public function signupCodeCannotBeReused(ApiTester $I): void
    {
        $this->createUser($I, ['sms_code' => 12345]);
        $payload = ['phone' => self::PHONE, 'code' => 12345];

        $I->sendPost('/auth-signup-code', $payload);
        $I->seeResponseContainsJson(['status' => 'OK']);

        $I->sendPost('/auth-signup-code', $payload);
        $I->seeResponseContainsJson(['status' => 'ERROR']);
        $this->assertNoToken($I);
    }

    public function signupCodeRejectsWrongCode(ApiTester $I): void
    {
        $id = $this->createUser($I, ['sms_code' => 12345]);

        $I->sendPost('/auth-signup-code', ['phone' => self::PHONE, 'code' => 54321]);

        $I->seeResponseContainsJson(['status' => 'ERROR']);
        $this->assertNoToken($I);
        $I->assertEquals(12345, $I->grabRecord(User::class, ['id' => $id])->sms_code, 'stored code must stay untouched');
    }

    public function signupCodeRejectsMissingCode(ApiTester $I): void
    {
        $this->createUser($I, ['sms_code' => 12345]);

        $I->sendPost('/auth-signup-code', ['phone' => self::PHONE]);

        $I->seeResponseContainsJson(['status' => 'ERROR']);
        $this->assertNoToken($I);
    }

    public function signupCodeRejectsMissingPhone(ApiTester $I): void
    {
        $this->createUser($I, ['sms_code' => 12345]);

        $I->sendPost('/auth-signup-code', ['code' => 12345]);

        $I->seeResponseContainsJson(['status' => 'ERROR']);
        $this->assertNoToken($I);
    }

    public function signupCodeRejectsUnknownPhone(ApiTester $I): void
    {
        $this->createUser($I, ['sms_code' => 12345]);

        $I->sendPost('/auth-signup-code', ['phone' => '998000000000', 'code' => 12345]);

        $I->seeResponseContainsJson(['status' => 'ERROR']);
        $this->assertNoToken($I);
    }

    /**
     * SECURITY: this test is EXPECTED TO FAIL against the current code.
     * SignupCodeForm checks that the phone exists AND that the code exists in ANY user's
     * `sms_code` -- it never checks that the code belongs to THAT phone's user. So anyone who
     * knows a victim's phone number can log in as the victim with another user's valid code.
     */
    #[Group('security-bug')]
    public function signupCodeRejectsCodeBelongingToAnotherUser(ApiTester $I): void
    {
        $this->createUser($I, ['username' => self::PHONE, 'access_token' => self::TOKEN]);
        $this->createUser($I, [
            'username' => self::OTHER_PHONE,
            'access_token' => self::OTHER_TOKEN,
            'sms_code' => 55555,
        ]);

        // victim = PHONE (has no code). Attacker uses the code that was sent to OTHER_PHONE.
        $I->sendPost('/auth-signup-code', ['phone' => self::PHONE, 'code' => 55555]);

        $I->seeResponseContainsJson(['status' => 'ERROR']);
        $this->assertNoToken($I);
    }
}