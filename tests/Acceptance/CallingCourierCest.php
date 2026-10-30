<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use app\models\User;
use Tests\Support\AcceptanceTester;
use Tests\Support\CreatesRecords;
use Yii;

/**
 * Acceptance tests: real HTTP against `php -S 127.0.0.1:8081 -t web web/index-test.php`.
 * Nothing is rolled back automatically, so every row this class creates is deleted in _before/_after.
 */
final class CallingCourierCest
{
    use CreatesRecords;

    private const TOKEN = 'accept-token-0001';
    private const USERNAME = '998900000001';
    private const PHONE = '998901234567';

    private int $userId = 0;

    public function _before(AcceptanceTester $I): void
    {
        $dsn = (string) Yii::$app->db->dsn;
        if (!str_contains($dsn, 'test')) {
            throw new \RuntimeException("Refusing to run acceptance tests against a non-test database: $dsn");
        }

        $this->cleanup();
        $this->userId = $this->insertRow('user', [
            'username' => self::USERNAME,
            'password_hash' => Yii::$app->security->generatePasswordHash('secret123'),
            'access_token' => self::TOKEN,
            'auth_key' => 'accept-auth-key',
            'fullname' => 'Acceptance User',
            'role_id' => 1,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    public function _after(AcceptanceTester $I): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $db = Yii::$app->db;
        $ids = $db->createCommand('SELECT id FROM user WHERE access_token = :t', [':t' => self::TOKEN])->queryColumn();
        if ($ids) {
            $db->createCommand()->delete('calling_courier_request', ['user_id' => $ids])->execute();
            $db->createCommand()->delete('user', ['id' => $ids])->execute();
        }
    }

    private function cityId(): int
    {
        return (int) Yii::$app->db->createCommand('SELECT id FROM city ORDER BY id LIMIT 1')->queryScalar();
    }

    private function authenticate(AcceptanceTester $I): void
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . self::TOKEN);
    }

    public function serverRejectsRequestsWithoutAToken(AcceptanceTester $I): void
    {
        $I->sendGet('calling-courier');
        $I->seeResponseCodeIs(401);

        $I->sendPost('calling-courier', ['city_id' => $this->cityId(), 'phone' => self::PHONE]);
        $I->seeResponseCodeIs(401);
    }

    public function citiesAreServedAsJson(AcceptanceTester $I): void
    {
        $this->authenticate($I);
        $I->sendGet('calling-courier');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['data' => ['success' => true]]);
        $I->seeResponseJsonMatchesJsonPath('$.data.data[0].name_ru');
    }

    public function unroutedVerbsGetA404(AcceptanceTester $I): void
    {
        $this->authenticate($I);
        $I->sendPut('calling-courier', []);
        $I->seeResponseCodeIs(404);
    }

    public function courierCanBeCalledOnceThenTheSecondCallIsRefused(AcceptanceTester $I): void
    {
        $this->authenticate($I);
        $body = ['city_id' => $this->cityId(), 'phone' => self::PHONE, 'address' => 'Ташкент, Чиланзар', 'latitude' => '41.2', 'longitude' => '69.2'];

        $I->sendPost('calling-courier', $body);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['data' => ['success' => true, 'data' => ['phone' => self::PHONE, 'address' => 'Ташкент, Чиланзар']]]);

        $I->sendPost('calling-courier', $body);
        $I->seeResponseContainsJson(['data' => ['success' => false]]);

        $rows = (int) Yii::$app->db
            ->createCommand('SELECT COUNT(*) FROM calling_courier_request WHERE user_id = :u', [':u' => $this->userId])
            ->queryScalar();
        $I->assertSame(1, $rows);
    }

    public function invalidInputIsReportedNotCrashed(AcceptanceTester $I): void
    {
        $this->authenticate($I);
        $I->sendPost('calling-courier', ['city_id' => $this->cityId(), 'phone' => 'not-a-phone']);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['data' => ['success' => false]]);
        $I->seeResponseJsonMatchesJsonPath('$.data.errors.phone');
    }
}