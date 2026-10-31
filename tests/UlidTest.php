<?php

/*
 * SPDX-License-Identifier: LGPL-3.0-only
 */

declare(strict_types=1);

namespace Frootbox\Database\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Frootbox\Database\DirectWriter;
use Frootbox\Database\EntityMapper;
use Frootbox\Database\Repository;
use Frootbox\Database\Schema\Blueprint;
use Frootbox\Database\Tests\Fixtures\Tags;
use Frootbox\Database\Tests\Support\TestDatabase;
use Frootbox\Database\Ulid;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Ulid::class)]
#[CoversClass(Repository::class)]
#[CoversClass(EntityMapper::class)]
#[CoversClass(Blueprint::class)]
final class UlidTest extends TestCase
{
    public function testCreatesSortableIds(): void
    {
        $first = Ulid::generate(1_700_000_000_000);
        $second = Ulid::generate(1_700_000_000_001);

        self::assertSame(26, \strlen($first));
        self::assertTrue(Ulid::isValid($first));
        self::assertSame('01HF7YAT00', substr($first, 0, 10));
        self::assertLessThan(substr($second, 0, 10), substr($first, 0, 10));
        self::assertNotSame(Ulid::generate(), Ulid::generate());
    }

    public function testChecksTheFormat(): void
    {
        self::assertFalse(Ulid::isValid('01hf7yat00'), 'lower case');
        self::assertFalse(Ulid::isValid('01HF7YAT000000000000000000A'), 'too long');
        self::assertFalse(Ulid::isValid('01HF7YAT00ILOU00000000000'), 'I, L, O and U are not used');
        self::assertFalse(Ulid::isValid('81HF7YAT000000000000000000'), 'beyond the year 10889');
    }

    public function testRepositoriesCreateTheId(): void
    {
        $connection = DriverManager::getConnection(new DsnParser()->parse(TestDatabase::url()));
        $this->createTable($connection);
        $tags = new Tags($connection, new DirectWriter($connection));

        try {
            $tag = $tags->insert(['name' => 'red']);
            self::assertTrue(Ulid::isValid($tag->id));
            self::assertSame('red', $tags->getById($tag->id)->name);

            $own = Ulid::generate();
            self::assertSame($own, $tags->insert(['id' => $own, 'name' => 'blue', 'parentId' => $tag->id])->id);
            self::assertSame($tag->id, $tags->getById($own)->parentId);
        } finally {
            TestDatabase::dropTables($connection, Tags::TABLE);
            $connection->close();
        }
    }

    private function createTable(Connection $connection): void
    {
        TestDatabase::dropTables($connection, Tags::TABLE);
        $table = new Blueprint(Tags::TABLE);
        $table->ulid();
        $table->ulid('parent_id')->nullable();
        $table->string('name', 50);

        $connection->createSchemaManager()->createTable($table->toTable());
    }
}
