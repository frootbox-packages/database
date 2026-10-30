<?php

/*
 * SPDX-License-Identifier: LGPL-3.0-only
 */

declare(strict_types=1);

namespace Frootbox\Database\Tests\Fixtures;

use DateTimeImmutable;
use Frootbox\Database\Entity;

final readonly class Article extends Entity
{
    /**
     * @param list<string>|null $tags
     */
    public function __construct(
        public int $id,
        public string $title,
        public ?string $body,
        public int $priority,
        public ArticleStatus $status,
        public bool $pinned,
        public ?array $tags,
        public ?DateTimeImmutable $publishedAt,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt,
    ) {}
}
