<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Database\Tests\Fixtures;

use Doctrine\DBAL\Connection;
use Frootbox\Database\Repository;
use Frootbox\Database\Schema\Blueprint;
use Frootbox\Database\Tests\Support\TestDatabase;

/**
 * @extends Repository<Article>
 */
final class Articles extends Repository
{
    public const string TABLE = 'test_articles';

    public static function createTable(Connection $connection): void
    {
        TestDatabase::dropTables($connection, self::TABLE);

        $table = new Blueprint(self::TABLE);
        $table->id();
        $table->string('title', 100);
        $table->text('body')->nullable();
        $table->integer('priority')->default(0);
        $table->string('status', 20)->default('draft');
        $table->boolean('pinned')->default(false);
        $table->json('tags')->nullable();
        $table->dateTime('published_at')->nullable();
        $table->timestamps();

        $connection->createSchemaManager()->createTable($table->toTable());
    }

    protected function table(): string
    {
        return self::TABLE;
    }

    protected function entity(): string
    {
        return Article::class;
    }
}
