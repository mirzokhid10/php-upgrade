<?php

namespace Tests\Unit;

use app\models\Branch;
use app\models\City;
use app\models\NotificationForm;
use app\models\Waybill;
use Codeception\Test\Unit;
use Tests\Support\CreatesRecords;
use Tests\Support\UnitTester;
use Tests\Support\WaybillFixtures;
use Yii;

class NotificationFormTest extends Unit
{
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

    protected function _before()
    {
        $this->resetSms();
    }

    private const PLAIN_CITY = 5000; // a city that has no hard-coded recipient

    /** Inserts a row with this id only if the (possibly seeded) test DB does not have it yet. */
    private function ensureRecord(string $class, array $key, array $attributes = []): void
    {
        $table = $class::getTableSchema()->name;
        $exists = Yii::$app->db->createCommand("SELECT COUNT(*) FROM `$table` WHERE id = :id", [':id' => $key['id']])->queryScalar();
        if (!$exists) {
            $this->insertRow($table, array_merge($attributes, $key));
        }
    }

    private function regionId(): int
    {
        return (int) Yii::$app->db->createCommand('SELECT id FROM region ORDER BY id LIMIT 1')->queryScalar();
    }

    /** Waybill whose from-city exists (the form reads $waybill->fromCity->name_uz). */
    private function waybillIn(int $cityId, array $attrs = []): Waybill
    {
        $this->ensureRecord(City::class, ['id' => $cityId], ['name_uz' => 'Testcity']);
        $id = $this->buildWaybill(array_merge(['from_city_id' => $cityId, 'from_branch_id' => 0], $attrs));
        return Waybill::findOne($id);
    }

    private function notify(Waybill $waybill): bool
    {
        return (new NotificationForm(['barcode' => $waybill->barcode]))->saveNotification();
    }

    /** Runs $fn and returns the PHP warnings/notices it raised, without letting them fail the test. */
    private function captureWarnings(callable $fn): array
    {
        $seen = [];
        set_error_handler(function (int $no, string $msg) use (&$seen) {
            $seen[] = $msg;
            return true;
        });
        try {
            $fn();
        } finally {
            restore_error_handler();
        }
        return $seen;
    }

    // ---- rules ----------------------------------------------------------

    public function testEverythingIsOptional(): void
    {
        $this->assertTrue((new NotificationForm())->validate());
    }

    public function testOrderCountMustBeANonNegativeInteger(): void
    {
        foreach ([-1, 'abc', 1.5] as $bad) {
            $form = new NotificationForm(['order_count' => $bad]);
            $this->assertFalse($form->validate(), var_export($bad, true));
        }
        foreach ([0, 5, '7'] as $good) {
            $this->assertTrue((new NotificationForm(['order_count' => $good]))->validate(), var_export($good, true));
        }
    }

    public function testBarcodeMustBeText(): void
    {
        $this->assertFalse((new NotificationForm(['barcode' => ['a']]))->validate());
    }

    public function testInvalidFormSendsNothing(): void
    {
        $this->resetSms();
        $this->assertFalse((new NotificationForm(['order_count' => -5]))->saveNotification());
        $this->assertSame([], $this->sent());
    }

    // ---- sending --------------------------------------------------------

    public function testUnknownBarcodeSendsNothing(): void
    {
        $this->resetSms();
        $result = null;
        $warnings = $this->captureWarnings(function () use (&$result) {
            $result = (new NotificationForm(['barcode' => 'NOPE000000']))->saveNotification();
        });
        $this->assertTrue($result);
        $this->assertSame([], $this->sent());
        $this->assertSame([], $warnings);
    }

    public function testEachSpecialCityHasItsOwnRecipient(): void
    {
        $recipients = [
            199 => '998994443911', 135 => '998994443911', 126 => '998994443911', 124 => '998994443911',
            178 => '998994443902', 180 => '998994443901', 184 => '998994443903',
            177 => '998994443906', 179 => '998954443913',
        ];
        foreach ($recipients as $cityId => $phone) {
            $this->resetSms();
            $waybill = $this->waybillIn($cityId);
            $this->assertTrue($this->notify($waybill));

            $this->assertCount(1, $this->sent(), "city $cityId");
            ['phone' => $to, 'text' => $text, 'waybillid' => $wid, 'is_call_center' => $flag] = $this->sent()[0];
            $this->assertSame($phone, $to, "city $cityId");
            $this->assertStringContainsString($waybill->barcode, $text);
            $this->assertEquals($waybill->id, $wid);
            $this->assertEquals(1, $flag);
        }
    }

    public function testRegion9NotifiesTwoPeople(): void
    {
        $this->resetSms();
        $this->notify($this->waybillIn(self::PLAIN_CITY, ['from_region_id' => 9]));

        $this->assertSame(
            ['998910228484', '998918018686'],
            array_column($this->sent(), 'phone')
        );
    }

    public function testPlainCityWithoutBranchSendsNothing(): void
    {
        $this->resetSms();
        $this->notify($this->waybillIn(self::PLAIN_CITY));
        $this->assertSame([], $this->sent());
    }

    public function testBranch14GetsAnExtraMessage(): void
    {
        $this->resetSms();
        $this->ensureRecord(Branch::class, ['id' => 14], ['phone' => '+998 90 111 22 33', 'region_id' => $this->regionId(), 'city_id' => $this->twoCities()[0]]);
        $this->notify($this->waybillIn(199, ['from_branch_id' => 14]));

        // city 199 -> its fixed recipient only (the branch's own message is skipped for those cities),
        // plus branch 14's extra message.
        $phones = array_column($this->sent(), 'phone');
        $this->assertContains('998994443911', $phones);
        $this->assertContains('998994443990', $phones);
    }

    // ---- documented gaps (flip these when the behaviour is fixed) -------

    /**
     * $waybill is never assigned when no barcode is given, so `if ($waybill)` reads an undefined variable.
     * Fix: add `$waybill = null;` at the top of saveNotification(), then assert $warnings is empty.
     * @group known-gap
     * @group php8
     */
    public function testEmptyBarcodeReadsAnUndefinedVariable(): void
    {
        $this->resetSms();
        $form = new NotificationForm(['order_count' => 2]);
        $result = null;
        $warnings = $this->captureWarnings(function () use ($form, &$result) {
            $result = $form->saveNotification();
        });

        $this->assertTrue($result);
        $this->assertSame([], $this->sent());
        $this->assertNotEmpty(array_filter($warnings, fn($w) => str_contains($w, 'Undefined variable $waybill')));
    }

    /**
     * Regression from commit 9fc4d62: SmsComponent::send() became send($phone, $text, $waybill_id, ...), but the two
     * branch calls in NotificationForm still pass ($wid, $phone, $text, true). Red until those calls are fixed.
     * @group regression
     */
    public function testBranchMessageGoesToTheBranchPhone(): void
    {
        $this->resetSms();
        $this->ensureRecord(Branch::class, ['id' => 9020], ['phone' => '+998 90 123 45 00', 'region_id' => $this->regionId(), 'city_id' => $this->twoCities()[0]]);
        $waybill = $this->waybillIn(self::PLAIN_CITY, ['from_branch_id' => 9020]);
        $this->notify($waybill);

        $rows = $this->sent();
        $this->assertCount(1, $rows);
        $this->assertSame('998901234500', $rows[0]['phone']);
        $this->assertStringStartsWith('ZAYAVKA', $rows[0]['text']);
        $this->assertEquals($waybill->id, $rows[0]['waybillid']);
    }

    public function testBranch15IsExcluded(): void
    {
        $this->resetSms();
        $this->ensureRecord(Branch::class, ['id' => 15], ['phone' => '+998 90 123 45 15', 'region_id' => $this->regionId(), 'city_id' => $this->twoCities()[0]]);
        $this->notify($this->waybillIn(self::PLAIN_CITY, ['from_branch_id' => 15]));

        $this->assertSame([], $this->sent());
    }
}