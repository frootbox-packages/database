<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Database;

use RuntimeException;

/**
 * Wrong use of a repository, e.g. an unknown option or a bad column name.
 */
final class RepositoryException extends RuntimeException {}
