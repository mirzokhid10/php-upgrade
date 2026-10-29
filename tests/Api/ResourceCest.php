<?php

declare(strict_types=1);

namespace Tests\Api;

use app\models\User;
use Codeception\Attribute\Group;
use Codeception\Scenario;
use Tests\Support\ApiTester;
use Tests\Support\CreatesRecords;
use Tests\Support\CreatesUsers;
use Yii;

/**
 * Functional (HTTP-level) tests for app\controllers\ResourceController (lookup lists).
 *
 *   GET /resource/{regions,cities,cities-by-region,clients,status,parcel-types,parcel-lists,
 *                  return-types,status-labels,version}
 *
 * Any logged-in user may call them. Answers are wrapped by app\components\Serializer:
 * {"status":"OK","code":200,"message":"Success","data": ...}
 *
 * Run:  vendor/bin/codecept run Api ResourceCest
 */
class ResourceCest
{
    use CreatesUsers;
    use CreatesRecords;

    private const LOOKUP_TOKEN = 'resource-token-0001';

    public function _before(ApiTester $I): void
    {
        Yii::$app->security->passwordHashCost = 4;
    }

    // ================================================================ helpers

    private function login(ApiTester $I): void
    {
        $this->createUser($I, [
            'username' => '998901110010',
            'access_token' => self::LOOKUP_TOKEN,
            'role_id' => User::ROLE_COURIER,
        ]);
        $I->amBearerAuthenticated(self::LOOKUP_TOKEN);
    }

    private function payload(ApiTester $I): mixed
    {
        return $this->json($I)['data'] ?? null;
    }

    private function dbCount(string $sql, array $params = []): int
    {
        return (int) Yii::$app->db->createCommand($sql, $params)->queryScalar();
    }

    private function appVersionIsTransactional(): bool
    {
        $engine = Yii::$app->db->createCommand(
            "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_version'"
        )->queryScalar();

        return strtoupper((string) $engine) === 'INNODB';
    }

    // ================================================================ access

    public function everyRouteRequiresAToken(ApiTester $I): void
    {
        foreach ([
            'regions', 'cities', 'cities-by-region?id=1', 'clients', 'status', 'parcel-types',
            'parcel-lists', 'return-types', 'status-labels', 'version',
        ] as $path) {
            $I->sendGet('/resource/' . $path);
            $I->seeResponseCodeIs(401);
        }
    }

    // ================================================================ database lists

    public function regionsListsEveryRegion(ApiTester $I): void
    {
        $this->login($I);

        $I->sendGet('/resource/regions');

        $I->seeResponseCodeIs(200);
        $rows = (array) $this->payload($I);
        $I->assertSame($this->dbCount('SELECT COUNT(*) FROM region'), count($rows));
        $I->assertArrayHasKey('id', $rows[0] ?? []);
    }

    public function citiesListsEveryCity(ApiTester $I): void
    {
        $this->login($I);

        $I->sendGet('/resource/cities');

        $I->seeResponseCodeIs(200);
        $rows = (array) $this->payload($I);
        $I->assertSame($this->dbCount('SELECT COUNT(*) FROM city'), count($rows));
        $I->assertArrayHasKey('region_id', $rows[0] ?? []);
    }

    public function clientsListsEveryClient(ApiTester $I): void
    {
        $this->login($I);

        $I->sendGet('/resource/clients');

        $I->seeResponseCodeIs(200);
        $I->assertSame($this->dbCount('SELECT COUNT(*) FROM clients'), count((array) $this->payload($I)));
    }

    /**
     * KNOWN GAP (expected to fail today): the query is `SELECT * FROM clients`, so every logged-in
     * user - including a courier - receives bank account, MFO, VAT code, director and accountant of
     * every client. If the app only needs id + name, select just those columns.
     */
    #[Group('known-gap')]
    public function clientsListDoesNotExposeBankDetails(ApiTester $I): void
    {
        $this->login($I);

        $I->sendGet('/resource/clients');

        $rows = (array) $this->payload($I);
        $row = $rows[0] ?? [];
        $I->assertNotEmpty($row, 'the test database needs at least one client');
        foreach (['bank_account', 'bank_name', 'mfo', 'vat_payer_code', 'director_fullname', 'main_accountant'] as $field) {
            $I->assertArrayNotHasKey($field, $row, "`$field` is sent to every logged-in user");
        }
    }

    public function statusLabelsListsEveryStatus(ApiTester $I): void
    {
        $this->login($I);

        $I->sendGet('/resource/status-labels');

        $I->seeResponseCodeIs(200);
        $rows = (array) $this->payload($I);
        $I->assertSame($this->dbCount('SELECT COUNT(*) FROM status'), count($rows));
        foreach (['id', 'name', 'description_uz', 'description_ru', 'description_en'] as $field) {
            $I->assertArrayHasKey($field, $rows[0] ?? [], "`$field` is expected in a status label");
        }
    }

    // ================================================================ cities-by-region

    public function citiesByRegionReturnsOnlyThatRegion(ApiTester $I): void
    {
        $regionId = (int) Yii::$app->db->createCommand(
            'SELECT region_id FROM city WHERE region_id IS NOT NULL GROUP BY region_id ORDER BY COUNT(*) DESC LIMIT 1'
        )->queryScalar();
        $this->login($I);

        $I->sendGet('/resource/cities-by-region?id=' . $regionId);

        $I->seeResponseCodeIs(200);
        $rows = (array) $this->payload($I);
        $I->assertNotEmpty($rows);
        $I->assertSame($this->dbCount('SELECT COUNT(*) FROM city WHERE region_id = :id', [':id' => $regionId]), count($rows));
        foreach ($rows as $row) {
            $I->assertEquals($regionId, $row['region_id']);
        }
    }

    public function citiesByRegionNeedsAnId(ApiTester $I): void
    {
        $this->login($I);

        $I->sendGet('/resource/cities-by-region');

        $I->seeResponseCodeIs(400);
    }

    public function citiesByRegionReturnsAnEmptyListForAnUnknownRegion(ApiTester $I): void
    {
        $this->login($I);

        $I->sendGet('/resource/cities-by-region?id=2147483000');

        $I->seeResponseCodeIs(200);
        $I->assertSame([], $this->payload($I));
    }

    public function citiesByRegionIsNotSqlInjectable(ApiTester $I): void
    {
        $this->login($I);
        // MySQL turns the text '1 OR 1=1' into the number 1 when it compares it with an integer
        // column, so the answer is region 1's cities - never "all cities".
        $expected = $this->dbCount('SELECT COUNT(*) FROM city WHERE region_id = 1');

        $I->sendGet('/resource/cities-by-region?id=' . rawurlencode('1 OR 1=1'));

        $I->seeResponseCodeIs(200);
        $I->assertSame($expected, count((array) $this->payload($I)));
    }

    // ================================================================ fixed lists

    public function parcelTypesAreFixed(ApiTester $I): void
    {
        $this->login($I);

        $I->sendGet('/resource/parcel-types');

        $I->assertEquals([1 => 'box', 2 => 'document', 3 => 'package'], $this->payload($I));
    }

    public function parcelListsAreFixed(ApiTester $I): void
    {
        $this->login($I);

        $I->sendGet('/resource/parcel-lists');

        $I->assertSame(['smartphone', 'smartwatch', 'book'], $this->payload($I));
    }

    public function returnTypesAreFixed(ApiTester $I): void
    {
        $this->login($I);

        $I->sendGet('/resource/return-types');

        $I->assertEquals([1 => 'money', 2 => 'document', 3 => 'waybill'], $this->payload($I));
    }

    public function statusNamesIncludeTheStatusesUsedByTheApi(ApiTester $I): void
    {
        $this->login($I);

        $I->sendGet('/resource/status');

        $status = (array) $this->payload($I);
        $I->assertCount(21, $status);
        $I->assertEquals('delivered', $status[11] ?? null);   // set by confirm-delivery
        $I->assertEquals('handover', $status[57] ?? null);    // set by handover-status
    }

    // ================================================================ version

    public function versionFallsBackTo1Point0WhenNoVersionIsActive(ApiTester $I, Scenario $scenario): void
    {
        if (!$this->appVersionIsTransactional()) {
            $scenario->skip('app_version is not InnoDB, so this test could not be rolled back.');
        }
        Yii::$app->db->createCommand()->update('app_version', ['status' => 0], ['status' => 1])->execute();
        $this->login($I);

        $I->sendGet('/resource/version');

        $I->seeResponseCodeIs(200);
        $I->assertSame('1.0', $this->payload($I));
    }

    public function versionReturnsTheActiveVersionNumber(ApiTester $I, Scenario $scenario): void
    {
        if (!$this->appVersionIsTransactional()) {
            $scenario->skip('app_version is not InnoDB, so this test could not be rolled back.');
        }
        Yii::$app->db->createCommand()->update('app_version', ['status' => 0], ['status' => 1])->execute();
        $this->insertRow('app_version', ['number' => '9.9.9', 'status' => 1]);
        $this->login($I);

        $I->sendGet('/resource/version');

        $I->assertSame('9.9.9', $this->payload($I));
    }

    // ================================================================ compat

    /**
     * COMPAT (PHP 8.1): see WaybillCest::numericColumnsKeepLegacyStringTypes.
     * `Query::all()` returns raw rows, so ids changed from "12" to 12.
     * Expected to fail until PDO::ATTR_STRINGIFY_FETCHES is enabled; delete it if numbers are fine.
     */
    #[Group('compat')]
    public function regionIdsKeepLegacyStringTypes(ApiTester $I): void
    {
        $this->login($I);

        $I->sendGet('/resource/regions');

        $rows = (array) $this->payload($I);
        $I->assertSame('integer', gettype(($rows[0] ?? [])['id'] ?? null), 'id used to be "12", now it is 12');
    }
}