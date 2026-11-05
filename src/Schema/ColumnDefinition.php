<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Database\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;

/**
 * One column of a Blueprint. Modifiers can be chained:
 * $table->string('slug')->nullable()->unique(). In alterTable(),
 * ->change() replaces an existing column with this definition.
 */
final class ColumnDefinition
{
    private bool $nullable = false;

    private bool $hasDefault = false;

    private mixed $default = null;

    private bool $unsigned = false;

    private bool $autoincrement = false;

    private bool $unique = false;

    private bool $index = false;

    private bool $change = false;

    /**
     * @param non-empty-string $name
     */
    public function __construct(
        public readonly string $name,
        private readonly string $type,
        private readonly ?int $length = null,
        private readonly ?int $precision = null,
        private readonly int $scale = 0,
    ) {}

    public function nullable(bool $nullable = true): self
    {
        $this->nullable = $nullable;

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->hasDefault = true;
        $this->default = $value;

        return $this;
    }

    public function unsigned(): self
    {
        $this->unsigned = true;

        return $this;
    }

    public function autoIncrement(): self
    {
        $this->autoincrement = true;

        return $this;
    }

    /**
     * Adds a unique index on this column.
     */
    public function unique(): self
    {
        $this->unique = true;

        return $this;
    }

    /**
     * Adds an index on this column.
     */
    public function index(): self
    {
        $this->index = true;

        return $this;
    }

    /**
     * Changes the existing column of this name instead of adding one, e.g.
     * $table->string('email')->nullable()->change(). The definition is the
     * whole new column: type, length, null and default as written here.
     * Indexes stay as they are.
     */
    public function change(): self
    {
        $this->change = true;

        return $this;
    }

    public function isChange(): bool
    {
        return $this->change;
    }

    public function isUnique(): bool
    {
        return $this->unique;
    }

    public function isIndexed(): bool
    {
        return $this->index;
    }

    public function toColumn(): Column
    {
        return $this->describe(Column::editor()->setUnquotedName($this->name))->create();
    }

    /**
     * Sets everything this definition says on a column, new or existing.
     */
    public function describe(ColumnEditor $editor): ColumnEditor
    {
        return $editor
            ->setTypeName($this->type)
            ->setLength($this->length)
            ->setPrecision($this->precision)
            ->setScale($this->scale)
            ->setNotNull(!$this->nullable)
            ->setUnsigned($this->unsigned)
            ->setAutoincrement($this->autoincrement)
            ->setDefaultValue($this->hasDefault ? $this->default : null);
    }
}
