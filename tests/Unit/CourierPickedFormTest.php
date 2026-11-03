<?php

namespace Tests\Unit;

use app\models\CourierPickedForm;
use app\models\User;
use app\models\Waybill;
use Codeception\Test\Unit;
use Tests\Support\CreatesUsers;
use Tests\Support\CreatesRecords;
use Tests\Support\UnitTester;
use Tests\Support\WaybillFixtures;
use Yii;

class CourierPickedFormTest extends Unit
{
    use CreatesUsers;
    use CreatesRecords;
    use WaybillFixtures;

    protected UnitTester $tester;

    /** Waybill with sensible defaults, inserted with a plain INSERT (see CreatesRecords / WaybillFixtures). */
    private function buildWaybill(array $overrides = []): int
    {
        return $this->waybill(array_merge([
            'status'            => 1,
            'from_client_id'    => $this->clientIds()[0],
            'from_fullname'     => 'Sender',
            'from_phone_number' => '998 90 123 45 67',
            'cod_taken'         => 0,
            'for_sign'          => 1,
        ], $overrides));
    }

    /** Logs in a legal-client user (client_id set) and returns the user id. */
    private function login(array $overrides = []): int
    {
        $id = $this->createUser($this->tester, array_merge(['client_id' => $this->clientIds()[0]], $overrides));
        Yii::$app->user->setIdentity(User::findOne($id));
        return $id;
    }

    private function load(int $id): Waybill
    {
        return Waybill::findOne($id);
    }

    // ---- rules ----------------------------------------------------------

    public function testBarcodeIsRequired(): void
    {
        $form = new CourierPickedForm();
        $this->assertFalse($form->validate());
        $this->assertTrue($form->hasErrors('barcode'));
    }

    public function testTimestampMustBeATenDigitInteger(): void
    {
        foreach ([999999999, 10000000000, 'abc', 12.5] as $bad) {
            $form = new CourierPickedForm(['barcode' => 'X', 'timestamp' => $bad]);
            $this->assertFalse($form->validate(), 'timestamp ' . var_export($bad, true) . ' must be rejected');
            $this->assertTrue($form->hasErrors('timestamp'));
        }
        foreach ([1700000000, '1700000000', null] as $good) {
            $form = new CourierPickedForm(['barcode' => 'X', 'timestamp' => $good]);
            $this->assertTrue($form->validate(), 'timestamp ' . var_export($good, true) . ' must be accepted');
        }
    }

    public function testCourierMustExistWhenGiven(): void
    {
        $courier = $this->login();

        $form = new CourierPickedForm(['barcode' => 'X', 'courier_id' => 999999999]);
        $this->assertFalse($form->validate());
        $this->assertTrue($form->hasErrors('courier_id'));

        $form = new CourierPickedForm(['barcode' => 'X', 'courier_id' => $courier]);
        $this->assertTrue($form->validate());

        $form = new CourierPickedForm(['barcode' => 'X', 'courier_id' => null]);
        $this->assertTrue($form->validate(), 'courier_id is optional');
    }

    // ---- changeStatus ---------------------------------------------------

    public function testUserWithoutClientIsRefused(): void
    {
        $this->login(['client_id' => 0]);
        $id = $this->buildWaybill();
        $form = new CourierPickedForm(['barcode' => $this->load($id)->barcode]);

        $this->assertFalse($form->changeStatus());
        $this->assertSame('This API for legal client', $form->error_message);
        $this->assertEquals(1, $this->load($id)->status);
    }

    public function testUnknownBarcodeIsReported(): void
    {
        $this->login();
        $form = new CourierPickedForm(['barcode' => 'NOPE000000']);
        $this->assertFalse($form->changeStatus());
        $this->assertSame('Barcode not found.', $form->error_message);
    }

    public function testMovesWaybillToStatus3AndStoresTimestamp(): void
    {
        $this->login();
        $id = $this->buildWaybill(['status' => 1]);
        $form = new CourierPickedForm(['barcode' => $this->load($id)->barcode, 'timestamp' => 1700000000]);

        $this->assertTrue($form->changeStatus());
        $this->assertEquals(3, $this->load($id)->status);
        $this->assertEquals(1700000000, $this->load($id)->pickup_datetime);
    }

    public function testTimestampDefaultsToNow(): void
    {
        $this->login();
        $id = $this->buildWaybill();
        $before = time();
        $form = new CourierPickedForm(['barcode' => $this->load($id)->barcode]);

        $this->assertTrue($form->changeStatus());
        $stamp = (int) $this->load($id)->pickup_datetime;
        $this->assertGreaterThanOrEqual($before, $stamp);
        $this->assertLessThanOrEqual(time(), $stamp);
    }

    public function testCourierIsAssignedOnlyWhenGiven(): void
    {
        $courier = $this->login();

        $a = $this->buildWaybill();
        $initial = $this->load($a)->pickup_courier_id;
        (new CourierPickedForm(['barcode' => $this->load($a)->barcode]))->changeStatus();
        $this->assertEquals($initial, $this->load($a)->pickup_courier_id);

        $b = $this->buildWaybill();
        (new CourierPickedForm(['barcode' => $this->load($b)->barcode, 'courier_id' => $courier]))->changeStatus();
        $this->assertEquals($courier, $this->load($b)->pickup_courier_id);
    }

    public function testSeveralBarcodesSeparatedByCommas(): void
    {
        $this->login();
        $a = $this->buildWaybill();
        $b = $this->buildWaybill();
        $form = new CourierPickedForm(['barcode' => $this->load($a)->barcode . ',' . $this->load($b)->barcode]);

        $this->assertTrue($form->changeStatus());
        $this->assertEquals(3, $this->load($a)->status);
        $this->assertEquals(3, $this->load($b)->status);
    }

    public function testOtherClientsWaybillsAreNotTouched(): void
    {
        $this->login();
        $foreign = $this->buildWaybill(['from_client_id' => $this->clientIds()[1], 'status' => 1]);
        $form = new CourierPickedForm(['barcode' => $this->load($foreign)->barcode]);

        $this->assertFalse($form->changeStatus());
        $this->assertSame('Barcode not found.', $form->error_message);
        $this->assertEquals(1, $this->load($foreign)->status);
    }

    // ---- documented gaps (flip these when the behaviour is fixed) -------

    /** @group known-gap */
    public function testUnknownBarcodeInAListIsSilentlyIgnored(): void
    {
        $this->login();
        $a = $this->buildWaybill();
        $form = new CourierPickedForm(['barcode' => $this->load($a)->barcode . ',NOPE000000']);

        $this->assertTrue($form->changeStatus(), 'returns true as soon as ONE barcode matches');
        $this->assertSame('', $form->error_message);
        $this->assertEquals(3, $this->load($a)->status);
    }

    /**
     * strpos() returns 0 for a leading comma, which is falsy, so ",CODE" is looked up as one literal barcode.
     * @group known-gap
     */
    public function testLeadingCommaDisablesListParsing(): void
    {
        $this->login();
        $a = $this->buildWaybill();
        $form = new CourierPickedForm(['barcode' => ',' . $this->load($a)->barcode]);

        $this->assertFalse($form->changeStatus());
        $this->assertEquals(1, $this->load($a)->status);
    }

    /**
     * There is no status guard: a delivered waybill can be pulled back to "picked up" by its legal client.
     * @group known-gap
     */
    public function testDeliveredWaybillIsMovedBackToStatus3(): void
    {
        $this->login();
        $a = $this->buildWaybill(['status' => 11]);
        $form = new CourierPickedForm(['barcode' => $this->load($a)->barcode]);

        $this->assertTrue($form->changeStatus());
        $this->assertEquals(3, $this->load($a)->status);
    }
}