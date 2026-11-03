<?php

namespace Tests\Api;

use Tests\Support\ApiTester;
use Tests\Support\CreatesRecords;
use Tests\Support\CreatesUsers;
use Tests\Support\WaybillFixtures;
use Yii;

/**
 * POST ready-notification -> NotificationController::actionReady
 *
 * The recipient routing itself (cities, regions, branches) is covered by Unit/NotificationFormTest.
 * Here we test the HTTP layer: authentication, validation, and what really gets queued in `sms`.
 */
class NotificationTest
{
    use CreatesUsers;
    use CreatesRecords;
    use WaybillFixtures;

    private const URL = 'ready-notification';

    private function auth(ApiTester $I, string $token): void
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $token);
    }

    /** A waybill from region 9, which always notifies two fixed recipients, in a real city. */
    private function region9Waybill(array $overrides = []): int
    {
        [$cityId] = $this->twoCities();

        return $this->waybill(array_merge([
            'status'         => 1,
            'from_client_id' => $this->clientIds()[0],
            'from_fullname'  => 'Sender',
            'from_city_id'   => $cityId,
            'from_region_id' => 9,
            'from_branch_id' => 0,
        ], $overrides));
    }

    private function smsRows(int $waybillId): array
    {
        return Yii::$app->db
            ->createCommand('SELECT phone, text FROM sms WHERE waybillid = :id ORDER BY id', [':id' => $waybillId])
            ->queryAll();
    }

    // ---- authentication and routing -------------------------------------

    public function requiresAToken(ApiTester $I): void
    {
        $I->sendPost(self::URL, ['barcode' => 'X']);
        $I->seeResponseCodeIs(401);
    }

    public function rejectsAnInvalidToken(ApiTester $I): void
    {
        $this->auth($I, 'not-a-real-token');
        $I->sendPost(self::URL, ['barcode' => 'X']);
        $I->seeResponseCodeIs(401);
    }

    public function isPostOnly(ApiTester $I): void
    {
        $this->courier($I);
        $this->auth($I, self::COURIER_TOKEN);
        $I->sendGet(self::URL);
        $I->seeResponseCodeIs(404);
    }

    // ---- validation -----------------------------------------------------

    public function rejectsANegativeOrderCountAndSendsNothing(ApiTester $I): void
    {
        $this->courier($I);
        $this->auth($I, self::COURIER_TOKEN);
        $id = $this->region9Waybill();

        $I->sendPost(self::URL, ['barcode' => $this->barcodeOf($id), 'order_count' => -1]);

        $I->seeResponseCodeIs(400);
        $I->assertSame([], $this->smsRows($id));
    }

    public function rejectsANonNumericOrderCount(ApiTester $I): void
    {
        $this->courier($I);
        $this->auth($I, self::COURIER_TOKEN);
        $id = $this->region9Waybill();

        $I->sendPost(self::URL, ['barcode' => $this->barcodeOf($id), 'order_count' => 'many']);

        $I->seeResponseCodeIs(400);
        $I->assertSame([], $this->smsRows($id));
    }

    // ---- sending --------------------------------------------------------

    public function queuesTheNotificationForAKnownBarcode(ApiTester $I): void
    {
        $this->courier($I);
        $this->auth($I, self::COURIER_TOKEN);
        $id = $this->region9Waybill();
        $barcode = $this->barcodeOf($id);

        $I->sendPost(self::URL, ['barcode' => $barcode, 'order_count' => 3]);

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['message' => 'Notification saved.']);

        $rows = $this->smsRows($id);
        $phones = array_column($rows, 'phone');
        $I->assertContains('998910228484', $phones);
        $I->assertContains('998918018686', $phones);
        foreach ($rows as $row) {
            $I->assertStringContainsString($barcode, $row['text']);
        }
    }

    /**
     * php8: $waybill is undefined in NotificationForm when no barcode is given.
     * Red until `$waybill = null;` is added at the top of saveNotification().
     * @group php8
     */
    public function acceptsARequestWithoutABarcodeAndSendsNothing(ApiTester $I): void
    {
        $this->courier($I);
        $this->auth($I, self::COURIER_TOKEN);
        $before = (int) Yii::$app->db->createCommand('SELECT COUNT(*) FROM sms')->queryScalar();

        $I->sendPost(self::URL, ['order_count' => 2]);

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['message' => 'Notification saved.']);
        $I->assertSame($before, (int) Yii::$app->db->createCommand('SELECT COUNT(*) FROM sms')->queryScalar());
    }

    /**
     * An unknown barcode still answers "Notification saved." although nothing was sent.
     * @group known-gap
     */
    public function anUnknownBarcodeIsReportedAsSaved(ApiTester $I): void
    {
        $this->courier($I);
        $this->auth($I, self::COURIER_TOKEN);
        $before = (int) Yii::$app->db->createCommand('SELECT COUNT(*) FROM sms')->queryScalar();

        $I->sendPost(self::URL, ['barcode' => 'NOPE000000']);

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['message' => 'Notification saved.']);
        $I->assertSame($before, (int) Yii::$app->db->createCommand('SELECT COUNT(*) FROM sms')->queryScalar());
    }

    // ---- who may call it (ask the manager what is intended) -------------

    /**
     * actionReady never calls the controller's user() helper, so there is no role check.
     * @group known-gap
     */
    public function anyRoleCanTriggerNotifications(ApiTester $I): void
    {
        $this->legalClient($I, $this->clientIds()[0]);
        $this->auth($I, self::CLIENT_TOKEN);
        $id = $this->region9Waybill();

        $I->sendPost(self::URL, ['barcode' => $this->barcodeOf($id)]);

        $I->seeResponseCodeIs(200);
        $I->assertNotEmpty($this->smsRows($id));
    }

    /**
     * The waybill does not have to belong to the caller's client.
     * @group known-gap
     */
    public function aLegalClientCanNotifyForAnotherClientsWaybill(ApiTester $I): void
    {
        $this->legalClient($I, $this->clientIds()[0]);
        $this->auth($I, self::CLIENT_TOKEN);
        $id = $this->region9Waybill(['from_client_id' => $this->clientIds()[1]]);

        $I->sendPost(self::URL, ['barcode' => $this->barcodeOf($id)]);

        $I->seeResponseCodeIs(200);
        $I->assertNotEmpty($this->smsRows($id));
    }
}