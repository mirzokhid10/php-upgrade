<?php

namespace Tests\Unit;

use app\models\History;
use app\models\Sign;
use app\models\User;
use app\models\Waybill;
use Codeception\Test\Unit;
use Tests\Support\CreatesUsers;
use Tests\Support\CreatesRecords;
use Tests\Support\UnitTester;
use Tests\Support\WaybillFixtures;
use Yii;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

/**
 * UploadedFile::saveAs($path, false) copies the temp file, so these tests upload a real 1x1 PNG
 * and assert the files written under params['sign_dir'] as well as status, history and SMS.
 */
class SignTest extends Unit
{
    use CreatesUsers;
    use CreatesRecords;
    use WaybillFixtures;

    protected UnitTester $tester;

    private int $smsFrom = 0;

    /** Forget SMS rows queued so far (the real SmsComponent writes to the `sms` table in tests). */
    private function resetSms(): void
    {
        $this->smsFrom = (int) Yii::$app->db->createCommand('SELECT COALESCE(MAX(id), 0) FROM sms')->queryScalar();
    }

    /** SMS rows queued since resetSms(), oldest first. */
    private function sent(): array
    {
        return Yii::$app->db->createCommand(
            'SELECT phone, text, waybillid, is_call_center FROM sms WHERE id > :from ORDER BY id',
            [':from' => $this->smsFrom]
        )->queryAll();
    }

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
    private string $dir;
    private string $png = '';

    protected function _before()
    {
        $this->dir = sys_get_temp_dir() . '/sign_test_' . uniqid() . '/';
        Yii::$app->params['sign_dir'] = $this->dir;
        $this->png = tempnam(sys_get_temp_dir(), 'sign');
        file_put_contents($this->png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        $this->me = $this->createUser($this->tester, ['fullname' => 'Test Courier']);
        Yii::$app->user->setIdentity(User::findOne($this->me));
        $this->resetSms();
    }

    protected function _after()
    {
        if (is_file($this->png)) {
            unlink($this->png);
        }
        if (is_dir($this->dir)) {
            FileHelper::removeDirectory($this->dir);
        }
    }

    private function load(int $id): Waybill
    {
        return Waybill::findOne($id);
    }

    private function sign(array $attrs, bool $withFile = true): Sign
    {
        $model = new Sign();
        $model->setAttributes($attrs);
        $model->user_id = $this->me;
        if ($withFile) {
            $model->sign_file = new UploadedFile([
                'name' => 'sign.png', 'tempName' => $this->png,
                'type' => 'image/png', 'size' => 1, 'error' => UPLOAD_ERR_OK,
            ]);
        }
        return $model;
    }

    private function upload(string $name = 'sign.png'): UploadedFile
    {
        return new UploadedFile(['name' => $name, 'tempName' => $this->png, 'type' => 'image/png', 'size' => 1, 'error' => UPLOAD_ERR_OK]);
    }

    private function signPath(int $waybillId, string $file): string
    {
        return $this->dir . date('Y/m/d', $this->load($waybillId)->created_at) . '/' . $waybillId . '/' . $file;
    }

    // ---- rules ----------------------------------------------------------

    public function testRules(): void
    {
        $model = new Sign(['user_id' => 'abc', 'waybill_id' => 999999999, 'full_name' => str_repeat('x', 256)]);
        $this->assertFalse($model->validate());
        $this->assertTrue($model->hasErrors('user_id'));
        $this->assertTrue($model->hasErrors('waybill_id'));
        $this->assertTrue($model->hasErrors('full_name'));

        $this->assertTrue((new Sign(['full_name' => 'Jane', 'is_delivery' => 1]))->validate());
    }

    public function testIdIsNotMassAssignable(): void
    {
        $model = new Sign();
        $model->setAttributes(['id' => 5, 'wids' => '1']);
        $this->assertNull($model->id);
        $this->assertSame('1', $model->wids);
    }

    // ---- nothing to do --------------------------------------------------

    public function testNothingHappensWithoutASignFile(): void
    {
        $id = $this->buildWaybill(['status' => 10]);
        $this->sign(['wids' => (string) $id, 'is_delivery' => 1], false)->saveSign();

        $this->assertEquals(10, $this->load($id)->status);
        $this->assertSame([], $this->sent());
    }

    public function testNothingHappensWithoutWaybillIdsOrBarcodes(): void
    {
        $id = $this->buildWaybill(['status' => 10]);
        $this->sign(['is_delivery' => 1])->saveSign();
        $this->assertEquals(10, $this->load($id)->status);
    }

    // ---- delivery -------------------------------------------------------

    public function testDeliveryMovesToStatus11SendsSmsAndLogsHistory(): void
    {
        $id = $this->buildWaybill(['status' => 10, 'to_fullname' => 'Receiver']);
        $barcode = $this->load($id)->barcode;
        $model = $this->sign(['wids' => (string) $id, 'is_delivery' => 1, 'full_name' => 'Jane', 'datetime' => 1700000000]);
        $model->saveSign();

        $wb = $this->load($id);
        $this->assertEquals(11, $wb->status);
        $this->assertEquals(10, $wb->old_status);
        $this->assertEquals(0, $wb->for_sign);
        $this->assertEquals(1700000000, $wb->delivery_datetime);
        $this->assertSame(1, $this->historyCount($id, 'to Jane'));
        $this->assertFileExists($this->signPath($id, 'delivery_sign.png'));
        $this->assertSame(1, $this->historyCount($id, 'saved electron sign'));

        $this->assertCount(1, $this->sent());
        ['phone' => $to, 'text' => $text, 'waybillid' => $wid] = $this->sent()[0];
        $this->assertSame('998901234567', $to, 'spaces are stripped from the sender phone');
        $this->assertStringContainsString($barcode, $text);
        $this->assertStringContainsString('Receiver', $text);
        $this->assertEquals($id, $wid);
        $this->assertStringContainsString('Hurmatli mijoz', $text);
    }

    public function testDeliveryByBarcodeWorksToo(): void
    {
        $id = $this->buildWaybill(['status' => 10]);
        $this->sign(['barcodes' => $this->load($id)->barcode, 'is_delivery' => 1, 'full_name' => 'Jane'])->saveSign();
        $this->assertEquals(11, $this->load($id)->status);
        $this->assertCount(1, $this->sent());
    }

    public function testLongSmsTextIsShortened(): void
    {
        $id = $this->buildWaybill(['status' => 10, 'to_fullname' => str_repeat('A', 100)]);
        $this->sign(['wids' => (string) $id, 'is_delivery' => 1])->saveSign();

        $text = $this->sent()[0]['text'];
        $this->assertStringNotContainsString('Hurmatli mijoz', $text);
        $this->assertStringContainsString('Pochtangiz', $text);
    }

    public function testSpecialClientsGoThroughStatus10Then57(): void
    {
        $id = $this->buildWaybill(['status' => 3, 'from_client_id' => 504]);

        $first = $this->sign(['wids' => (string) $id, 'is_delivery' => 1]);
        $first->saveSign();
        $this->assertEquals(10, $this->load($id)->status);
        $this->assertEquals($this->me, $this->load($id)->delivery_courier_id);
        $this->assertSame('status=10;', $first->msg);

        $second = $this->sign(['wids' => (string) $id, 'is_delivery' => 1]);
        $second->saveSign();
        $this->assertEquals(57, $this->load($id)->status);
        $this->assertSame('status=57;', $second->msg);
    }

    public function testCashOnDeliveryFlagIsReset(): void
    {
        $id = $this->buildWaybill(['status' => 10, 'cod_taken' => 1]);
        $this->sign(['wids' => (string) $id, 'is_delivery' => 1])->saveSign();
        $this->assertEquals(0, $this->load($id)->cod_taken);
        $this->assertFileExists($this->signPath($id, 'given_money_sign.png'));
    }

    public function testSeveralIdsWithGapsAndUnknownIds(): void
    {
        $a = $this->buildWaybill(['status' => 10]);
        $b = $this->buildWaybill(['status' => 10]);
        $this->sign(['wids' => "$a,,999999999,$b", 'is_delivery' => 1])->saveSign();

        $this->assertEquals(11, $this->load($a)->status);
        $this->assertEquals(11, $this->load($b)->status);
        $this->assertCount(2, $this->sent());
    }

    // ---- pickup ---------------------------------------------------------

    public function testPickupMovesToStatus3WithoutSms(): void
    {
        foreach ([1, 2] as $start) {
            $id = $this->buildWaybill(['status' => $start]);
            $model = $this->sign(['wids' => (string) $id, 'is_delivery' => 0, 'full_name' => 'Sender Name', 'datetime' => 1700000001]);
            $model->saveSign();

            $wb = $this->load($id);
            $this->assertEquals(3, $wb->status, "start $start");
            $this->assertEquals($start, $wb->old_status);
            $this->assertEquals(1700000001, $wb->pickup_datetime);
            $this->assertEquals($this->me, $wb->delivery_courier_id);
            $this->assertSame('status=3;', $model->msg);
            $this->assertSame(1, $this->historyCount($id, 'picked up'));
            $this->assertFileExists($this->signPath($id, 'pickup_sign.png'), "start $start");
        }
        $this->assertSame([], $this->sent());
    }

    public function testPickupByBarcodeAssignsThePickupCourier(): void
    {
        $id = $this->buildWaybill(['status' => 1]);
        $this->sign(['barcodes' => $this->load($id)->barcode, 'is_delivery' => 0])->saveSign();
        $this->assertEquals($this->me, $this->load($id)->pickup_courier_id);
    }

    public function testPickupBySpecialClientKeepsTheStatus(): void
    {
        $id = $this->buildWaybill(['status' => 1, 'from_client_id' => 504]);
        $this->sign(['wids' => (string) $id, 'is_delivery' => 0])->saveSign();
        $this->assertEquals(1, $this->load($id)->status);
    }

    public function testPhotoIsSavedNextToTheSignature(): void
    {
        $id = $this->buildWaybill(['status' => 10]);
        $model = $this->sign(['wids' => (string) $id, 'is_delivery' => 1]);
        $model->photo = $this->upload('photo.png');
        $model->saveSign();

        $this->assertFileExists($this->signPath($id, 'photo.png'));
        $this->assertSame(1, $this->historyCount($id, 'saved photo report'));
    }

    // ---- documented gaps (flip these when the behaviour is fixed) -------

    /**
     * The waybill-id branch never sets pickup_courier_id; the barcode branch does.
     * @group known-gap
     */
    public function testPickupByWaybillIdLeavesThePickupCourierEmpty(): void
    {
        $id = $this->buildWaybill(['status' => 1, 'pickup_courier_id' => 0]);
        $this->sign(['wids' => (string) $id, 'is_delivery' => 0])->saveSign();
        $this->assertEquals(0, $this->load($id)->pickup_courier_id);
    }

    public function testAlreadyDeliveredWaybillGetsNoSecondSms(): void
    {
        $id = $this->buildWaybill(['status' => 11]);
        $this->sign(['wids' => (string) $id, 'is_delivery' => 1])->saveSign();

        $this->assertSame([], $this->sent());
    }
}