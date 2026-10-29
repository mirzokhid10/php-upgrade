<?php

declare(strict_types=1);

namespace Tests\Support;

use yii\db\TableSchema;
use Yii;

/**
 * Inserts rows straight into the TEST database with a plain INSERT.
 *
 * Why not haveRecord()? Model callbacks such as Waybill::beforeSave() read
 * Yii::$app->user->identity, which is null outside an HTTP request.
 *
 * Every NOT NULL column without a default that is not given is filled from the table schema,
 * so the tests keep working when the real tables get new required columns.
 */
trait CreatesRecords
{
    /** @return int the new row's id */
    private function insertRow(string $table, array $attributes = []): int
    {
        $db = Yii::$app->db;
        $schema = $db->getTableSchema($table, true);

        if ($schema === null) {
            throw new \RuntimeException("Table `$table` does not exist in the test database.");
        }

        $db->createCommand()->insert($table, $this->fillRequiredFor($schema, $attributes))->execute();

        return (int) $db->getLastInsertID();
    }

    private function fillRequiredFor(TableSchema $schema, array $attributes): array
    {
        foreach ($schema->columns as $name => $column) {
            if (
                array_key_exists($name, $attributes)
                || $column->autoIncrement
                || $column->allowNull
                || $column->defaultValue !== null
            ) {
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
                default => substr(bin2hex(random_bytes(8)), 0, max(1, min((int) ($column->size ?? 8), 16))),
            };
        }

        return $attributes;
    }

    /** Reads one column of one row (table and column names come from the tests, never from users). */
    private function dbValue(string $table, string $column, int $id): mixed
    {
        return Yii::$app->db
            ->createCommand("SELECT `$column` FROM `$table` WHERE id = :id", [':id' => $id])
            ->queryScalar();
    }
}