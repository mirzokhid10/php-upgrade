<?php

namespace Tests\Unit;

use app\models\AcceptDeliveryForm;
use app\models\HandoverGet;
use app\models\History;
use app\models\User;
use app\models\Waybill;
use Codeception\Test\Unit;
use Tests\Support\CreatesUsers;
use Tests\Support\CreatesRecords;
use Tests\Support\UnitTester;
use Tests\Support\WaybillFixtures;
use Yii;

class AcceptDeliveryFormTest extends Unit
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

    private int $me;
    private int $other;

    protected function _before()
    {
        $this->me = $this->createUser($this->tester, ['fullname' => 'Test Courier']);
        $this->other = $this->createUser($this->tester, [
            'username' => self::OTHER_PHONE,
            'access_token' => self::OTHER_TOKEN,
            'fullname' => 'Other Courier',
            'email' => 'other-courier@test.local',
        ]);
        Yii::$app->user->setIdentity(User::findOne($this->me));
    }

    private function load(int $id): Waybill
    {
        return Waybill::findOne($id);
    }

    private function accept(int $waybillId): AcceptDeliveryForm
    {
        $form = new AcceptDeliveryForm(['barcode' => $this->load($waybillId)->barcode]);
        $form->accept();
        return $form;
    }

    // ---- rules ----------------------------------------------------------

    public function testBarcodeIsRequired(): void
    {
        $form = new AcceptDeliveryForm();
        $this->assertFalse($form->accept());
        $this->assertSame('Barcode is requied.', $form->errorMsg); // sic
    }

    public function testUnknownBarcodeIsRejected(): void
    {
        $form = new AcceptDeliveryForm(['barcode' => 'NOPE000000']);
        $this->assertFalse($form->accept());
        $this->assertSame('Barcode not found.', $form->errorMsg);
    }

    // ---- transitions ----------------------------------------------------

    public function testStatusTransitions(): void
    {
        // [start status, expected status, expected old_status]
        $cases = [
            [1, 3, 1], [2, 3, 2],
            [3, 10, 3], [4, 10, 4], [6, 10, 6], [15, 10, 15],
            [10, 10, 10],        // taken over from another courier
            [11, 11, null],      // delivered: untouched
            [5, 5, null],        // not a listed status: untouched
        ];
        foreach ($cases as [$start, $expected, $oldStatus]) {
            $id = $this->buildWaybill(['status' => $start, 'old_status' => 0, 'delivery_courier_id' => $this->other]);
            $form = $this->accept($id);
            $wb = $this->load($id);

            $this->assertEquals($expected, $wb->status, "status $start");
            if ($oldStatus !== null) {
                $this->assertEquals($oldStatus, $wb->old_status, "old_status for $start");
            }
            $this->assertEquals($expected, $form->waybill->status, "form->waybill is refreshed for $start");
        }
    }

    public function testPickupStatusesAssignThePickupCourier(): void
    {
        foreach ([1, 2] as $start) {
            $id = $this->buildWaybill(['status' => $start]);
            $this->accept($id);
            $this->assertEquals($this->me, $this->load($id)->pickup_courier_id, "status $start");
        }
    }

    public function testDeliveryStatusesAssignTheDeliveryCourier(): void
    {
        foreach ([3, 4, 6, 15] as $start) {
            $id = $this->buildWaybill(['status' => $start, 'delivery_courier_id' => $this->other]);
            $this->accept($id);
            $this->assertEquals($this->me, $this->load($id)->delivery_courier_id, "status $start");
        }
    }

    public function testStatus10OfTheSameCourierIsLeftAlone(): void
    {
        $id = $this->buildWaybill(['status' => 10, 'old_status' => 3, 'delivery_courier_id' => $this->me]);
        $this->accept($id);
        $this->assertEquals(3, $this->load($id)->old_status);
    }

    public function testSpecialClientsGetAHandoverRowAtStatus2(): void
    {
        foreach ([504, 684] as $client) {
            $id = $this->buildWaybill(['status' => 2, 'from_client_id' => $client]);
            $this->accept($id);
            $this->assertEquals(1, HandoverGet::find()->where(['waybill_id' => $id])->count(), "client $client");
            $this->assertEquals($this->me, $this->load($id)->delivery_courier_id);
        }
    }

    public function testOrdinaryClientsGetNoHandoverRow(): void
    {
        $id = $this->buildWaybill(['status' => 2, 'from_client_id' => $this->clientIds()[0]]);
        $this->accept($id);
        $this->assertEquals(0, HandoverGet::find()->where(['waybill_id' => $id])->count());
    }

    public function testExistingHandoverRowIsNotDuplicated(): void
    {
        $id = $this->buildWaybill(['status' => 2, 'from_client_id' => 504]);
        $this->insertRow(HandoverGet::getTableSchema()->name, ['waybill_id' => $id, 'user_id' => $this->other, 'status' => 0]);
        $this->accept($id);
        $this->assertEquals(1, HandoverGet::find()->where(['waybill_id' => $id])->count());
    }

    // ---- history --------------------------------------------------------

    public function testHistoryIsWrittenInThreeLanguages(): void
    {
        $id = $this->buildWaybill(['status' => 3]);
        $this->accept($id);

        $history = History::find()->where(['waybill_id' => $id])->one();
        $this->assertNotNull($history);
        $this->assertEquals($this->me, $history->user_id);
        $this->assertSame('Test Courier accept waybill himself', $history->message_en);
        $this->assertStringContainsString('Test Courier', $history->message_uz);
        $this->assertStringContainsString('Test Courier', $history->message_ru);
    }

    /** @group known-gap */
    public function testHistoryIsWrittenEvenWhenNothingChanged(): void
    {
        $id = $this->buildWaybill(['status' => 11]);
        $this->accept($id);

        $this->assertEquals(11, $this->load($id)->status);
        $this->assertEquals(1, History::find()->where(['waybill_id' => $id])->count());
    }
}