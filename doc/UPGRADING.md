# Upgrading Horde_Db

**Contact:** dev@lists.horde.org

---

## Table of Contents

- [Upgrading to 3.0 (PSR-4 / src/)](#upgrading-to-30-psr-4--src)
  - [Overview](#overview)
  - [Breaking Changes](#breaking-changes)
  - [Migration Guide](#migration-guide)
  - [New Features in 3.0](#new-features-in-30)
  - [Deprecations](#deprecations)
  - [PHP Version Requirements](#php-version-requirements)
- [Previous Upgrades](#previous-upgrades)
  - [Upgrading to 2.2.0](#upgrading-to-220)
  - [Upgrading to 2.1.0](#upgrading-to-210)

---

## Upgrading to 3.0 (PSR-4 / src/)

### Overview

Version 3.0 introduces a modern **PSR-4 compliant variant** alongside the legacy PSR-0 code:

- **lib/** - PSR-0 (legacy) - `Horde_Db_*` classes
- **src/** - PSR-4 (modern) - `Horde\Db\*` namespaced classes

Both variants are included in the same package and have **feature parity**. You can:
- Continue using `lib/` (PSR-0) indefinitely
- Migrate to `src/` (PSR-4) incrementally
- Mix both in the same codebase during migration

### Breaking Changes

#### 1. Namespace Changes (src/ only)

**PSR-0 (lib/):**
```php
use Horde_Db_Adapter;
use Horde_Db_Exception;
use Horde_Db;

$db = Horde_Db_Adapter::factory('mysqli', $config);
$result = $db->select($sql);
```

**PSR-4 (src/):**
```php
use Horde\Db\Adapter;
use Horde\Db\DbException;
use Horde\Db\Constants;

$db = Adapter::factory('mysqli', $config);
$result = $db->select($sql);
```

#### 2. Exception Classes Renamed (src/ only)

| PSR-0 (lib/) | PSR-4 (src/) |
|--------------|--------------|
| `Horde_Db_Exception` | `Horde\Db\DbException` |
| `Horde_Db_Migration_Exception` | `Horde\Db\Migration\MigrationException` |

#### 3. Constants Class Renamed (src/ only)

**PSR-0 (lib/):**
```php
Horde_Db::FETCH_ASSOC
Horde_Db::FETCH_NUM
Horde_Db::FETCH_BOTH
```

**PSR-4 (src/):**
```php
Horde\Db\Constants::FETCH_ASSOC
Horde\Db\Constants::FETCH_NUM
Horde\Db\Constants::FETCH_BOTH
```

#### 4. Deprecated mysql Extension Support Removed (src/ only)

The **ext-mysql** adapter (deprecated in PHP 5.5, removed in PHP 7.0) is **not available** in src/:

**Removed classes:**
- `Horde\Db\Adapter\Mysql` (use `Mysqli` or `Pdo\Mysql` instead)
- `Horde\Db\Adapter\Mysql\Result`

**Migration:**
```php
// Old (lib/ only, PHP < 7.0)
$config = ['adapter' => 'mysql', ...];

// New (both lib/ and src/)
$config = ['adapter' => 'mysqli', ...];
// or
$config = ['adapter' => 'pdo_mysql', ...];
```

**Note:** The `mysql` adapter is still available in `lib/` for legacy compatibility but should not be used.

---

### Migration Guide

#### Strategy 1: Continue Using lib/ (No Changes Required)

The **lib/** (PSR-0) variant remains fully supported:

```php
// No changes needed - lib/ still works
use Horde_Db_Adapter;

$db = Horde_Db_Adapter::factory('mysqli', $config);
// All existing code continues to work
```

**Best for:**
- Stable production codebases
- Projects not ready for namespace migration
- Maximum backward compatibility

---

#### Strategy 2: Migrate to src/ (Recommended for New Code)

**Step 1: Update imports**

Use global search-replace in your codebase:

| Find | Replace |
|------|---------|
| `use Horde_Db_Adapter` | `use Horde\Db\Adapter` |
| `use Horde_Db_Exception` | `use Horde\Db\DbException` |
| `use Horde_Db_Migration_Base` | `use Horde\Db\Migration\Base` |
| `use Horde_Db_Migration_Migrator` | `use Horde\Db\Migration\Migrator` |
| `use Horde_Db_Value` | `use Horde\Db\Value` |
| `Horde_Db::FETCH_` | `Horde\Db\Constants::FETCH_` |

**Step 2: Update exception handling**

```php
// Before (lib/)
try {
    $db->query($sql);
} catch (Horde_Db_Exception $e) {
    // handle
}

// After (src/)
try {
    $db->query($sql);
} catch (Horde\Db\DbException $e) {
    // handle
}
```

**Step 3: Update type hints (if used)**

```php
// Before (lib/)
function processResults(Horde_Db_Adapter_Base $db) { }

// After (src/)
function processResults(Horde\Db\Adapter\Base $db) { }
```

**Step 4: Update mysql adapter references**

```php
// Before (lib/, deprecated)
$config = ['adapter' => 'mysql'];

// After (src/ and modern lib/)
$config = ['adapter' => 'mysqli'];
// or
$config = ['adapter' => 'pdo_mysql'];
```

---

#### Strategy 3: Incremental Migration (Both Variants)

You can use **both lib/ and src/** in the same codebase during migration:

```php
// Legacy code continues using lib/
use Horde_Db_Adapter as LegacyAdapter;
$legacyDb = LegacyAdapter::factory('mysqli', $config);

// New code uses src/
use Horde\Db\Adapter;
$modernDb = Adapter::factory('mysqli', $config);
```

Both connect to the same database and are fully compatible.

---

### New Features in 3.0

#### 1. Server Capability Detection (src/ only)

Query database server features programmatically:

```php
use Horde\Db\Adapter\CapabilityDetection;

$db = Adapter::factory('mysqli', $config);

if ($db instanceof CapabilityDetection) {
    $info = $db->getServerCapabilities();

    // MySQL/MariaDB version detection
    echo $info->getDescription();  // "MySQL 8.0.35" or "MariaDB 10.11.2"

    // Feature detection
    if ($info->supportsJSON()) {
        // Use JSON columns safely
        $db->addColumn('users', 'metadata', 'json');
    }

    if ($info->supportsCTE()) {
        // Use Common Table Expressions (WITH clause)
    }

    if ($info->supportsWindowFunctions()) {
        // Use OVER, PARTITION BY, etc.
    }
}
```

**Available capability checks:**
- `supportsJSON()` - JSON data type and functions (MySQL 5.7.8+, MariaDB 10.2.7+)
- `supportsCTE()` - Common Table Expressions (MySQL 8.0+, MariaDB 10.2.1+)
- `supportsWindowFunctions()` - Window functions (MySQL 8.0+, MariaDB 10.2+)
- `supportsCheckConstraints()` - CHECK constraints (MySQL 8.0.16+, MariaDB 10.2.1+)
- `supportsGeneratedColumns()` - Generated/computed columns (MySQL 5.7+, MariaDB 5.2+)
- And more...

**Also available via `hasCapability()` shortcut:**
```php
if ($db->hasCapability('json')) {
    // Use JSON features
}
```

#### 2. TEXT/BLOB/JSON Default Values (Both lib/ and src/)

MySQL 8.0.13+ and MariaDB 10.2.1+ support default values for TEXT, BLOB, JSON, and GEOMETRY columns using expression syntax.

**Automatic handling:**
```php
// Horde_Db automatically converts to expression syntax when needed
$db->addColumn('articles', 'body', 'text', ['default' => 'No content']);
// Generates: body TEXT DEFAULT ('No content')

$db->addColumn('metadata', 'data', 'json', ['default' => '{}']);
// Generates: data JSON DEFAULT ('{}')
```

The schema layer automatically detects server capabilities and applies the correct syntax.

#### 3. UTF-8 Charset Auto-Upgrade (Both lib/ and src/)

MySQL 8.0+ and MariaDB 10.6+ no longer automatically alias `utf8` to `utf8mb4`, causing connection failures with legacy configurations.

**Automatic upgrade:**
```php
// Your config specifies legacy charset
$config = [
    'adapter' => 'mysqli',
    'charset' => 'utf8'  // Legacy 3-byte UTF-8
];

// Horde_Db automatically upgrades to utf8mb4 (4-byte UTF-8)
$db = Adapter::factory('mysqli', $config);
// Connection uses 'utf8mb4' instead, preventing errors
```

No code changes required - this happens transparently.

#### 4. PHP 8.1+ Compatibility (Both lib/ and src/)

Full compatibility with PHP 8.1, 8.2, and 8.3+:

- Fixed null handling in SQLite adapter methods
- Replaced deprecated `parent::` callable syntax
- Proper return type declarations for Iterator methods
- No deprecation warnings on modern PHP

#### 5. Modern Type Declarations (src/ only - partial)

New code in src/ uses PHP 8.0+ features:

```php
// ServerInfo class uses readonly properties
class ServerInfo
{
    public readonly string $versionString;
    public readonly bool $isMariaDB;
    public readonly int $majorVersion;
}

// CapabilityDetection interface uses return types
interface CapabilityDetection
{
    public function getServerCapabilities(): object;
    public function hasCapability(string $capability): bool;
}
```

**Note:** Most legacy code in src/ still uses PHPDoc-only type hints for backward compatibility.

---

### Deprecations

#### 1. mysql Extension Adapter (Removed in src/, Deprecated in lib/)

**Status:**
- **Removed** from src/
- **Deprecated** in lib/ (but still available)

**Migration:**
```php
// Deprecated
$config = ['adapter' => 'mysql'];

// Use mysqli
$config = ['adapter' => 'mysqli'];

// Or use PDO
$config = ['adapter' => 'pdo_mysql'];
```

#### 2. execute() Method (Deprecated in 2.1.0)

The `execute()` method is **deprecated for external usage**. Use specialized methods instead:

```php
// Deprecated
$result = $db->execute('SELECT * FROM users');

// Use select() for SELECT queries
$result = $db->select('SELECT * FROM users');

// Use insert(), update(), delete() for DML
$db->insert($sql);
$db->update($sql);
$db->delete($sql);
```

**Note:** `execute()` is still used internally and available, but should not be called directly in application code.

---

### PHP Version Requirements

| Horde_Db Version | PHP Requirements |
|------------------|------------------|
| **2.x** (lib/) | PHP 5.3 - 8.x |
| **3.0** (lib/ + src/) | PHP 7.4 - 8.x |

Both lib/ and src/ in version 3.0 require **PHP 7.4+**. The minimum version was raised to support:
- Typed properties (optional, used in new code)
- Union types (optional, used in new code)
- Better type system overall

---

### Testing Your Migration

#### 1. Test Database Connections

```php
use Horde\Db\Adapter;

try {
    $db = Adapter::factory('mysqli', $config);
    $db->connect();
    echo "Connection successful\n";
} catch (Horde\Db\DbException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
```

#### 2. Test Query Execution

```php
// Basic query
$result = $db->selectAll('SELECT * FROM users LIMIT 1');
var_dump($result);

// Parameterized query
$result = $db->selectAll(
    'SELECT * FROM users WHERE id = ?',
    [1]
);
var_dump($result);
```

#### 3. Test Transactions

```php
$db->beginDbTransaction();
try {
    $db->insert('INSERT INTO users (name) VALUES (?)', ['Test User']);
    $db->commitDbTransaction();
    echo "Transaction successful\n";
} catch (Exception $e) {
    $db->rollbackDbTransaction();
    echo "Transaction failed: " . $e->getMessage() . "\n";
}
```

#### 4. Test Migrations

```php
use Horde\Db\Migration\Migrator;

$migrator = new Migrator($db, null, [
    'migrationsPath' => __DIR__ . '/migrations'
]);

// Check current version
echo "Current version: " . $migrator->getCurrentVersion() . "\n";

// Run pending migrations
$migrator->migrate();
echo "Migrations complete\n";
```

---

### Troubleshooting

#### Issue: Class Not Found

**Symptom:**
```
Fatal error: Class 'Horde\Db\Adapter' not found
```

**Solution:**
Ensure your composer autoloader is up to date:
```bash
composer dump-autoload
```

#### Issue: Namespace vs Class Name Confusion

**Symptom:**
```
Class 'Horde_Db_Adapter' not found
```

**Solution:**
You're trying to use lib/ class names with src/ code. Either:

1. Use lib/ classes:
   ```php
   use Horde_Db_Adapter;  // No namespace
   ```

2. Or use src/ classes:
   ```php
   use Horde\Db\Adapter;  // Namespaced
   ```

Don't mix the naming conventions.

#### Issue: Charset Errors on MySQL 8.0+

**Symptom:**
```
Unknown character set: 'utf8'
```

**Solution:**
Update to Horde_Db 3.0+ which auto-upgrades `utf8` to `utf8mb4`, or manually change your config:
```php
$config['charset'] = 'utf8mb4';  // 4-byte UTF-8
```

#### Issue: TEXT/BLOB Default Value Errors

**Symptom:**
```
BLOB/TEXT column 'body' can't have a default value
```

**Solution:**
Update to Horde_Db 3.0+ which handles this automatically for MySQL 8.0.13+, or remove the default value:
```php
// Remove 'default' for TEXT/BLOB on MySQL < 8.0.13
$db->addColumn('articles', 'body', 'text');
```

---

### Migration Checklist

- [ ] **Backup your database** before testing
- [ ] Update composer dependencies: `composer update horde/db`
- [ ] Choose migration strategy (stay on lib/, migrate to src/, or incremental)
- [ ] Update namespace imports (if migrating to src/)
- [ ] Update exception class names (if migrating to src/)
- [ ] Update constant references (if migrating to src/)
- [ ] Replace `mysql` adapter with `mysqli` or `pdo_mysql`
- [ ] Test database connections
- [ ] Test query execution
- [ ] Test transactions
- [ ] Test migrations
- [ ] Update documentation
- [ ] Train team on new namespaces (if applicable)

---

## Previous Upgrades

### Upgrading to 2.2.0

#### Horde_Db_Adapter_Base

- **Added:** `updateBlob()` method

  Updates BLOB/CLOB data in an existing row.

  ```php
  $db->updateBlob(
      'documents',
      ['content' => $blobData],
      ['id' => 123]
  );
  ```

#### Horde_Db_Value_Text

- **Added:** `Horde_Db_Value_Text` class

  Represents TEXT/CLOB values for database operations.

  ```php
  use Horde_Db_Value_Text;

  $text = new Horde_Db_Value_Text('Large text content...');
  $db->insert('INSERT INTO articles (body) VALUES (?)', [$text]);
  ```

---

### Upgrading to 2.1.0

#### Horde_Db_Adapter_Base

- **Changed:** Several methods are **abstract** now:
  - `execute()`
  - `select()`
  - `insert()`
  - `beginDbTransaction()`
  - `commitDbTransaction()`
  - `rollbackDbTransaction()`

  **Impact:** Custom adapter implementations must implement these methods.

- **Deprecated:** `execute()` method for external usage

  **Old way:**
  ```php
  $result = $db->execute('SELECT * FROM users');
  ```

  **New way:**
  ```php
  $result = $db->select('SELECT * FROM users');
  ```

  **Note:** `execute()` is still used internally but should not be called directly in application code.

- **Changed:** `select()` method return type

  **Before:** Could return various types depending on adapter
  ```php
  $result = $db->select($sql);  // Returns resource, PDOStatement, etc.
  ```

  **After:** Always returns a `Horde_Db_Adapter_Base_Result` subclass
  ```php
  $result = $db->select($sql);  // Returns Result object
  foreach ($result as $row) {
      // iterate
  }
  ```

  **Migration:** If you were checking result types, update to expect Result objects:
  ```php
  // Before
  if (is_resource($result)) { }

  // After
  if ($result instanceof Horde_Db_Adapter_Base_Result) { }
  ```

- **Changed:** `addIndex()` method return value

  **Before:** Returned nothing
  ```php
  $db->addIndex('users', ['email']);
  ```

  **After:** Returns the index name
  ```php
  $indexName = $db->addIndex('users', ['email']);
  echo "Created index: $indexName";
  ```

- **Added:** New methods
  - `writeCache($key, $data)` - Write data to query cache
  - `readCache($key)` - Read data from query cache
  - `insertBlob($table, $fields, $pk, $idValue)` - Insert BLOB data
  - `column($tableName, $columnName)` - Get column metadata

  **Example:**
  ```php
  // Cache query results
  $db->writeCache('user_count', $count);
  $cachedCount = $db->readCache('user_count');

  // Insert binary data
  $db->insertBlob(
      'documents',
      ['name' => 'report.pdf', 'content' => $pdfData],
      'id'
  );

  // Get column info
  $column = $db->column('users', 'email');
  echo $column->getType();  // 'string'
  ```

---

## Getting Help

- **Mailing List:** dev@lists.horde.org
- **Documentation:** https://www.horde.org/libraries/Horde_Db
- **Bug Tracker:** https://bugs.horde.org/
- **Source Code:** https://github.com/horde/Db

---

## See Also

- **CHANGES** - Detailed changelog with all fixes and features
- **README** - Installation and basic usage
- **API Documentation** - Full API reference

---

**Last Updated:** 2026-04-06
**Document Version:** 3.0.0-alpha8
