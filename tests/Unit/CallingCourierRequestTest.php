<?php

declare(strict_types=1);

namespace Tests\Unit;

use app\models\CallingCourierRequest;
use app\models\City;
use app\models\User;
use Codeception\Test\Unit;
use Tests\Support\CreatesRecords;
use Tests\Support\UnitTester;
use Yii;

/**
 * Unit tests for the CallingCourierRequest model: rules, relations, helpers. No HTTP.
 */
final class CallingCourierRequestTest extends Unit
{
    use CreatesRecords;

    protected UnitTester $tester;

    private function city(): array
    {
        $row = Yii::$app->db->createCommand('SELECT id, region_id FROM city ORDER BY id LIMIT 1')->queryOne();
        $this->assertNotEmpty($row, 'the test database needs at least one city');

        return $row;
    }

    private function valid(array $override = []): CallingCourierRequest
    {
        $model = new CallingCourierRequest();
        $model->setAttributes(array_merge([
            'city_id' => $this->city()['id'],
            'phone' => '998901234567',
        ], $override));

        return $model;
    }

    private function errorsOf(CallingCourierRequest $model): array
    {
        $model->validate();

        return $model->getErrors();
    }

    private function userId(): int
    {
        return $this->insertRow('user', [
            'username' => 'unit-' . bin2hex(random_bytes(4)),
            'access_token' => bin2hex(random_bytes(16)),
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    public function testMinimalValidRequestPasses(): void
    {
        $model = $this->valid();
        $this->assertTrue($model->validate(), json_encode($model->errors));
    }

    public function testCityAndPhoneAreRequired(): void
    {
        $model = new CallingCourierRequest();
        $this->assertFalse($model->validate());
        $this->assertArrayHasKey('city_id', $model->errors);
        $this->assertArrayHasKey('phone', $model->errors);
    }

    public function testPhoneFormat(): void
    {
        foreach (['998901234567'] as $good) {
            $this->assertTrue($this->valid(['phone' => $good])->validate(), $good);
        }
        // too short, too long, wrong country code, plus sign, letters, spaces, injected newline
        foreach (['99890123456', '9989012345678', '998', '901234567', '+998901234567', '99890123456a', '998 901234567', "998901234567\n"] as $bad) {
            $model = $this->valid(['phone' => $bad]);
            $this->assertFalse($model->validate(), 'must be rejected: ' . json_encode($bad));
            $this->assertArrayHasKey('phone', $model->errors);
        }
    }

    public function testCityMustBeAnIntegerThatExists(): void
    {
        $this->assertArrayHasKey('city_id', $this->errorsOf($this->valid(['city_id' => 'abc'])));
        $this->assertArrayHasKey('city_id', $this->errorsOf($this->valid(['city_id' => 999999999])));
    }

    public function testAddressLengthLimit(): void
    {
        $this->assertTrue($this->valid(['address' => str_repeat('a', 255)])->validate());
        $this->assertArrayHasKey('address', $this->errorsOf($this->valid(['address' => str_repeat('a', 256)])));
        $this->assertTrue($this->valid(['address' => 'Ташкент, ул. Навои 15'])->validate());
    }

    public function testCoordinateRanges(): void
    {
        $this->assertTrue($this->valid(['latitude' => '90', 'longitude' => '180'])->validate());
        $this->assertTrue($this->valid(['latitude' => '-90', 'longitude' => '-180'])->validate());
        $this->assertArrayHasKey('latitude', $this->errorsOf($this->valid(['latitude' => '90.0001', 'longitude' => '10'])));
        $this->assertArrayHasKey('longitude', $this->errorsOf($this->valid(['latitude' => '10', 'longitude' => '-180.5'])));
        $this->assertArrayHasKey('latitude', $this->errorsOf($this->valid(['latitude' => 'north', 'longitude' => '10'])));
    }

    public function testCoordinatesMayBeOmittedTogether(): void
    {
        $this->assertTrue($this->valid()->validate());
        $this->assertTrue($this->valid(['latitude' => '', 'longitude' => ''])->validate());
        $this->assertTrue($this->valid(['latitude' => null, 'longitude' => null])->validate());
    }

    public function testLatitudeWithoutLongitudeIsRejected(): void
    {
        $errors = $this->errorsOf($this->valid(['latitude' => '41.3']));
        $this->assertArrayHasKey('latitude', $errors);
        $this->assertArrayHasKey('longitude', $errors);
    }

    /**
     * @group known-gap
     * validateCoordinatesPair is attached to `latitude`, and inline validators skip empty values by default,
     * so a request with ONLY a longitude is probably accepted. Fix: add 'skipOnEmpty' => false to that rule.
     */
    public function testLongitudeWithoutLatitudeIsRejected(): void
    {
        $errors = $this->errorsOf($this->valid(['longitude' => '69.2']));
        $this->assertNotEmpty($errors, 'longitude without latitude must be rejected');
    }

    public function testUnsafeAttributesCannotBeMassAssigned(): void
    {
        $model = $this->valid(['user_id' => 12345, 'region_id' => 999, 'status' => CallingCourierRequest::STATUS_COMPLETED, 'id' => 77]);
        $fresh = new CallingCourierRequest();

        $this->assertNull($model->user_id);
        $this->assertNull($model->region_id);
        $this->assertNull($model->id);
        $this->assertSame($fresh->status, $model->status);
    }

    public function testStatusConstantsAreDistinct(): void
    {
        $values = [
            CallingCourierRequest::STATUS_PENDING, CallingCourierRequest::STATUS_NEW, CallingCourierRequest::STATUS_ASSIGNED,
            CallingCourierRequest::STATUS_ACCEPTED, CallingCourierRequest::STATUS_ON_THE_WAY,
            CallingCourierRequest::STATUS_COMPLETED, CallingCourierRequest::STATUS_CANCELLED,
        ];
        $this->assertCount(7, array_unique($values));
    }

    public function testTableName(): void
    {
        $this->assertSame('calling_courier_request', CallingCourierRequest::getTableSchema()->name);
    }

    public function testCityAndRegionRelations(): void
    {
        $city = $this->city();
        $userId = $this->userId();
        $id = $this->insertRow('calling_courier_request', [
            'user_id' => $userId,
            'city_id' => $city['id'],
            'region_id' => $city['region_id'],
            'phone' => '998901234567',
            'status' => CallingCourierRequest::STATUS_NEW,
        ]);

        $model = CallingCourierRequest::findOne($id);
        $this->assertInstanceOf(City::class, $model->city);
        $this->assertEquals($city['id'], $model->city->id);
        $this->assertEquals($city['region_id'], $model->region->id);
    }

    public function testHasPendingRequest(): void
    {
        $city = $this->city();
        $userId = $this->userId();
        $this->assertFalse(CallingCourierRequest::hasPendingRequest($userId));

        $this->insertRow('calling_courier_request', [
            'user_id' => $userId, 'city_id' => $city['id'], 'region_id' => $city['region_id'],
            'phone' => '998901234567', 'status' => CallingCourierRequest::STATUS_NEW,
        ]);
        $this->assertFalse(CallingCourierRequest::hasPendingRequest($userId), 'NEW is not PENDING');

        $this->insertRow('calling_courier_request', [
            'user_id' => $userId, 'city_id' => $city['id'], 'region_id' => $city['region_id'],
            'phone' => '998901234567', 'status' => CallingCourierRequest::STATUS_PENDING,
        ]);
        $this->assertTrue(CallingCourierRequest::hasPendingRequest($userId));
    }

    public function testTimestampBehaviorFillsCreatedAt(): void
    {
        $city = $this->city();
        $model = $this->valid();
        $model->user_id = $this->userId();
        $model->region_id = $city['region_id'];

        $this->assertTrue($model->save(false));
        $model->refresh();
        $this->assertGreaterThan(0, (int) $model->created_at);
    }
}