<!-- Parent: ../AGENTS.md -->
<!-- Generated: 2026-05-10 | Updated: 2026-05-10 -->

# include (Legacy Core PHP)

## Purpose
Legacy core PHP library directory containing utility classes, database helpers, and foundational functions. Much of this code predates the modern `includes/` directory and is being phased out in favor of PSR-compliant components.

## Key Files
| File | Description |
|------|-------------|
| `Loader.php` | Class autoloader using include_once pattern |
| `ChartUtils.php` | Chart/graph generation utilities |
| `utils/` | Common utility functions (CRMEntity.php, InventoryHandler.php) |
| `QueryGenerator/` | SQL query builder for list views |
| `fields/` | Field type definitions and helpers |
| `Webservices/` | API endpoint handlers (Relation.php, Describe.php) |

## Subdirectories
| Directory | Purpose |
|-----------|---------|
| `database/` | Database connection and query utilities |
| `events/` | Event system for module hooks |
| `fields/` | Field type definitions |
| `ListView/` | List view rendering helpers |
| `QueryGenerator/` | Query building classes |
| `simplehtmldom/` | HTML parsing library |
| `utils/` | Core utility functions |
| `Webservices/` | REST/SOAP API endpoints |
| `Zend/` | Zend framework components (Json, Oauth) |

## For AI Agents

### Working In This Directory
- Many files use procedural PHP patterns
- Some functions are deprecated and replaced in `includes/`
- Check `include/deprecated.txt` for deprecated function list

### Legacy Patterns
- Uses `include_once` rather than `require_once`
- Global functions often defined directly in files
- Database queries often inline rather than via query builder

<!-- MANUAL: -->
