<?php

declare(strict_types=1);

namespace Tests\Api;

use Tests\Support\ApiTester;
use Tests\Support\CreatesRecords;
use Tests\Support\CreatesUsers;
use Yii;

/**
 * Functional tests for CallingCourierController (in-process, TEST database).
 * Adjust the two URL constants if your urlManager rules differ.
 */
final class CallingCourierCest
{
    use CreatesUsers;
    use CreatesRecords;

    private const CITIES_URL = 'calling-courier';   // GET  calling-courier => calling-courier/cities
    private const CREATE_URL = 'calling-courier';   // POST calling-courier => calling-courier/create

    private const STATUS_NEW = 1;
    private const STATUS_COMPLETED = 5;
    private const STATUS_CANCELLED = 6;

    private function authenticate(ApiTester $I, string $token = self::TOKEN): void
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $token);
    }

    /** The app wraps every response as {status, code, message, data: {...}}; the controller's own result is `data`. */
    private function result(ApiTester $I): array
    {
        $body = $this->json($I);

        return is_array($body['data'] ?? null) ? $body['data'] : [];
    }

    /** Fails when the response is only a generic error: the specific field error must be reported. */
    private function assertRejected(ApiTester $I, string $field): void
    {
        $data = $this->result($I);
        $I->assertFalse($data['success'] ?? null, json_encode($data, JSON_UNESCAPED_UNICODE));
        $I->assertArrayHasKey($field, $data['errors'] ?? [], 'expected a validation error for ' . $field . ', got: ' . json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    private function city(): array
    {
        return Yii::$app->db->createCommand('SELECT id, region_id FROM city ORDER BY id LIMIT 1')->queryOne();
    }

    private function payload(array $override = []): array
    {
        return array_merge(['city_id' => $this->city()['id'], 'phone' => self::PHONE], $override);
    }

    private function insertRequest(int $userId, int $status): int
    {
        $city = $this->city();

        return $this->insertRow('calling_courier_request', [
            'user_id' => $userId,
            'city_id' => $city['id'],
            'region_id' => $city['region_id'],
            'phone' => self::PHONE,
            'status' => $status,
        ]);
    }

    private function requestCount(int $userId): int
    {
        return (int) Yii::$app->db
            ->createCommand('SELECT COUNT(*) FROM calling_courier_request WHERE user_id = :u', [':u' => $userId])
            ->queryScalar();
    }

    // ---------- access ----------

    public function citiesRequiresAToken(ApiTester $I): void
    {
        $I->sendGet(self::CITIES_URL);
        $I->seeResponseCodeIs(401);
    }

    public function createRequiresAToken(ApiTester $I): void
    {
        $I->sendPost(self::CREATE_URL, $this->payload());
        $I->seeResponseCodeIs(401);
    }

    /**
     * The URL rules are verb-specific (GET and POST only), so any other verb finds no route.
     * The controller's own VerbFilter is therefore unreachable through the router: it is a second safety net.
     */
    public function otherVerbsAreNotRouted(ApiTester $I): void
    {
        $this->createUser($I);
        $this->authenticate($I);

        $I->sendPut(self::CITIES_URL, []);
        $I->seeResponseCodeIs(404);
        $I->sendDelete(self::CITIES_URL);
        $I->seeResponseCodeIs(404);
    }

    public function theSameUrlDoesDifferentThingsPerVerb(ApiTester $I): void
    {
        $userId = $this->createUser($I);
        $this->authenticate($I);

        $I->sendGet(self::CITIES_URL);
        $I->assertArrayHasKey('data', $this->result($I));
        $I->assertSame(0, $this->requestCount($userId), 'GET must never create a request');
    }

    // ---------- cities ----------

    public function citiesListsEveryCityWithNames(ApiTester $I): void
    {
        $this->createUser($I);
        $this->authenticate($I);
        $I->sendGet(self::CITIES_URL);
        $I->seeResponseCodeIs(200);

        $data = $this->result($I);
        $I->assertTrue($data['success']);

        $expected = array_map('intval', Yii::$app->db->createCommand('SELECT id FROM city')->queryColumn());
        $actual = array_map(static fn ($c) => (int) $c['id'], $data['data']);
        sort($expected);
        sort($actual);
        $I->assertSame($expected, $actual);

        $keys = array_keys($data['data'][0]);
        sort($keys);
        $I->assertSame(['id', 'name_en', 'name_ru', 'name_uz'], $keys);
    }

    // ---------- create: success ----------

    public function createMakesARequestForTheCurrentUser(ApiTester $I): void
    {
        $userId = $this->createUser($I);
        $this->authenticate($I);
        $city = $this->city();

        $I->sendPost(self::CREATE_URL, $this->payload(['address' => 'Ташкент, ул. Навои 15', 'latitude' => '41.311081', 'longitude' => '69.240562']));
        $I->seeResponseCodeIs(200);

        $data = $this->result($I);
        $I->assertTrue($data['success'], json_encode($data, JSON_UNESCAPED_UNICODE));
        $id = (int) $data['data']['id'];

        $I->assertSame(self::PHONE, $data['data']['phone']);
        $I->assertEquals($city['id'], $data['data']['city']['id']);
        $I->assertEquals($city['region_id'], $data['data']['region']['id']);
        $I->assertEqualsWithDelta(41.311081, (float) $data['data']['latitude'], 0.000001);
        $I->assertGreaterThan(0, (int) $data['data']['created_at']);

        $I->assertEquals($userId, $this->dbValue('calling_courier_request', 'user_id', $id));
        $I->assertEquals($city['region_id'], $this->dbValue('calling_courier_request', 'region_id', $id));
        $I->assertSame('Ташкент, ул. Навои 15', $this->dbValue('calling_courier_request', 'address', $id));
    }

    public function createAcceptsAJsonBody(ApiTester $I): void
    {
        $userId = $this->createUser($I);
        $this->authenticate($I);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost(self::CREATE_URL, json_encode($this->payload()));

        $I->assertTrue($this->result($I)['success']);
        $I->assertSame(1, $this->requestCount($userId));
    }

    public function createWorksWithoutAddressAndCoordinates(ApiTester $I): void
    {
        $this->createUser($I);
        $this->authenticate($I);
        $I->sendPost(self::CREATE_URL, $this->payload());

        $I->assertTrue($this->result($I)['success']);
    }

    public function createIgnoresFieldsTheClientMustNotSet(ApiTester $I): void
    {
        $userId = $this->createUser($I);
        $otherId = $this->createUser($I, ['username' => self::OTHER_PHONE, 'access_token' => self::OTHER_TOKEN]);
        $this->authenticate($I);
        $city = $this->city();

        $I->sendPost(self::CREATE_URL, $this->payload([
            'user_id' => $otherId,
            'region_id' => 999999,
            'status' => self::STATUS_COMPLETED,
            'id' => 1,
        ]));
        $data = $this->result($I);
        $I->assertTrue($data['success'], json_encode($data, JSON_UNESCAPED_UNICODE));

        $id = (int) $data['data']['id'];
        $I->assertNotSame(1, $id);
        $I->assertEquals($userId, $this->dbValue('calling_courier_request', 'user_id', $id));
        $I->assertEquals($city['region_id'], $this->dbValue('calling_courier_request', 'region_id', $id));
        $I->assertNotEquals(self::STATUS_COMPLETED, $this->dbValue('calling_courier_request', 'status', $id));
        $I->assertSame(0, $this->requestCount($otherId));
    }

    // ---------- create: validation (HTTP 200 with success=false is the app's convention) ----------

    /** @group php8 */
    public function createReportsMissingFields(ApiTester $I): void
    {
        $userId = $this->createUser($I);
        $this->authenticate($I);
        $I->sendPost(self::CREATE_URL, []);

        $data = $this->result($I);
        $I->assertFalse($data['success']);
        // reset($model->errors) can raise a notice on PHP 8, which the catch block would turn into a generic message.
        $I->assertArrayHasKey('errors', $data, 'expected validation errors, got: ' . json_encode($data, JSON_UNESCAPED_UNICODE));
        $I->assertArrayHasKey('city_id', $data['errors']);
        $I->assertArrayHasKey('phone', $data['errors']);
        $I->assertNotEmpty($data['message']);
        $I->assertSame(0, $this->requestCount($userId));
    }

    /** @group php8 */
    public function createRejectsAnInvalidPhone(ApiTester $I): void
    {
        $userId = $this->createUser($I);
        $this->authenticate($I);

        foreach (['12345', '+998901234567', '99890123456a', ''] as $phone) {
            $I->sendPost(self::CREATE_URL, $this->payload(['phone' => $phone]));
            $data = $this->result($I);
            $I->assertFalse($data['success'], 'phone ' . json_encode($phone));
            $I->assertArrayHasKey('phone', $data['errors'] ?? [], json_encode($data, JSON_UNESCAPED_UNICODE));
        }
        $I->assertSame(0, $this->requestCount($userId));
    }

    /** @group php8 */
    public function createRejectsAnUnknownCity(ApiTester $I): void
    {
        $userId = $this->createUser($I);
        $this->authenticate($I);

        foreach ([999999999, 'abc'] as $city) {
            $I->sendPost(self::CREATE_URL, $this->payload(['city_id' => $city]));
            $data = $this->result($I);
            $I->assertFalse($data['success'], 'city ' . $city);
            $I->assertArrayHasKey('city_id', $data['errors'] ?? [], json_encode($data, JSON_UNESCAPED_UNICODE));
        }
        $I->assertSame(0, $this->requestCount($userId));
    }

    /** @group php8 */
    public function createRejectsArraysInsteadOfText(ApiTester $I): void
    {
        $userId = $this->createUser($I);
        $this->authenticate($I);

        $I->sendPost(self::CREATE_URL, $this->payload(['phone' => ['998901234567'], 'address' => ['x']]));
        $I->seeResponseCodeIs(200);
        $this->assertRejected($I, 'phone');
        $I->assertSame(0, $this->requestCount($userId));
    }

    public function createRejectsALongAddress(ApiTester $I): void
    {
        $this->createUser($I);
        $this->authenticate($I);
        $I->sendPost(self::CREATE_URL, $this->payload(['address' => str_repeat('a', 256)]));

        $data = $this->result($I);
        $I->assertFalse($data['success']);
        $I->assertArrayHasKey('address', $data['errors'] ?? []);
    }

    public function createRejectsOutOfRangeCoordinates(ApiTester $I): void
    {
        $userId = $this->createUser($I);
        $this->authenticate($I);

        $I->sendPost(self::CREATE_URL, $this->payload(['latitude' => '91', 'longitude' => '10']));
        $this->assertRejected($I, 'latitude');
        $I->sendPost(self::CREATE_URL, $this->payload(['latitude' => '10', 'longitude' => '181']));
        $this->assertRejected($I, 'longitude');
        $I->assertSame(0, $this->requestCount($userId));
    }

    public function createRejectsLatitudeWithoutLongitude(ApiTester $I): void
    {
        $userId = $this->createUser($I);
        $this->authenticate($I);
        $I->sendPost(self::CREATE_URL, $this->payload(['latitude' => '41.3']));

        $this->assertRejected($I, 'latitude');
        $I->assertSame(0, $this->requestCount($userId));
    }

    /** @group known-gap  (expected to fail until validateCoordinatesPair gets 'skipOnEmpty' => false) */
    public function createRejectsLongitudeWithoutLatitude(ApiTester $I): void
    {
        $userId = $this->createUser($I);
        $this->authenticate($I);
        $I->sendPost(self::CREATE_URL, $this->payload(['longitude' => '69.2']));

        $this->assertRejected($I, 'latitude');
        $I->assertSame(0, $this->requestCount($userId));
    }

    // ---------- create: one active request per user ----------

    public function aSecondActiveRequestIsRefused(ApiTester $I): void
    {
        $userId = $this->createUser($I);
        $this->authenticate($I);

        $I->sendPost(self::CREATE_URL, $this->payload());
        $I->assertTrue($this->result($I)['success']);

        $I->sendPost(self::CREATE_URL, $this->payload());
        $data = $this->result($I);
        $I->assertFalse($data['success']);
        $I->assertNotEmpty($data['message']);
        $I->assertSame(1, $this->requestCount($userId));
    }

    public function everyActiveStatusBlocksANewRequest(ApiTester $I): void
    {
        $userId = $this->createUser($I);
        $this->authenticate($I);

        foreach ([0, 1, 2, 3, 4] as $status) {
            $rowId = $this->insertRequest($userId, $status);
            $I->sendPost(self::CREATE_URL, $this->payload());
            $I->assertFalse($this->result($I)['success'], "status $status must block");
            Yii::$app->db->createCommand()->delete('calling_courier_request', ['id' => $rowId])->execute();
        }
    }

    public function finishedRequestsDoNotBlockANewOne(ApiTester $I): void
    {
        $userId = $this->createUser($I);
        $this->authenticate($I);
        $this->insertRequest($userId, self::STATUS_COMPLETED);
        $this->insertRequest($userId, self::STATUS_CANCELLED);

        $I->sendPost(self::CREATE_URL, $this->payload());
        $I->assertTrue($this->result($I)['success']);
        $I->assertSame(3, $this->requestCount($userId));
    }

    public function anotherUsersActiveRequestDoesNotBlock(ApiTester $I): void
    {
        $this->createUser($I);
        $otherId = $this->createUser($I, ['username' => self::OTHER_PHONE, 'access_token' => self::OTHER_TOKEN]);
        $this->insertRequest($otherId, self::STATUS_NEW);
        $this->authenticate($I);

        $I->sendPost(self::CREATE_URL, $this->payload());
        $I->assertTrue($this->result($I)['success']);
    }
}