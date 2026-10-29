<?php

declare(strict_types=1);

namespace Tests\Support;

use app\models\History;
use app\models\User;
use Yii;

/**
 * Shared fixtures for Cests that exercise WaybillController.
 * Needs the CreatesUsers and CreatesRecords traits in the same class.
 */
trait WaybillFixtures
{
    private const COURIER_TOKEN = 'courier-token-0001';
    private const OTHER_COURIER_TOKEN = 'courier-token-0002';
    private const CLIENT_TOKEN = 'client-token-0001';
    private const OTHER_CLIENT_TOKEN = 'client-token-0002';

    private int $sequence = 0;

    /**
     * Two real client ids from the test DB that are not special-cased in the app
     * (4, 8, 504, 581, 653, 684, 812, 813, 3, 10, 36, 576).
     */
    private function clientIds(): array
    {
        $ids = Yii::$app->db->createCommand(
            'SELECT id FROM clients WHERE id NOT IN (3, 4, 8, 10, 36, 504, 576, 581, 653, 684, 812, 813) ORDER BY id LIMIT 2'
        )->queryColumn();

        if (count($ids) < 2) {
            throw new \RuntimeException('The test database needs at least two rows in `clients`.');
        }

        return array_map('intval', $ids);
    }

    /** Two real cities, preferably in different regions: [fromCityId, toCityId]. */
    private function twoCities(): array
    {
        $db = Yii::$app->db;
        $from = (int) $db->createCommand('SELECT id FROM city ORDER BY id LIMIT 1')->queryScalar();
        $to = (int) $db->createCommand(
            'SELECT id FROM city WHERE region_id <> (SELECT region_id FROM city WHERE id = :f) ORDER BY id LIMIT 1',
            [':f' => $from]
        )->queryScalar();

        if ($to === 0) {
            $to = (int) $db->createCommand('SELECT id FROM city WHERE id <> :f ORDER BY id LIMIT 1', [':f' => $from])->queryScalar();
        }
        if ($from === 0 || $to === 0) {
            throw new \RuntimeException('The test database needs at least two rows in `city`.');
        }

        return [$from, $to];
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
        return sprintf('9980%08d', ++$this->sequence);
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

    /** Every WaybillController answer is wrapped: {"status":"OK","code":200,"message":"Success","data": ...} */
    private function payload(ApiTester $I): mixed
    {
        return $this->json($I)['data'] ?? null;
    }

    private function historyCount(int $waybillId, string $messageLike): int
    {
        return (int) History::find()
            ->where(['waybill_id' => $waybillId])
            ->andWhere(['like', 'message_en', $messageLike])
            ->count();
    }

    /** Newest queued SMS for a phone number, or null. */
    private function smsFor(string $phone): ?array
    {
        $row = Yii::$app->db
            ->createCommand('SELECT * FROM sms WHERE phone = :phone ORDER BY id DESC LIMIT 1', [':phone' => $phone])
            ->queryOne();

        return $row ?: null;
    }

    private function countWaybills(): int
    {
        return (int) Yii::$app->db->createCommand('SELECT COUNT(*) FROM waybill')->queryScalar();
    }
}