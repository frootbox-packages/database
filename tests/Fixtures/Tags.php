<?php

/*
 * SPDX-License-Identifier: LGPL-3.0-only
 */

declare(strict_types=1);

namespace Frootbox\Database\Tests\Fixtures;

use Frootbox\Database\Repository;

/**
 * @extends Repository<Tag>
 */
final class Tags extends Repository
{
    public const string TABLE = 'test_tags';

    protected function table(): string
    {
        return self::TABLE;
    }

    protected function entity(): string
    {
        return Tag::class;
    }
}
