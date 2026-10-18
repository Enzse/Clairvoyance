# Database Migrations

This directory contains SQL migration files for the Clairvoyance KPI Analytics system.

## Migration Files

- `000_create_migration_tracking.sql` - Creates the migration tracking table
- `001_create_users_table.sql` - Creates the users table with roles
- `002_create_dashboards_table.sql` - Creates the dashboards table with JSON layout
- `003_create_dashboard_access_table.sql` - Creates the dashboard access table
- `004_create_dashboard_share_tokens_table.sql` - Creates the share tokens table
- `005_create_kpis_table.sql` - Creates the KPIs table with RAG thresholds
- `006_create_kpi_entries_table.sql` - Creates the KPI entries table for time-series data
- `007_add_indexes_and_constraints.sql` - Adds performance indexes and constraints

## Running Migrations

### Run All Migrations
```bash
php scripts/migrate.php
```

### Run Fresh Migration (Drops all tables first)
```bash
php scripts/migrate.php --fresh
```

### Run Specific Migration
```bash
php scripts/migrate.php --migration=001
```

## Rolling Back Migrations

### Rollback Specific Migration
```bash
php scripts/rollback.php --migration=001
```

### Rollback All Migrations
```bash
php scripts/rollback.php --all
```

## Database Schema

### Users Table
- `id` - Primary key
- `email` - Unique email address
- `password` - Hashed password
- `role` - ENUM('viewer', 'editor', 'admin')
- `created_at` - Timestamp
- `updated_at` - Timestamp

### Dashboards Table
- `id` - Primary key
- `name` - Dashboard name
- `description` - Dashboard description
- `layout` - JSON array of widget configurations
- `user_id` - Foreign key to users table
- `created_at` - Timestamp
- `updated_at` - Timestamp

### Dashboard Access Table
- `dashboard_id` - Foreign key to dashboards table
- `user_id` - Foreign key to users table
- `created_at` - Timestamp

### Dashboard Share Tokens Table
- `id` - Primary key
- `dashboard_id` - Foreign key to dashboards table
- `token` - Unique share token
- `expires_at` - Token expiration datetime
- `created_at` - Timestamp

### KPIs Table
- `id` - Primary key
- `name` - KPI name
- `direction` - ENUM('higher_is_better', 'lower_is_better')
- `target` - Target value
- `rag_red` - Red threshold value
- `rag_amber` - Amber threshold value
- `format_prefix` - Display prefix
- `format_suffix` - Display suffix
- `user_id` - Foreign key to users table
- `created_at` - Timestamp
- `updated_at` - Timestamp

### KPI Entries Table
- `id` - Primary key
- `kpi_id` - Foreign key to KPIs table
- `date` - Entry date
- `value` - KPI value
- `created_at` - Timestamp
- `updated_at` - Timestamp

## Notes

- All tables use InnoDB engine with utf8mb4 charset
- Foreign key constraints are enforced
- Indexes are created for performance optimization
- Timestamps are automatically managed by MySQL
- No default admin is seeded: register an account, then run `php scripts/promote_user.php <email>`
