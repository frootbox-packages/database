<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Database\Tests\Schema;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Tools\DsnParser;
use Frootbox\Database\Schema\Blueprint;
use Frootbox\Database\Schema\ColumnDefinition;
use Frootbox\Database\Schema\SchemaException;
use Frootbox\Database\Tests\Support\TestDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks the generated SQL for every supported database, without a connection.
 */
#[CoversClass(Blueprint::class)]
#[CoversClass(ColumnDefinition::class)]
final class BlueprintTest extends TestCase
{
    /**
     * @return iterable<string, array{AbstractPlatform, list<string>}>
     */
    public static function platforms(): iterable
    {
        yield 'SQLite' => [new SQLitePlatform(), [
            'id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL',
            'title VARCHAR(255) NOT NULL',
            'content CLOB DEFAULT NULL',
            'price NUMERIC(8, 2) NOT NULL',
            'visible BOOLEAN DEFAULT 1 NOT NULL',
            'created_at DATETIME DEFAULT NULL',
            'CREATE UNIQUE INDEX news_slug_unique ON news (slug)',
            'CREATE INDEX news_published_on_visible_index ON news (published_on, visible)',
        ]];

        yield 'MySQL' => [new MySQL80Platform(), [
            'id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL',
            'title VARCHAR(255) NOT NULL',
            'content JSON DEFAULT NULL',
            'price NUMERIC(8, 2) NOT NULL',
            'visible TINYINT DEFAULT 1 NOT NULL',
            'PRIMARY KEY (id)',
            'UNIQUE INDEX news_slug_unique (slug)',
            'INDEX news_published_on_visible_index (published_on, visible)',
        ]];

        yield 'MariaDB' => [new MariaDBPlatform(), [
            'id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL',
            'content JSON DEFAULT NULL',
            'PRIMARY KEY (id)',
            'UNIQUE INDEX news_slug_unique (slug)',
        ]];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('platforms')]
    public function testCreatesPortableTable(AbstractPlatform $platform, array $expected): void
    {
        $table = new Blueprint('news');
        $table->id();
        $table->string('title');
        $table->string('slug', 100)->unique();
        $table->json('content')->nullable();
        $table->decimal('price', 8, 2);
        $table->date('published_on');
        $table->boolean('visible')->default(true);
        $table->timestamps();
        $table->index(['published_on', 'visible']);

        $sql = implode("\n", $platform->getCreateTableSQL($table->toTable()));

        foreach ($expected as $fragment) {
            self::assertStringContainsString($fragment, $sql);
        }
    }

    public function testCompositePrimaryKey(): void
    {
        $table = new Blueprint('news_tags');
        $table->bigInteger('news_id');
        $table->bigInteger('tag_id');
        $table->primary('news_id', 'tag_id');

        $sql = implode("\n", new MySQL80Platform()->getCreateTableSQL($table->toTable()));

        self::assertStringContainsString('PRIMARY KEY (news_id, tag_id)', $sql);
    }

    public function testShortensLongIndexNames(): void
    {
        $table = new Blueprint(str_repeat('t', 40));
        $table->string(str_repeat('c', 40))->index();

        $sql = implode("\n", new SQLitePlatform()->getCreateTableSQL($table->toTable()));

        self::assertMatchesRegularExpression('/CREATE INDEX (\w{64}) ON/', $sql);
    }

    public function testCannotDropColumnsOfANewTable(): void
    {
        $table = new Blueprint('news');
        $table->dropColumn('title');

        $this->expectException(SchemaException::class);

        $table->toTable();
    }

    public function testRejectsEmptyNames(): void
    {
        $this->expectException(SchemaException::class);

        new Blueprint('news')->string('');
    }

    #[DataProvider('badNames')]
    public function testNewNamesAreSnakeCase(\Closure $define): void
    {
        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('must be snake_case');

        $define(new Blueprint('news'));
    }

    /**
     * @return iterable<string, array{\Closure(Blueprint): mixed}>
     */
    public static function badNames(): iterable
    {
        yield 'camelCase column' => [static fn(Blueprint $table) => $table->dateTime('publishedAt')];
        yield 'PascalCase column' => [static fn(Blueprint $table) => $table->string('Title')];
        yield 'dashed column' => [static fn(Blueprint $table) => $table->string('sub-title')];
        yield 'too long' => [static fn(Blueprint $table) => $table->string(str_repeat('a', 65))];
        yield 'renamed to camelCase' => [static fn(Blueprint $table) => $table->renameColumn('Old_Name', 'newName')];
        yield 'index name' => [static fn(Blueprint $table) => $table->index('title', 'TitleIndex')];
        yield 'table name' => [static fn(Blueprint $table) => Blueprint::snakeCase('NewsItems', 'Table')];
    }

    public function testExistingNamesAreTakenAsTheyAre(): void
    {
        $table = new Blueprint('legacy');
        $table->dropColumn('OldColumn');
        $table->renameColumn('Other_Column', 'other_column');
        $table->dropIndex('IDX_Legacy');

        self::assertSame('published_at', Blueprint::snakeCase('published_at', 'Column'));
    }

    public function testChangesAnExistingColumnAndKeepsItsIndex(): void
    {
        $connection = DriverManager::getConnection(new DsnParser()->parse(TestDatabase::url()));
        TestDatabase::dropTables($connection, 'blueprint_people');
        $create = new Blueprint('blueprint_people');
        $create->id();
        $create->string('email')->unique();
        $create->string('kind', 20)->default('person');

        foreach ($connection->getDatabasePlatform()->getCreateTableSQL($create->toTable()) as $sql) {
            $connection->executeStatement($sql);
        }

        $connection->insert('blueprint_people', ['email' => 'a@example.test']);
        $schemaManager = $connection->createSchemaManager();
        $current = $schemaManager->introspectTableByUnquotedName('blueprint_people');
        $alter = new Blueprint('blueprint_people');
        $alter->string('email')->nullable()->change();
        $diff = $schemaManager->createComparator()->compareTables($current, $alter->applyTo($current));

        foreach ($connection->getDatabasePlatform()->getAlterTableSQL($diff) as $sql) {
            $connection->executeStatement($sql);
        }

        $connection->insert('blueprint_people', ['email' => null]);
        $connection->insert('blueprint_people', ['email' => null]);
        $changed = $schemaManager->introspectTableByUnquotedName('blueprint_people');

        self::assertFalse($changed->getColumn('email')->getNotnull());
        self::assertSame('person', $changed->getColumn('kind')->getDefault(), 'Other columns stay.');
        self::assertSame(['a@example.test', null, null], $connection->fetchFirstColumn('SELECT email FROM blueprint_people ORDER BY id'));
        self::assertCount(1, array_filter($changed->getIndexes(), static fn($index): bool => $index->isUnique() && !$index->isPrimary()), 'The unique index stays.');

        TestDatabase::dropTables($connection, 'blueprint_people');
    }

    public function testCannotChangeAColumnOfANewTable(): void
    {
        $table = new Blueprint('people');
        $table->string('email')->nullable()->change();

        $this->expectException(SchemaException::class);
        $table->toTable();
    }
}
