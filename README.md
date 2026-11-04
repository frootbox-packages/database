# frootbox/database

Repositories, typed entities and a schema builder for migrations on top of
[Doctrine DBAL](https://www.doctrine-project.org/projects/dbal.html). Works
with SQLite, MySQL and MariaDB. Part of the Frootbox CMS, usable in any PHP
project.

```bash
composer require frootbox/database
```

## Entities and repositories

An entity is a readonly class. Columns map to constructor parameters by
name (`published_at` to `$publishedAt`); JSON, dates, enums and booleans
are converted.

```php
final readonly class Article extends Entity
{
    public function __construct(
        public int $id,
        public string $title,
        public ArticleStatus $status,
        public ?DateTimeImmutable $publishedAt,
        public DateTimeImmutable $createdAt,
    ) {}
}

/** @extends Repository<Article> */
final class Articles extends Repository
{
    protected function table(): string
    {
        return 'articles';
    }

    protected function entity(): string
    {
        return Article::class;
    }
}

$articles = new Articles($connection, new DirectWriter($connection));
```

## Reading

```php
$articles->fetch([
    'where' => [
        'status' => ArticleStatus::Published,   // status = 'published'
        'publishedAt <=' => new DateTimeImmutable(), // = != <> < <= > >= like, not like, in, not in
        'id' => [1, 2, 3],                      // IN (...)
        'deletedAt' => null,                    // IS NULL
        Where::any(['pinned' => true], ['priority >' => 5]), // OR
    ],
    'order' => ['publishedAt' => 'desc', 'title'],
    'limit' => 10,
]);

$articles->fetchOne(['where' => ['slug' => 'hello']]);
$articles->fetchById(5);       // or null
$articles->getById(5);         // or EntityNotFoundException
$articles->count(['status' => 'draft']);
$articles->paginate(['order' => 'title'], page: 2, perPage: 20);
```

Column names are validated and quoted; unknown options throw.

## Writing

```php
$article = $articles->insert(['title' => 'Hello', 'status' => ArticleStatus::Draft]);
$articles->update($article->id, ['title' => 'Hello again']);
$articles->delete($article->id);
```

`created_at` and `updated_at` are filled automatically when the entity has
them. Writes go through a `Writer`: `DirectWriter` writes straight to the
database; an application can supply its own, e.g. to allow writes only in
certain places or to record changes.

## Migrations

Migrations for [Doctrine Migrations](https://www.doctrine-project.org/projects/migrations.html)
extend `Frootbox\Database\Migration` and describe tables with a short
schema builder:

```php
$this->createTable('articles', function (Blueprint $table): void {
    $table->id();
    $table->string('title');
    $table->json('content')->nullable();
    $table->timestamps();
});
```

Columns: `id()`, `ulid()`, `string($name, $length = 255)`, `text()`,
`integer()`, `bigInteger()`, `boolean()`, `decimal($name, $precision,
$scale)`, `date()`, `dateTime()`, `json()` and `timestamps()` for
`created_at` and `updated_at`. Each one can be `->nullable()`,
`->default($value)`, `->unique()` or `->index()`, an integer also
`->unsigned()`. `primary()`, `index()` and `unique()` on the table take
several columns.

New tables, columns and indexes must have snake_case names of at most 64
characters (`published_at`); existing names are accepted as they are, so
legacy tables can still be changed. Also available: `alterTable()`,
`renameTable()` and `dropTable()`. Migrations run in a transaction except
on MySQL and MariaDB, which end transactions on schema changes.

For ids that must exist before a row is saved, e.g. for drafts, use
`$table->ulid()` instead of `$table->id()`. When the entity's `id` is a
`string`, `insert()` creates a new `Ulid` unless one is given.

## Source and issues

This package is developed in the [Frootbox monorepo](https://github.com/Frootbox/frootbox)
and mirrored here read-only. Questions, bugs and ideas go there, see the
[documentation](https://github.com/Frootbox/frootbox/tree/main/documentation).

## License

MIT, see [`LICENSE`](LICENSE).
