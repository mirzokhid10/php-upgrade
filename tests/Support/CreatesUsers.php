<?php

declare(strict_types=1);

namespace Tests\Support;

use app\models\User;
use Yii;

/**
 * Shared helpers for Cests that need users in the TEST database.
 * (Constants in traits need PHP 8.2+.)
 */
trait CreatesUsers
{
    private const PHONE = '998901234567';
    private const OTHER_PHONE = '998907654321';
    private const PASSWORD = 'secret123';
    private const TOKEN = 'test-access-token-123';
    private const OTHER_TOKEN = 'test-access-token-456';

    /**
     * Inserts a user directly into the TEST database and returns its id.
     * Required columns that are not given (e.g. `email`) are filled from the table schema.
     */
    private function createUser(ApiTester $I, array $overrides = []): int
    {
        $attributes = array_merge([
            'username' => self::PHONE,
            'password_hash' => Yii::$app->security->generatePasswordHash(self::PASSWORD),
            'access_token' => self::TOKEN,
            'auth_key' => 'test-auth-key',
            'fullname' => 'Test Courier',
            'role_id' => 1,
            'status' => User::STATUS_ACTIVE,
        ], $overrides);

        return (int) $I->haveRecord(User::class, $this->fillRequiredColumns($attributes));
    }

    private function fillRequiredColumns(array $attributes): array
    {
        $seed = (string) ($attributes['username'] ?? self::PHONE);

        foreach (User::getTableSchema()->columns as $name => $column) {
            if (
                array_key_exists($name, $attributes)
                || $column->autoIncrement
                || $column->allowNull
                || $column->defaultValue !== null
            ) {
                continue;
            }

            if ($name === 'email') {
                $attributes[$name] = $seed . '@test.local';
                continue;
            }

            if (!empty($column->enumValues)) {
                $attributes[$name] = $column->enumValues[0];
                continue;
            }

            $attributes[$name] = match ($column->type) {
                'tinyint', 'smallint', 'integer', 'bigint', 'boolean',
                'float', 'double', 'decimal', 'money' => 0,
                'date' => '2000-01-01',
                'time' => '00:00:00',
                'datetime', 'timestamp' => '2000-01-01 00:00:00',
                'json' => '{}',
                default => substr(md5($seed . $name), 0, max(1, min((int) ($column->size ?? 8), 16))),
            };
        }

        return $attributes;
    }

    private function json(ApiTester $I): array
    {
        $data = json_decode($I->grabResponse(), true);

        return is_array($data) ? $data : [];
    }

    private function assertNoToken(ApiTester $I): void
    {
        $data = $this->json($I)['data'] ?? [];
        $I->assertArrayNotHasKey('token', is_array($data) ? $data : [], 'response must not contain a token');
    }
}