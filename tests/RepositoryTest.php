<?php

/*
 * SPDX-License-Identifier: LGPL-3.0-only
 */

declare(strict_types=1);

namespace Frootbox\Database\Tests;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Frootbox\Database\DirectWriter;
use Frootbox\Database\Entity;
use Frootbox\Database\EntityMapper;
use Frootbox\Database\EntityNotFoundException;
use Frootbox\Database\Paginated;
use Frootbox\Database\Query\Criteria;
use Frootbox\Database\Repository;
use Frootbox\Database\RepositoryException;
use Frootbox\Database\Result;
use Frootbox\Database\Tests\Fixtures\Article;
use Frootbox\Database\Tests\Fixtures\Articles;
use Frootbox\Database\Tests\Fixtures\ArticleStatus;
use Frootbox\Database\Tests\Support\TestDatabase;
use Frootbox\Database\Values;
use Frootbox\Database\Where;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Repository::class)]
#[CoversClass(Criteria::class)]
#[CoversClass(EntityMapper::class)]
#[CoversClass(Entity::class)]
#[CoversClass(Result::class)]
#[CoversClass(Paginated::class)]
#[CoversClass(Values::class)]
#[CoversClass(Where::class)]
#[CoversClass(DirectWriter::class)]
final class RepositoryTest extends TestCase
{
    private Connection $connection;

    private Articles $articles;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(new DsnParser()->parse(TestDatabase::url()));
        Articles::createTable($this->connection);
        $this->articles = new Articles($this->connection, new DirectWriter($this->connection));

        $this->create('Alpha', priority: 1, status: ArticleStatus::Published, tags: ['red']);
        $this->create('Beta', priority: 5, publishedAt: new DateTimeImmutable('2026-01-01 10:00:00'));
        $this->create('Gamma', priority: 5, status: ArticleStatus::Published);
        $this->create('Delta', priority: 9, body: 'Text');
    }

    protected function tearDown(): void
    {
        TestDatabase::dropTables($this->connection, Articles::TABLE);
        $this->connection->close();
    }

    public function testFetchesTypedEntities(): void
    {
        $alpha = $this->articles->fetchOne(['where' => ['title' => 'Alpha']]);

        self::assertInstanceOf(Article::class, $alpha);
        self::assertSame(1, $alpha->priority);
        self::assertSame(ArticleStatus::Published, $alpha->status);
        self::assertSame(['red'], $alpha->tags);
        self::assertFalse($alpha->pinned);
        self::assertNull($alpha->body);
        self::assertNull($alpha->publishedAt);
        self::assertLessThanOrEqual(new DateTimeImmutable(), $alpha->createdAt);
    }

    public function testWhereConditions(): void
    {
        self::assertSame(['Beta', 'Gamma'], $this->titles(['where' => ['priority' => 5]]));
        self::assertSame(['Alpha', 'Gamma'], $this->titles(['where' => ['status' => ArticleStatus::Published]]));
        self::assertSame(['Beta', 'Gamma', 'Delta'], $this->titles(['where' => ['priority >=' => 5]]));
        self::assertSame(['Alpha', 'Delta'], $this->titles(['where' => ['priority !=' => 5]]));
        self::assertSame(['Alpha', 'Delta'], $this->titles(['where' => ['title' => ['Alpha', 'Delta', 'Omega']]]));
        self::assertSame(['Beta', 'Gamma'], $this->titles(['where' => ['title not in' => ['Alpha', 'Delta']]]));
        self::assertSame(['Delta'], $this->titles(['where' => ['body !=' => null]]));
        self::assertSame(['Beta'], $this->titles(['where' => ['publishedAt <' => new DateTimeImmutable('2026-06-01')]]));
        self::assertSame(['Gamma'], $this->titles(['where' => ['title like' => '%amm%']]));
        self::assertSame([], $this->titles(['where' => ['title' => []]]));
        self::assertSame(['Alpha', 'Delta'], $this->titles(['where' => [Where::any(['priority' => 1], ['body' => 'Text'])]]));
        self::assertSame(['Gamma'], $this->titles(['where' => ['status' => 'published', Where::any(['priority >' => 3], ['title' => 'Zeta'])]]));
    }

    public function testOrderLimitAndOffset(): void
    {
        self::assertSame(['Delta', 'Beta', 'Gamma', 'Alpha'], $this->titles(['order' => ['priority' => 'desc', 'title']]));
        self::assertSame(['Delta', 'Beta'], $this->titles(['order' => 'title desc', 'limit' => 2, 'offset' => 1]));
    }

    public function testPaginates(): void
    {
        $page = $this->articles->paginate(['order' => 'title'], page: 2, perPage: 3);

        self::assertSame(4, $page->total);
        self::assertSame(2, $page->pages());
        self::assertCount(1, $page);
        self::assertTrue($page->hasPreviousPage());
        self::assertFalse($page->hasNextPage());
        self::assertSame('Gamma', $page->items->first()?->title);
        self::assertSame(2, $page->jsonSerialize()['pages']);
    }

    public function testCountsAndFindsById(): void
    {
        self::assertSame(4, $this->articles->count());
        self::assertSame(2, $this->articles->count(['priority' => 5]));

        $id = $this->articles->fetchOne(['where' => ['title' => 'Delta']])->id ?? 0;
        self::assertSame('Delta', $this->articles->getById($id)->title);
        self::assertNull($this->articles->fetchById(999999));

        $this->expectException(EntityNotFoundException::class);
        $this->articles->getById(999999);
    }

    public function testFillsTimestamps(): void
    {
        $note = $this->articles->fetchOne(['where' => ['title' => 'Alpha']]);
        self::assertNotNull($note);
        self::assertNotNull($note->updatedAt);

        $renamed = $this->articles->update($note->id, ['title' => 'Alpha 2']);

        self::assertSame('Alpha 2', $renamed->title);
        self::assertEquals($note->createdAt, $renamed->createdAt);
        self::assertGreaterThanOrEqual($note->updatedAt, $renamed->updatedAt);
    }

    public function testRejectsUnknownOptions(): void
    {
        $this->expectException(RepositoryException::class);
        $this->expectExceptionMessage('Unknown option "orderBy"');

        $this->articles->fetch(['orderBy' => 'title']);
    }

    public function testRejectsInjectedColumnNames(): void
    {
        $this->expectException(RepositoryException::class);

        $this->articles->fetch(['where' => ['title; DROP TABLE notes' => 'x']]);
    }

    public function testResultHelpers(): void
    {
        $result = $this->articles->fetch(['order' => 'title']);

        self::assertFalse($result->isEmpty());
        self::assertSame('Alpha', $result->first()?->title);
        self::assertCount(4, $result->all());
        self::assertSame('Alpha', $result->jsonSerialize()[0]['title'] ?? null);
        self::assertTrue($this->articles->fetch(['where' => ['title' => 'Zeta']])->isEmpty());
    }

    public function testWritesDirectly(): void
    {
        $id = $this->articles->insert(['title' => 'Epsilon'])->id;

        self::assertSame('Epsilon 2', $this->articles->update($id, ['title' => 'Epsilon 2'])->title);
        self::assertTrue($this->articles->delete($id));
        self::assertFalse($this->articles->delete($id));

        $this->expectException(EntityNotFoundException::class);
        $this->articles->update($id, ['title' => 'Gone']);
    }

    public function testColumnNames(): void
    {
        self::assertSame('published_at', Values::column('publishedAt'));
        self::assertSame('published_at', Values::column('published_at'));
        self::assertSame('id', Values::column('id'));
    }

    /**
     * @param array{where?: array<int|string, mixed>, order?: string|array<int|string, string>, limit?: int, offset?: int} $options
     *
     * @return list<string>
     */
    private function titles(array $options): array
    {
        return $this->articles->fetch($options + ['order' => 'id'])->map(static fn(Article $article): string => $article->title);
    }

    /**
     * @param list<string> $tags
     */
    private function create(string $title, int $priority = 0, ArticleStatus $status = ArticleStatus::Draft, array $tags = [], ?string $body = null, ?DateTimeImmutable $publishedAt = null): void
    {
        $this->articles->insert(['title' => $title, 'body' => $body, 'priority' => $priority, 'status' => $status, 'tags' => $tags, 'publishedAt' => $publishedAt]);
    }
}
