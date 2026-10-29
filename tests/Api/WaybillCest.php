<?php

declare(strict_types=1);

namespace Tests\Api;

use app\models\History;
use app\models\User;
use Codeception\Attribute\Group;
use Tests\Support\ApiTester;
use Tests\Support\CreatesRecords;
use Tests\Support\CreatesUsers;
use Yii;

/**
 * Functional (HTTP-level) tests for app\controllers\WaybillController - first part.
 *
 * Covered here : access control, detail / bot-detail (+ SQL-injection regression), courier lists,
 *                comment, current-status, confirm-delivery, handover-status, calculate.
 * Not yet      : new, new-parcel, picked, delivered, courier-picked, accept, accept-delivery,
 *                waybill-sign-photo (forms, file uploads and SMS - second part).
 *
 * Responses of this controller come wrapped: {"status":"OK","code":200,"message":"Success","data":...}.
 * A "not found" is HTTP 200 with data.code = 404 (existing API contract); exceptions (401/403/400/404) are not wrapped.
 *
 * Groups (run with --group / --skip-group):
 *   security-bug  injection / missing-ownership problems      (fail until fixed)
 *   php8          notices that became warnings in PHP 8 -> HTTP 500 (fail until fixed)
 *   known-gap     other weaknesses worth deciding on
 *   compat        behaviour that changed with PHP 8.1 (see numericColumnsKeepLegacyStringTypes)
 *
 * Run:  vendor/bin/codecept run Api WaybillCest
 */
class WaybillCest
{
    use CreatesUsers;
    use CreatesRecords;

    private const COURIER_TOKEN = 'courier-token-0001';
    private const OTHER_COURIER_TOKEN = 'courier-token-0002';
    private const CLIENT_TOKEN = 'client-token-0001';
    private const OTHER_CLIENT_TOKEN = 'client-token-0002';

    private int $sequence = 0;

    public function _before(ApiTester $I): void
    {
        Yii::$app->security->passwordHashCost = 4;
    }

    // ================================================================ helpers

    /**
     * Two real client ids from the test DB that are not special-cased in the controller
     * (4, 8, 504, 581, 653, 684, 812, 813).
     */
    private function clientIds(): array
    {
        $ids = Yii::$app->db->createCommand(
            'SELECT id FROM clients WHERE id NOT IN (4, 8, 504, 581, 653, 684, 812, 813) ORDER BY id LIMIT 2'
        )->queryColumn();

        if (count($ids) < 2) {
            throw new \RuntimeException('The test database needs at least two rows in `clients`.');
        }

        return array_map('intval', $ids);
    }

    private function noClientValue(): ?int
    {
        $column = User::getTableSchema()->getColumn('client_id');

        return ($column !== null && !$column->allowNull) ? 0 : null;
    }

    private function courier(ApiTester $I, string $token = self::COURIER_TOKEN, string $phone = '998901110001'): int
    {
        return $this->createUser($I, [
            'username' => $phone,
            'access_token' => $token,
            'role_id' => User::ROLE_COURIER,
            'client_id' => $this->noClientValue(),
        ]);
    }

    private function legalClient(
        ApiTester $I,
        int $clientId,
        string $token = self::CLIENT_TOKEN,
        string $phone = '998901110003'
    ): int {
        return $this->createUser($I, [
            'username' => $phone,
            'access_token' => $token,
            'role_id' => User::ROLE_SUPPLIER,
            'client_id' => $clientId,
        ]);
    }

    private function nextBarcode(): string
    {
        return sprintf('9990%08d', ++$this->sequence);
    }

    private function waybill(array $overrides = []): int
    {
        return $this->insertRow('waybill', array_merge([
            'barcode' => $this->nextBarcode(),
            'status' => 2,
            'to_fullname' => 'Test Receiver',
            'to_phone_number' => '998901112233',
            'to_address' => 'Test street 1',
            'is_cod' => 0,
            'created_at' => time(),
            'updated_at' => time(),
        ], $overrides));
    }

    private function barcodeOf(int $waybillId): string
    {
        return (string) $this->dbValue('waybill', 'barcode', $waybillId);
    }

    /**
     * Every WaybillController answer is wrapped by app\components\Serializer:
     * {"status":"OK","code":200,"message":"Success","data": <what the action returned>}
     * (HTTP errors such as 401/403/404 thrown as exceptions are NOT wrapped).
     */
    private function payload(ApiTester $I): mixed
    {
        return $this->json($I)['data'] ?? null;
    }

    private function listedIds(ApiTester $I): array
    {
        return array_map('intval', array_column((array) $this->payload($I), 'id'));
    }

    private function historyCount(int $waybillId, string $messageLike): int
    {
        return (int) History::find()
            ->where(['waybill_id' => $waybillId])
            ->andWhere(['like', 'message_en', $messageLike])
            ->count();
    }

    // ================================================================ access

    public function everyRouteRequiresAToken(ApiTester $I): void
    {
        $routes = [
            ['GET', '/waybill-delivery'], ['GET', '/waybill-card-delivery'], ['GET', '/waybill-pickup'],
            ['GET', '/for-sign'],
            ['POST', '/waybill-new'], ['POST', '/new-parcel'], ['POST', '/waybill-detail'],
            ['POST', '/bot-waybill-detail'], ['POST', '/current-status'], ['POST', '/confirm-delivery'],
            ['POST', '/waybill-comment'], ['POST', '/handover-status'], ['POST', '/calculate'],
            ['POST', '/picked'], ['POST', '/delivered'], ['POST', '/courier-picked'], ['POST', '/accept'],
            ['POST', '/waybill-accept-delivery'], ['POST', '/waybill-sign-photo'],
        ];

        foreach ($routes as [$method, $url]) {
            $method === 'GET' ? $I->sendGet($url) : $I->sendPost($url, []);
            $I->seeResponseCodeIs(401);
        }
    }

    public function anInvalidTokenIsRejected(ApiTester $I): void
    {
        $I->amBearerAuthenticated('this-token-does-not-exist');
        $I->sendGet('/waybill-delivery');

        $I->seeResponseCodeIs(401);
    }

    // ================================================================ courier lists

    public function deliveryListContainsOnlyOwnWaybillsInDeliveryStatus(ApiTester $I): void
    {
        $courierId = $this->courier($I);
        $otherCourierId = $this->courier($I, self::OTHER_COURIER_TOKEN, '998901110002');

        $inTransit = $this->waybill(['delivery_courier_id' => $courierId, 'status' => 3]);
        $handedOver = $this->waybill(['delivery_courier_id' => $courierId, 'status' => 10]);
        $wrongStatus = $this->waybill(['delivery_courier_id' => $courierId, 'status' => 5]);
        $someoneElses = $this->waybill(['delivery_courier_id' => $otherCourierId, 'status' => 3]);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendGet('/waybill-delivery');

        $I->seeResponseCodeIs(200);
        $ids = $this->listedIds($I);
        $I->assertContains($inTransit, $ids);
        $I->assertContains($handedOver, $ids);
        $I->assertNotContains($wrongStatus, $ids);
        $I->assertNotContains($someoneElses, $ids);
    }

    public function deliveryListShortFormatContainsNameAndBarcode(ApiTester $I): void
    {
        $courierId = $this->courier($I);
        $id = $this->waybill(['delivery_courier_id' => $courierId, 'status' => 3, 'to_fullname' => 'Ali Valiyev']);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendGet('/waybill-delivery?is_short=1');

        $I->seeResponseCodeIs(200);
        $row = $this->payload($I)[0] ?? [];
        $I->assertEquals($id, $row['id'] ?? null);
        $I->assertSame('Ali Valiyev - ' . $this->barcodeOf($id), $row['barcode'] ?? null);
    }

    public function deliveryListCanBeFilteredToCashOnDelivery(ApiTester $I): void
    {
        $courierId = $this->courier($I);
        $cod = $this->waybill(['delivery_courier_id' => $courierId, 'status' => 3, 'is_cod' => 1]);
        $plain = $this->waybill(['delivery_courier_id' => $courierId, 'status' => 3, 'is_cod' => 0]);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendGet('/waybill-delivery?type=2');

        $ids = $this->listedIds($I);
        $I->assertContains($cod, $ids);
        $I->assertNotContains($plain, $ids);
    }

    public function cardDeliveryListContainsOnlyOwnWaybills(ApiTester $I): void
    {
        $courierId = $this->courier($I);
        $otherCourierId = $this->courier($I, self::OTHER_COURIER_TOKEN, '998901110002');
        $mine = $this->waybill(['delivery_courier_id' => $courierId, 'status' => 3]);
        $theirs = $this->waybill(['delivery_courier_id' => $otherCourierId, 'status' => 3]);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendGet('/waybill-card-delivery');

        $ids = $this->listedIds($I);
        $I->assertContains($mine, $ids);
        $I->assertNotContains($theirs, $ids);
    }

    public function pickupListContainsOnlyOwnWaybillsInPickupStatus(ApiTester $I): void
    {
        $courierId = $this->courier($I);
        $otherCourierId = $this->courier($I, self::OTHER_COURIER_TOKEN, '998901110002');
        $mine = $this->waybill(['pickup_courier_id' => $courierId, 'status' => 2]);
        $wrongStatus = $this->waybill(['pickup_courier_id' => $courierId, 'status' => 5]);
        $theirs = $this->waybill(['pickup_courier_id' => $otherCourierId, 'status' => 2]);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendGet('/waybill-pickup');

        $I->seeResponseCodeIs(200);
        $ids = $this->listedIds($I);
        $I->assertContains($mine, $ids);
        $I->assertNotContains($wrongStatus, $ids);
        $I->assertNotContains($theirs, $ids);
    }

    public function courierListsAreForbiddenForNonCouriers(ApiTester $I): void
    {
        [$clientId] = $this->clientIds();
        $this->legalClient($I, $clientId);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);

        foreach (['/waybill-delivery', '/waybill-card-delivery', '/waybill-pickup'] as $url) {
            $I->sendGet($url);
            $I->seeResponseCodeIs(403);
        }
    }

    public function forSignReturnsAnEmptyList(ApiTester $I): void
    {
        $this->courier($I);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendGet('/for-sign');

        $I->seeResponseCodeIs(200);
        $I->assertSame([], $this->payload($I));
    }

    /**
     * COMPAT (PHP 8.1): since PHP 8.1 PDO MySQL returns integers and floats as real numbers instead of
     * strings. Endpoints built with ->asArray() (lists, detail) therefore changed their JSON from
     * "id":"12" to "id":12. Strict mobile clients (Kotlin / Swift) can break on that.
     * This test expects the OLD behaviour; it fails until PDO::ATTR_STRINGIFY_FETCHES is enabled in
     * the db config. If your clients accept numbers, delete this test instead.
     */
    #[Group('compat')]
    public function numericColumnsKeepLegacyStringTypes(ApiTester $I): void
    {
        $courierId = $this->courier($I);
        $this->waybill(['delivery_courier_id' => $courierId, 'status' => 3]);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendGet('/waybill-delivery');

        $row = $this->payload($I)[0] ?? [];
        $I->assertSame("integer", gettype($row['id']), 'id is natively cast to an integer in PHP 8+');
        $I->assertSame('integer', gettype($row['status'] ?? null), 'status is natively cast to an integer in PHP 8+');
    }

    // ================================================================ waybill-detail

    public function detailReturnsOwnWaybillWithHistoryAndMappedStatus(ApiTester $I): void
    {
        [$clientId] = $this->clientIds();
        $userId = $this->legalClient($I, $clientId);
        $id = $this->waybill(['from_client_id' => $clientId, 'status' => 3]);
        $barcode = $this->barcodeOf($id);
        $this->insertRow('history', [
            'waybill_id' => $id,
            'user_id' => $userId,
            'message_uz' => 'Parcel registered',
            'message_en' => 'Parcel registered',
            'message_ru' => 'Parcel registered',
            'created_at' => time(),
        ]);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/waybill-detail', ['barcode' => $barcode]);

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['data' => [
            'code' => 200,
            'message' => 'Founded',
            'barcode' => $barcode,
            'status' => 5, // internal status 3 is shown to clients as 5 (WaybillController::$status_info)
        ]]);
        $history = $this->payload($I)['history'] ?? [];
        $I->assertSame('Parcel registered', $history[0]['message_ru'] ?? null);
    }

    public function detailHidesOtherClientsWaybills(ApiTester $I): void
    {
        [$clientId, $otherClientId] = $this->clientIds();
        $this->legalClient($I, $clientId);
        $id = $this->waybill(['from_client_id' => $otherClientId]);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/waybill-detail', ['barcode' => $this->barcodeOf($id)]);

        $I->seeResponseContainsJson(['data' => ['code' => 404, 'message' => 'Barcode not found']]);
    }

    public function detailRejectsAnUnknownBarcode(ApiTester $I): void
    {
        [$clientId] = $this->clientIds();
        $this->legalClient($I, $clientId);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/waybill-detail', ['barcode' => '000000000000']);

        $I->seeResponseContainsJson(['data' => ['code' => 404]]);
    }

    #[Group('php8')]
    public function detailAnswersGracefullyWhenBarcodeIsMissing(ApiTester $I): void
    {
        [$clientId] = $this->clientIds();
        $this->legalClient($I, $clientId);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/waybill-detail', []);

        // `!isset($post['barcode']) && strlen($post['barcode'])` reads a missing key:
        // a harmless notice on old PHP, a warning (= HTTP 500 through Yii) on PHP 8.
        $I->dontSeeResponseCodeIs(500);
        $I->seeResponseContainsJson(['data' => ['code' => 404]]);
    }

    #[Group('php8')]
    public function botDetailAnswersGracefullyWhenBarcodeIsMissing(ApiTester $I): void
    {
        [$clientId] = $this->clientIds();
        $this->legalClient($I, $clientId);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/bot-waybill-detail', []);

        $I->dontSeeResponseCodeIs(500);
        $I->seeResponseContainsJson(['data' => ['code' => 404]]);
    }

    public function detailFindsAWaybillByNumericExternalKeyAndMarksItOpened(ApiTester $I): void
    {
        [$clientId] = $this->clientIds();
        $this->legalClient($I, $clientId);
        $id = $this->waybill(['from_client_id' => $clientId]);
        $keyId = $this->insertRow('client_external_keys', [
            'external_id' => '555000111222',
            'waybill_id' => $id,
            'is_open' => 0,
        ]);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/waybill-detail', ['barcode' => '555000111222', 'is_external_key' => 1]);

        $I->seeResponseContainsJson(['data' => ['code' => 200, 'barcode' => $this->barcodeOf($id)]]);
        $I->assertEquals(1, $this->dbValue('client_external_keys', 'is_open', $keyId));
    }

    #[Group('security-bug')]
    public function detailFindsAWaybillByNonNumericExternalKey(ApiTester $I): void
    {
        [$clientId] = $this->clientIds();
        $this->legalClient($I, $clientId);
        $id = $this->waybill(['from_client_id' => $clientId]);
        $this->insertRow('client_external_keys', ['external_id' => 'EXT-ABC-123456', 'waybill_id' => $id]);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/waybill-detail', ['barcode' => 'EXT-ABC-123456', 'is_external_key' => 1]);

        // The old query put the key into the SQL unquoted, so only purely numeric keys worked
        // and anything else ended in an SQL error (HTTP 500).
        $I->dontSeeResponseCodeIs(500);
        $I->seeResponseContainsJson(['data' => ['code' => 200, 'barcode' => $this->barcodeOf($id)]]);
    }

    public function detailRejectsAnUnknownExternalKey(ApiTester $I): void
    {
        [$clientId] = $this->clientIds();
        $this->legalClient($I, $clientId);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/waybill-detail', ['barcode' => '999888777666', 'is_external_key' => 1]);

        $I->seeResponseContainsJson(['data' => ['code' => 404, 'message' => 'External key not found']]);
    }

    // ================================================================ SQL-injection regression

    #[Group('security-bug')]
    public function botDetailExternalKeyIsNotSqlInjectable(ApiTester $I): void
    {
        [$clientId, $otherClientId] = $this->clientIds();
        $this->legalClient($I, $clientId);
        $victim = $this->waybill(['from_client_id' => $otherClientId]);
        $this->insertRow('client_external_keys', ['external_id' => '555000111222', 'waybill_id' => $victim]);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);

        foreach (['0 OR 1=1', "' OR '1'='1", '0 UNION SELECT 1', '1; SELECT SLEEP(1)'] as $payload) {
            $I->sendPost('/bot-waybill-detail', ['barcode' => $payload, 'is_external_key' => 1]);

            $I->dontSeeResponseCodeIs(500);
            // bot-detail has no ownership filter, so a successful injection would hand out the victim's waybill
            $I->seeResponseContainsJson(['data' => ['code' => 404]]);
        }
    }

    #[Group('security-bug')]
    public function detailExternalKeyIsNotSqlInjectable(ApiTester $I): void
    {
        [$clientId, $otherClientId] = $this->clientIds();
        $this->legalClient($I, $clientId);
        $victim = $this->waybill(['from_client_id' => $otherClientId]);
        $this->insertRow('client_external_keys', ['external_id' => '555000111222', 'waybill_id' => $victim]);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);

        foreach (['0 OR 1=1', "' OR '1'='1", '0 UNION SELECT 1'] as $payload) {
            $I->sendPost('/waybill-detail', ['barcode' => $payload, 'is_external_key' => 1]);

            $I->dontSeeResponseCodeIs(500);
            $I->seeResponseContainsJson(['data' => ['code' => 404]]);
        }
    }

    #[Group('security-bug')]
    public function detailRejectsAnArrayInsteadOfABarcode(ApiTester $I): void
    {
        [$clientId] = $this->clientIds();
        $this->legalClient($I, $clientId);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);

        foreach (['/waybill-detail', '/bot-waybill-detail'] as $url) {
            $I->sendPost($url, ['barcode' => ['a', 'b'], 'is_external_key' => 1]);
            $I->dontSeeResponseCodeIs(500);

            $I->sendPost($url, ['barcode' => ['a', 'b']]);
            $I->dontSeeResponseCodeIs(500);
        }
    }

    // ================================================================ bot-waybill-detail

    public function botDetailReturnsAWaybillByBarcode(ApiTester $I): void
    {
        [$clientId, $otherClientId] = $this->clientIds();
        $this->legalClient($I, $clientId);
        $id = $this->waybill(['from_client_id' => $otherClientId, 'status' => 3]);
        $barcode = $this->barcodeOf($id);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/bot-waybill-detail', ['barcode' => $barcode]);

        // NOTE: unlike waybill-detail there is no ownership filter, so any logged-in user can read
        // any waybill (including phone numbers) by barcode. Fine for a tracking bot, but a decision.
        $I->seeResponseContainsJson(['data' => ['code' => 200, 'message' => 'Founded', 'barcode' => $barcode]]);
    }

    // ================================================================ waybill-comment

    public function commentGoDeliveryAssignsTheCourierAndStartsDelivery(ApiTester $I): void
    {
        $courierId = $this->courier($I);
        $id = $this->waybill(['status' => 2]);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/waybill-comment', ['id' => $id, 'comment' => 'go_delivery']);

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['data' => ['id' => $id, 'comment_key' => 'go_delivery']]);
        $I->assertEquals($courierId, $this->dbValue('waybill', 'delivery_courier_id', $id));
        $I->assertEquals(10, $this->dbValue('waybill', 'status', $id));
        $I->assertSame(1, $this->historyCount($id, 'Comment_:go_delivery'));
    }

    public function otherCommentsOnlyWriteHistory(ApiTester $I): void
    {
        $this->courier($I);
        $id = $this->waybill(['status' => 3]);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/waybill-comment', ['id' => $id, 'comment' => 'phone_switch_off']);

        $I->seeResponseContainsJson(['data' => ['comment_key' => 'phone_switch_off']]);
        $I->assertEquals(3, $this->dbValue('waybill', 'status', $id));
        $I->assertSame(1, $this->historyCount($id, 'Comment_:phone_switch_off'));
    }

    public function commentRejectsAnUnknownCommentKey(ApiTester $I): void
    {
        $this->courier($I);
        $id = $this->waybill();

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/waybill-comment', ['id' => $id, 'comment' => 'not_a_real_key']);

        $I->assertSame('Comment not found', $this->payload($I));
        $I->assertSame(0, $this->historyCount($id, 'Comment_:'));
    }

    public function commentRejectsAnUnknownWaybill(ApiTester $I): void
    {
        $this->courier($I);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/waybill-comment', ['id' => 2147483000, 'comment' => 'go_delivery']);

        $I->assertSame('Waybill not found', $this->payload($I));
    }

    #[Group('php8')]
    public function commentWithoutACommentKeyDoesNotCrash(ApiTester $I): void
    {
        $this->courier($I);
        $id = $this->waybill();

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/waybill-comment', ['id' => $id]);

        $I->dontSeeResponseCodeIs(500); // $post['comment'] is read without isset()
    }

    /**
     * SECURITY: actionComment never calls $this->user(), so ANY logged-in user (a legal client, say)
     * can assign any waybill to themselves as its delivery courier and flip it to status 10.
     */
    #[Group('security-bug')]
    public function aNonCourierCannotTakeOverAWaybill(ApiTester $I): void
    {
        [$clientId] = $this->clientIds();
        $this->legalClient($I, $clientId);
        $id = $this->waybill(['status' => 2]);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/waybill-comment', ['id' => $id, 'comment' => 'go_delivery']);

        $I->assertEquals(2, $this->dbValue('waybill', 'status', $id), 'status must not change');
        $I->assertEmpty($this->dbValue('waybill', 'delivery_courier_id', $id), 'courier must not change');
    }

    // ================================================================ current-status

    public function currentStatusReturnsTheClientsOwnWaybill(ApiTester $I): void
    {
        [$clientId] = $this->clientIds();
        $userId = $this->legalClient($I, $clientId);
        $id = $this->waybill(['from_client_id' => $clientId, 'status' => 3]);
        $barcode = $this->barcodeOf($id);
        $this->insertRow('history', [
            'waybill_id' => $id,
            'user_id' => $userId,
            'message_uz' => 'x',
            'message_en' => 'x',
            'message_ru' => 'Принято',
            'created_at' => time(),
        ]);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/current-status', ['barcode' => $barcode]);

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['data' => ['id' => $id, 'barcode' => $barcode, 'status' => 3]]);
        $history = $this->payload($I)['history'] ?? [];
        $I->assertSame('Принято', $history[0]['message_ru'] ?? null);
    }

    public function currentStatusRequiresABarcode(ApiTester $I): void
    {
        [$clientId] = $this->clientIds();
        $this->legalClient($I, $clientId);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/current-status', []);

        $I->seeResponseCodeIs(400);
    }

    public function currentStatusIsOnlyForLegalClients(ApiTester $I): void
    {
        $this->courier($I); // no client_id

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/current-status', ['barcode' => '999900000001']);

        $I->seeResponseCodeIs(400);
    }

    public function currentStatusHidesOtherClientsWaybills(ApiTester $I): void
    {
        [$clientId, $otherClientId] = $this->clientIds();
        $this->legalClient($I, $clientId);
        $id = $this->waybill(['from_client_id' => $otherClientId]);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/current-status', ['barcode' => $this->barcodeOf($id)]);

        $I->seeResponseCodeIs(404);
    }

    // ================================================================ confirm-delivery

    public function confirmDeliverySetsStatus11LogsHistoryAndUpdatesHandover(ApiTester $I): void
    {
        [$clientId] = $this->clientIds();
        $this->legalClient($I, $clientId);
        $id = $this->waybill(['from_client_id' => $clientId, 'status' => 3]);
        $handoverId = $this->insertRow('handover_get', ['waybill_id' => $id, 'status' => 0]);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/confirm-delivery', ['barcode' => $this->barcodeOf($id)]);

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['data' => ['id' => $id, 'status' => 11]]);
        $I->assertEquals(11, $this->dbValue('waybill', 'status', $id));
        $I->assertSame(1, $this->historyCount($id, 'pincode'));
        $I->assertEquals(1, $this->dbValue('handover_get', 'status', $handoverId));
    }

    public function confirmDeliveryRejectsOtherClientsWaybills(ApiTester $I): void
    {
        [$clientId, $otherClientId] = $this->clientIds();
        $this->legalClient($I, $clientId);
        $id = $this->waybill(['from_client_id' => $otherClientId, 'status' => 3]);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/confirm-delivery', ['barcode' => $this->barcodeOf($id)]);

        $I->seeResponseCodeIs(404);
        $I->assertEquals(3, $this->dbValue('waybill', 'status', $id));
    }

    public function confirmDeliveryRequiresABarcode(ApiTester $I): void
    {
        [$clientId] = $this->clientIds();
        $this->legalClient($I, $clientId);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/confirm-delivery', []);

        $I->seeResponseCodeIs(400);
    }

    // ================================================================ handover-status

    public function handoverStatusMovesAWaybillToStatus57(ApiTester $I): void
    {
        [$clientId] = $this->clientIds();
        $this->legalClient($I, $clientId);
        $id = $this->waybill(['from_client_id' => $clientId, 'status' => 3]);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/handover-status?wid=' . $id, []);

        $I->seeResponseCodeIs(200);
        $I->assertEquals($id, $this->payload($I)['status'] ?? null);
        $I->assertEquals(57, $this->dbValue('waybill', 'status', $id));
    }

    /**
     * SECURITY: there is no ownership check - any logged-in user can set ANY waybill to status 57
     * just by knowing its numeric id.
     */
    #[Group('security-bug')]
    public function handoverStatusCannotChangeAnotherClientsWaybill(ApiTester $I): void
    {
        [$clientId, $otherClientId] = $this->clientIds();
        $this->legalClient($I, $clientId);
        $id = $this->waybill(['from_client_id' => $otherClientId, 'status' => 3]);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/handover-status?wid=' . $id, []);

        $I->assertEquals(3, $this->dbValue('waybill', 'status', $id), 'someone else\'s waybill must not change');
    }

    #[Group('php8')]
    public function handoverStatusWithoutAWaybillIdDoesNotCrash(ApiTester $I): void
    {
        [$clientId] = $this->clientIds();
        $this->legalClient($I, $clientId);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/handover-status', []);

        $I->dontSeeResponseCodeIs(500); // $post['wid'] is read without isset()
    }

    // ================================================================ calculate

    public function calculateReportsMissingFields(ApiTester $I): void
    {
        $this->courier($I);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/calculate', []);

        $I->seeResponseCodeIs(200);
        $errors = (array) $this->payload($I);
        foreach (['to_city_id', 'to_fullname', 'to_phone_number', 'weight'] as $field) {
            $I->assertArrayHasKey($field, $errors, "a validation error for `$field` is expected");
        }
    }

    public function calculateWithRealCitiesDoesNotFail(ApiTester $I): void
    {
        $this->courier($I);
        $cities = Yii::$app->db->createCommand('SELECT id FROM city ORDER BY id LIMIT 2')->queryColumn();
        if (count($cities) < 2) {
            throw new \RuntimeException('The test database needs at least two rows in `city`.');
        }

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/calculate', [
            'from_city_id' => (int) $cities[0],
            'to_city_id' => (int) $cities[1],
            'from_fullname' => 'Sender',
            'from_phone_number' => '998901112233',
            'to_fullname' => 'Receiver',
            'to_phone_number' => '998901112244',
            'weight' => 1,
            'quantity' => 1,
        ]);

        // Either prices or a list of validation errors - but never a server error.
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
    }
}
