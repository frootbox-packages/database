<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Database;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\Migrations\AbstractMigration;
use Frootbox\Database\Schema\Blueprint;
use Frootbox\Database\Schema\SchemaException;

/**
 * Base class for all Frootbox migrations.
 *
 *     public function up(Schema $schema): void
 *     {
 *         $this->createTable('news', function (Blueprint $table): void {
 *             $table->id();
 *             $table->string('title');
 *             $table->timestamps();
 *         });
 *     }
 *
 * The SQL comes from Doctrine DBAL and fits SQLite, MySQL and MariaDB.
 * For anything the helpers do not cover, addSql() and the DBAL schema API
 * stay available.
 */
abstract class Migration extends AbstractMigration
{
    /** @var (\Closure(string, int|string, array<string, mixed>, array<string, mixed>): void)|null */
    private static ?\Closure $recorder = null;

    /**
     * Who hears of rows a migration changes with updateRow(), e.g. the log
     * of actions of the CMS. Null stops it.
     *
     * @internal
     *
     * @param (\Closure(string, int|string, array<string, mixed>, array<string, mixed>): void)|null $recorder table, id, before, after
     */
    public static function recordWith(?\Closure $recorder): void
    {
        self::$recorder = $recorder;
    }

    /**
     * MySQL and MariaDB end a transaction with every schema change, so
     * migrations run in a transaction only on the other databases.
     */
    #[\Override]
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    /**
     * @param \Closure(Blueprint): void $define
     */
    protected function createTable(string $name, \Closure $define): void
    {
        $blueprint = new Blueprint(Blueprint::snakeCase($name, 'Table'));
        $define($blueprint);

        foreach ($this->platform->getCreateTableSQL($blueprint->toTable()) as $sql) {
            $this->addSql($sql);
        }
    }

    /**
     * Changes an existing table: add, rename or drop columns and indexes.
     *
     * @param \Closure(Blueprint): void $define
     */
    protected function alterTable(string $name, \Closure $define): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $current = $schemaManager->introspectTableByUnquotedName($this->tableName($name));

        $blueprint = new Blueprint($this->tableName($name));
        $define($blueprint);

        $diff = $schemaManager->createComparator()->compareTables($current, $blueprint->applyTo($current));

        foreach ($this->platform->getAlterTableSQL($diff) as $sql) {
            $this->addSql($sql);
        }
    }

    protected function renameTable(string $from, string $to): void
    {
        $this->addSql($this->platform->getRenameTableSQL($this->tableName($from), Blueprint::snakeCase($to, 'Table')));
    }

    protected function dropTable(string $name): void
    {
        $this->addSql($this->platform->getDropTableSQL($this->tableName($name)));
    }

    /**
     * Changes columns of one row, e.g. content the migration rewrites. In
     * the CMS the change is kept in the log of actions, so it shows in the
     * history of a page like any other change.
     *
     * @param array<string, mixed> $before the columns as they were
     * @param array<string, mixed> $after  the same columns as they become
     */
    protected function updateRow(string $table, int|string $id, array $before, array $after): void
    {
        if ([] === $after) {
            return;
        }

        $sets = [];

        foreach (array_keys($after) as $column) {
            $sets[] = $this->connection->quoteSingleIdentifier($column) . ' = ?';
        }

        $this->addSql(
            \sprintf('UPDATE %s SET %s WHERE %s = ?', $this->connection->quoteSingleIdentifier($table), implode(', ', $sets), $this->connection->quoteSingleIdentifier('id')),
            [...array_values($after), $id],
        );

        if (null !== self::$recorder) {
            (self::$recorder)($table, $id, $before, $after);
        }
    }

    /**
     * @return non-empty-string
     */
    private function tableName(string $name): string
    {
        if ('' === $name) {
            throw new SchemaException('Empty table name.');
        }

        return $name;
    }
}
