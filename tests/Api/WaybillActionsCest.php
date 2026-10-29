<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Attribute\Group;
use Codeception\Scenario;
use Tests\Support\ApiTester;
use Tests\Support\CreatesRecords;
use Tests\Support\CreatesUsers;
use Tests\Support\WaybillFixtures;
use Yii;

/**
 * Functional (HTTP-level) tests for app\controllers\WaybillController - second part.
 *
 *   POST /waybill-new                 courier creates a waybill
 *   POST /new-parcel                  legal client creates a waybill through the API
 *   POST /picked, /delivered          courier signature flow (app\models\Sign)
 *   POST /courier-picked              legal client marks parcels as picked up
 *   POST /accept, /waybill-accept-delivery   courier takes waybills
 *   POST /waybill-sign-photo          legal client downloads a delivery photo
 *
 * BEFORE YOU RUN THEM - picked / delivered write signature files below params['sign_dir'].
 * config/params_local.php points that to a Windows folder ("d:/www/birun/..."), which on Linux would create
 * a folder named "d:" inside the project. Make the TEST config use a temp folder, in config/test.php:
 *
 *     'params' => array_merge($params, ['sign_dir' => sys_get_temp_dir() . '/aktiv-test-signs/']),
 *
 * (the tests that upload files are skipped with a message until you do).
 *
 * Responses are wrapped: {"status":"OK","code":200,"message":"Success","data": ...}.
 * Groups: security-bug (missing permission / ownership checks), known-gap, php8.
 *
 * Run:  vendor/bin/codecept run Api WaybillActionsCest
 */
class WaybillActionsCest
{
    use CreatesUsers;
    use CreatesRecords;
    use WaybillFixtures;

    // a valid 1x1 PNG
    private const PNG_BASE64 =
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function _before(ApiTester $I): void
    {
        Yii::$app->security->passwordHashCost = 4;
    }

    // ================================================================ helpers

    private function requireTempSignDir(Scenario $scenario): void
    {
        $dir = (string) (Yii::$app->params['sign_dir'] ?? '');
        if ($dir === '' || !str_starts_with($dir, sys_get_temp_dir())) {
            $scenario->skip("params['sign_dir'] must point to a temp folder in config/test.php (see the header of WaybillActionsCest).");
        }
    }

    private function pngFile(): string
    {
        $path = sys_get_temp_dir() . '/aktiv-test-sign.png';
        file_put_contents($path, base64_decode(self::PNG_BASE64));

        return $path;
    }

    private function notAnImageFile(): string
    {
        $path = sys_get_temp_dir() . '/aktiv-test-not-an-image.png';
        file_put_contents($path, 'this is plain text, not a PNG');

        return $path;
    }

    private function newWaybillPayload(array $overrides = []): array
    {
        [$fromCity, $toCity] = $this->twoCities();

        return array_merge([
            'from_city_id' => $fromCity,
            'to_city_id' => $toCity,
            'from_fullname' => 'Test Sender',
            'from_phone_number' => '998901234500',
            'to_fullname' => 'Test Receiver',
            'to_phone_number' => '998901234501',
            'to_address' => 'Test street 1',
            'weight' => 1,
            'quantity' => 1,
            'parcel_type' => 3,
            'is_delivery' => 1,
            'is_cod' => 0,
        ], $overrides);
    }

    // ================================================================ waybill-new (courier)

    public function newCreatesAWaybillForACourier(ApiTester $I): void
    {
        $courierId = $this->courier($I);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/waybill-new', $this->newWaybillPayload());

        $I->seeResponseCodeIs(200);
        $data = (array) $this->payload($I);
        $I->assertArrayHasKey('id', $data, 'the created waybill is returned: ' . json_encode($data));
        $id = (int) $data['id'];

        $I->assertEquals(2, $this->dbValue('waybill', 'status', $id));
        $I->assertEquals(1, $this->dbValue('waybill', 'shipper_type', $id));      // individual
        $I->assertEquals($courierId, $this->dbValue('waybill', 'formalize_user_id', $id));
        $I->assertGreaterThanOrEqual(12, strlen($this->barcodeOf($id)), 'a barcode is generated');
        $I->assertSame(1, $this->historyCount($id, 'add new parcel by Courier app'));
    }

    public function newReportsValidationErrorsAndCreatesNothing(ApiTester $I): void
    {
        $this->courier($I);
        $before = $this->countWaybills();

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/waybill-new', []);

        $errors = (array) $this->payload($I);
        foreach (['to_city_id', 'from_fullname', 'to_fullname', 'to_phone_number'] as $field) {
            $I->assertArrayHasKey($field, $errors, "a validation error for `$field` is expected");
        }
        $I->assertSame($before, $this->countWaybills());
    }

    // ================================================================ new-parcel (legal client API)

    public function newParcelCreatesAWaybillForALegalClient(ApiTester $I): void
    {
        [$clientId] = $this->clientIds();
        $userId = $this->legalClient($I, $clientId);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/new-parcel', $this->newWaybillPayload());

        $I->seeResponseCodeIs(200);
        $data = (array) $this->payload($I);
        $I->assertArrayHasKey('id', $data, 'the created parcel is returned: ' . json_encode($data));
        $id = (int) $data['id'];

        $I->assertGreaterThanOrEqual(12, strlen((string) ($data['barcode'] ?? '')));
        $I->assertEquals($clientId, $this->dbValue('waybill', 'from_client_id', $id));
        $I->assertEquals(3, $this->dbValue('waybill', 'shipper_type', $id));       // online store
        $I->assertEquals(2, $this->dbValue('waybill', 'status', $id));
        $I->assertSame(1, $this->historyCount($id, 'add new parcel by API'));
        $I->assertNotNull($this->dbValue('waybill', 'total_price', $id), 'a price is calculated');
        $I->assertNotEmpty($userId);
    }

    public function newParcelDefaultsTheWeightTo05(ApiTester $I): void
    {
        [$clientId] = $this->clientIds();
        $this->legalClient($I, $clientId);
        $payload = $this->newWaybillPayload();
        unset($payload['weight']);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/new-parcel', $payload);

        $data = (array) $this->payload($I);
        $I->assertArrayHasKey('id', $data, json_encode($data));
        $I->assertEquals(0.5, $data['weight'] ?? null);
    }

    public function newParcelRejectsAnIdenticalSecondRequest(ApiTester $I): void
    {
        [$clientId] = $this->clientIds();
        $this->legalClient($I, $clientId);
        $payload = $this->newWaybillPayload();

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/new-parcel', $payload);
        $I->seeResponseCodeIs(200);
        $after = $this->countWaybills();

        $I->sendPost('/new-parcel', $payload);

        $I->seeResponseCodeIs(409);
        $I->assertSame($after, $this->countWaybills(), 'no duplicate may be created');
    }

    public function newParcelAnswers422ForInvalidInput(ApiTester $I): void
    {
        [$clientId] = $this->clientIds();
        $this->legalClient($I, $clientId);
        $before = $this->countWaybills();

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/new-parcel', ['to_fullname' => 'Only a name']);

        $I->seeResponseCodeIs(422);
        $errors = (array) $this->payload($I);
        foreach (['to_city_id', 'from_phone_number', 'to_phone_number'] as $field) {
            $I->assertArrayHasKey($field, $errors, "a validation error for `$field` is expected");
        }
        $I->assertSame($before, $this->countWaybills());
    }

    // ================================================================ picked / delivered (Sign)

    public function pickedMovesANewWaybillToStatus3(ApiTester $I, Scenario $scenario): void
    {
        $this->requireTempSignDir($scenario);
        $courierId = $this->courier($I);
        $id = $this->waybill(['status' => 2]);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/picked', ['wids' => (string) $id, 'full_name' => 'Sender Name'], ['sign_file' => $this->pngFile()]);

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['data' => ['status' => 'ok']]);
        $I->assertEquals(3, $this->dbValue('waybill', 'status', $id));
        $I->assertEquals(2, $this->dbValue('waybill', 'old_status', $id));
        $I->assertSame(1, $this->historyCount($id, 'picked up'));
        $I->assertNotEmpty($courierId);
    }

    public function pickedByBarcodeAssignsThePickupCourier(ApiTester $I, Scenario $scenario): void
    {
        $this->requireTempSignDir($scenario);
        $courierId = $this->courier($I);
        $id = $this->waybill(['status' => 2]);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/picked', ['barcodes' => $this->barcodeOf($id), 'full_name' => 'Sender Name'], ['sign_file' => $this->pngFile()]);

        $I->seeResponseContainsJson(['data' => ['status' => 'ok']]);
        $I->assertEquals(3, $this->dbValue('waybill', 'status', $id));
        $I->assertEquals($courierId, $this->dbValue('waybill', 'pickup_courier_id', $id));
    }

    public function pickedReportsAnUnknownWaybillId(ApiTester $I): void
    {
        $this->courier($I);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/picked', ['waybill_id' => 2147483000]);

        $I->assertArrayHasKey('waybill_id', (array) $this->payload($I));
    }

    public function deliveredMovesTheWaybillToStatus11AndQueuesAnSms(ApiTester $I, Scenario $scenario): void
    {
        $this->requireTempSignDir($scenario);
        $this->courier($I);
        $id = $this->waybill(['status' => 10, 'from_phone_number' => '998901234599']);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/delivered', ['wids' => (string) $id, 'full_name' => 'Receiver Name'], ['sign_file' => $this->pngFile()]);

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['data' => ['status' => 'ok']]);
        $I->assertEquals(11, $this->dbValue('waybill', 'status', $id));
        $I->assertEquals(10, $this->dbValue('waybill', 'old_status', $id));
        $I->assertSame(1, $this->historyCount($id, 'delivered'));

        // The sender gets an SMS. Checking phone / waybillid / text also catches wrongly ordered send() arguments.
        $sms = $this->smsFor('998901234599');
        $I->assertNotNull($sms, 'an SMS to the sender is expected');
        $I->assertEquals($id, $sms['waybillid']);
        $I->assertStringContainsString($this->barcodeOf($id), (string) $sms['text']);
    }

    public function deliveredByBarcodeWorksToo(ApiTester $I, Scenario $scenario): void
    {
        $this->requireTempSignDir($scenario);
        $this->courier($I);
        $id = $this->waybill(['status' => 10, 'from_phone_number' => '998901234598']);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/delivered', ['barcodes' => $this->barcodeOf($id), 'full_name' => 'Receiver Name'], ['sign_file' => $this->pngFile()]);

        $I->seeResponseContainsJson(['data' => ['status' => 'ok']]);
        $I->assertEquals(11, $this->dbValue('waybill', 'status', $id));
    }

    public function deliveredWithoutASignatureImageChangesNothing(ApiTester $I): void
    {
        $this->courier($I);
        $id = $this->waybill(['status' => 10]);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/delivered', ['wids' => (string) $id]);

        // documents today's behaviour: the call says "ok", but all the work happens only when a sign_file is sent
        $I->seeResponseContainsJson(['data' => ['status' => 'ok']]);
        $I->assertEquals(10, $this->dbValue('waybill', 'status', $id));
    }

    public function deliveredRejectsAFileThatIsNotAPng(ApiTester $I): void
    {
        $this->courier($I);
        $id = $this->waybill(['status' => 10]);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/delivered', ['wids' => (string) $id], ['sign_file' => $this->notAnImageFile()]);

        $I->seeResponseCodeIs(400);
        $I->assertStringContainsString('sign_file', (string) ($this->json($I)['message'] ?? ''));
        $I->assertEquals(10, $this->dbValue('waybill', 'status', $id));
    }

    /**
     * SECURITY: picked / delivered never check who calls them. A legal client (not a courier) can mark
     * ANY waybill as delivered by id, which also sends the "delivered" SMS to the sender.
     */
    #[Group('security-bug')]
    public function aNonCourierCannotMarkAWaybillAsDelivered(ApiTester $I, Scenario $scenario): void
    {
        $this->requireTempSignDir($scenario);
        [$clientId, $otherClientId] = $this->clientIds();
        $this->legalClient($I, $clientId);
        $id = $this->waybill(['from_client_id' => $otherClientId, 'status' => 10]);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/delivered', ['wids' => (string) $id], ['sign_file' => $this->pngFile()]);

        $I->assertEquals(10, $this->dbValue('waybill', 'status', $id), 'someone else\'s waybill must not change');
    }

    // ================================================================ waybill-accept-delivery

    public function acceptDeliveryMovesAStatus2WaybillToStatus3(ApiTester $I): void
    {
        $courierId = $this->courier($I);
        $id = $this->waybill(['status' => 2]);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/waybill-accept-delivery', ['barcode' => $this->barcodeOf($id)]);

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['data' => ['barcode' => $this->barcodeOf($id), 'status' => 3, 'old_status' => 2]]);
        $I->assertEquals(3, $this->dbValue('waybill', 'status', $id));
        $I->assertEquals($courierId, $this->dbValue('waybill', 'pickup_courier_id', $id));
        $I->assertSame(1, $this->historyCount($id, 'accept waybill himself'));
    }

    public function acceptDeliveryMovesAStatus3WaybillToDeliveryStatus10(ApiTester $I): void
    {
        $courierId = $this->courier($I);
        $id = $this->waybill(['status' => 3]);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/waybill-accept-delivery', ['barcode' => $this->barcodeOf($id)]);

        $I->seeResponseContainsJson(['data' => ['status' => 10, 'old_status' => 3]]);
        $I->assertEquals($courierId, $this->dbValue('waybill', 'delivery_courier_id', $id));
    }

    public function acceptDeliveryLeavesDeliveredWaybillsAlone(ApiTester $I): void
    {
        $this->courier($I);
        $id = $this->waybill(['status' => 11]);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/waybill-accept-delivery', ['barcode' => $this->barcodeOf($id)]);

        $I->assertEquals(11, $this->dbValue('waybill', 'status', $id));
    }

    public function acceptDeliveryRejectsAnUnknownBarcode(ApiTester $I): void
    {
        $this->courier($I);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/waybill-accept-delivery', ['barcode' => '000000000000']);

        $I->assertSame('Barcode not found.', $this->payload($I));
    }

    public function acceptDeliveryRequiresABarcode(ApiTester $I): void
    {
        $this->courier($I);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/waybill-accept-delivery', []);

        $I->assertSame('Barcode is requied.', $this->payload($I));
    }

    /** SECURITY: like waybill-comment, accepting a waybill is open to every logged-in user. */
    #[Group('security-bug')]
    public function aNonCourierCannotAcceptAWaybill(ApiTester $I): void
    {
        [$clientId, $otherClientId] = $this->clientIds();
        $this->legalClient($I, $clientId);
        $id = $this->waybill(['from_client_id' => $otherClientId, 'status' => 3]);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/waybill-accept-delivery', ['barcode' => $this->barcodeOf($id)]);

        $I->assertEquals(3, $this->dbValue('waybill', 'status', $id), 'someone else\'s waybill must not change');
    }

    // ================================================================ accept

    public function acceptAssignsTheDeliveryCourierByWaybillId(ApiTester $I): void
    {
        $courierId = $this->courier($I);
        $id = $this->waybill(['status' => 9, 'is_delivery' => 1]);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/accept', ['wids' => (string) $id]);

        $I->seeResponseContainsJson(['data' => ['status' => 'ok']]);
        $I->assertEquals($courierId, $this->dbValue('waybill', 'delivery_courier_id', $id));
    }

    public function acceptAssignsThePickupCourierByBarcode(ApiTester $I): void
    {
        $courierId = $this->courier($I);
        $id = $this->waybill(['status' => 2, 'is_pickedup' => 1]);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/accept', ['barcodes' => $this->barcodeOf($id)]);

        $I->seeResponseContainsJson(['data' => ['status' => 'ok']]);
        $I->assertEquals($courierId, $this->dbValue('waybill', 'pickup_courier_id', $id));
    }

    public function acceptDoesNotReassignDeliveredWaybills(ApiTester $I): void
    {
        $this->courier($I);
        $id = $this->waybill(['status' => 11, 'is_delivery' => 1]);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/accept', ['barcodes' => $this->barcodeOf($id)]);

        $I->assertEmpty($this->dbValue('waybill', 'delivery_courier_id', $id));
    }

    public function acceptRejectsNonTextInput(ApiTester $I): void
    {
        $this->courier($I);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/accept', ['wids' => ['1', '2']]);

        $I->seeResponseContainsJson(['data' => ['status' => 'error']]);
    }

    // ================================================================ courier-picked (legal client API)

    public function courierPickedMarksOwnParcelsAsPickedUp(ApiTester $I): void
    {
        [$clientId] = $this->clientIds();
        $this->legalClient($I, $clientId);
        $courierId = $this->courier($I, self::COURIER_TOKEN, '998901110001');
        $id = $this->waybill(['from_client_id' => $clientId, 'status' => 2]);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/courier-picked', [
            'barcode' => $this->barcodeOf($id),
            'timestamp' => 1700000000,
            'courier_id' => $courierId,
        ]);

        $I->seeResponseContainsJson(['data' => ['status' => 'ok']]);
        $I->assertEquals(3, $this->dbValue('waybill', 'status', $id));
        $I->assertEquals(1700000000, $this->dbValue('waybill', 'pickup_datetime', $id));
        $I->assertEquals($courierId, $this->dbValue('waybill', 'pickup_courier_id', $id));
    }

    public function courierPickedAcceptsSeveralBarcodes(ApiTester $I): void
    {
        [$clientId] = $this->clientIds();
        $this->legalClient($I, $clientId);
        $first = $this->waybill(['from_client_id' => $clientId, 'status' => 2]);
        $second = $this->waybill(['from_client_id' => $clientId, 'status' => 2]);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/courier-picked', ['barcode' => $this->barcodeOf($first) . ',' . $this->barcodeOf($second)]);

        $I->seeResponseContainsJson(['data' => ['status' => 'ok']]);
        $I->assertEquals(3, $this->dbValue('waybill', 'status', $first));
        $I->assertEquals(3, $this->dbValue('waybill', 'status', $second));
    }

    public function courierPickedAnswersErrorForAnUnknownBarcode(ApiTester $I): void
    {
        [$clientId] = $this->clientIds();
        $this->legalClient($I, $clientId);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/courier-picked', ['barcode' => '000000000000']);

        $I->seeResponseContainsJson(['data' => ['status' => 'error']]);
    }

    public function courierPickedValidatesItsInput(ApiTester $I): void
    {
        [$clientId] = $this->clientIds();
        $this->legalClient($I, $clientId);
        $I->amBearerAuthenticated(self::CLIENT_TOKEN);

        $I->sendPost('/courier-picked', []);
        $I->assertArrayHasKey('barcode', (array) $this->payload($I));

        $I->sendPost('/courier-picked', ['barcode' => '123456789012', 'timestamp' => 5]);
        $I->assertArrayHasKey('timestamp', (array) $this->payload($I));

        $I->sendPost('/courier-picked', ['barcode' => '123456789012', 'courier_id' => 2147483000]);
        $I->assertArrayHasKey('courier_id', (array) $this->payload($I));
    }

    /**
     * SECURITY: the "is this yours?" check only needs ONE barcode of the list to belong to the client,
     * but the UPDATE then changes EVERY barcode of the list.
     */
    #[Group('security-bug')]
    public function courierPickedCannotTouchAnotherClientsParcels(ApiTester $I): void
    {
        [$clientId, $otherClientId] = $this->clientIds();
        $this->legalClient($I, $clientId);
        $mine = $this->waybill(['from_client_id' => $clientId, 'status' => 2]);
        $theirs = $this->waybill(['from_client_id' => $otherClientId, 'status' => 2]);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/courier-picked', ['barcode' => $this->barcodeOf($mine) . ',' . $this->barcodeOf($theirs)]);

        $I->assertEquals(2, $this->dbValue('waybill', 'status', $theirs), 'someone else\'s parcel must not change');
    }

    /**
     * SECURITY: a user without a client (a courier) searches `from_client_id IS NULL`, which matches
     * every private (non-company) waybill - so couriers can change those by barcode.
     */
    #[Group('security-bug')]
    public function courierPickedIsOnlyForLegalClients(ApiTester $I): void
    {
        $this->courier($I);
        $id = $this->waybill(['status' => 2]); // from_client_id stays NULL

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/courier-picked', ['barcode' => $this->barcodeOf($id)]);

        $I->assertEquals(2, $this->dbValue('waybill', 'status', $id));
    }

    // ================================================================ waybill-sign-photo

    public function signPhotoRequiresABarcode(ApiTester $I): void
    {
        [$clientId] = $this->clientIds();
        $this->legalClient($I, $clientId);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/waybill-sign-photo', []);

        $I->seeResponseCodeIs(400);
    }

    public function signPhotoIsOnlyForLegalClients(ApiTester $I): void
    {
        $this->courier($I);

        $I->amBearerAuthenticated(self::COURIER_TOKEN);
        $I->sendPost('/waybill-sign-photo', ['barcode' => '123456789012']);

        $I->seeResponseCodeIs(400);
    }

    public function signPhotoHidesOtherClientsWaybills(ApiTester $I): void
    {
        [$clientId, $otherClientId] = $this->clientIds();
        $this->legalClient($I, $clientId);
        $id = $this->waybill(['from_client_id' => $otherClientId]);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/waybill-sign-photo', ['barcode' => $this->barcodeOf($id)]);

        $I->seeResponseCodeIs(404);
    }

    public function signPhotoAnswers404WhenThereIsNoPhoto(ApiTester $I): void
    {
        [$clientId] = $this->clientIds();
        $this->legalClient($I, $clientId);
        $id = $this->waybill(['from_client_id' => $clientId]);

        $I->amBearerAuthenticated(self::CLIENT_TOKEN);
        $I->sendPost('/waybill-sign-photo', ['barcode' => $this->barcodeOf($id)]);

        $I->seeResponseCodeIs(404);
    }
}