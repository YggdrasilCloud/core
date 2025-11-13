# Storage Path Configuration for CI

## Problem

The `PhysicalFolderStorage` service creates physical directories on the filesystem. In CI environments, the `var/` directory doesn't exist by default because it's ignored by git.

## Solution

Configure `FILE_STORAGE_PATH` environment variable and create the directory before running PHP commands.

## GitHub Actions Setup

### 1. Create Storage Directory

Add this step before any PHP commands that might create folders:

```yaml
- name: Prepare storage directory
  run: |
    mkdir -p var/storage
    chmod -R 0777 var
```

### 2. Set Environment Variable

Export `FILE_STORAGE_PATH` for all PHP steps:

```yaml
- name: Run tests
  env:
    APP_ENV: test
    FILE_STORAGE_PATH: ${{ github.workspace }}/var/storage
  run: vendor/bin/phpunit
```

### Complete Example

```yaml
jobs:
  tests:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
      
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
      
      # IMPORTANT: Create storage directory BEFORE running tests
      - name: Prepare storage directory
        run: |
          mkdir -p var/storage
          chmod -R 0777 var
      
      - name: Run database migrations
        env:
          APP_ENV: test
          FILE_STORAGE_PATH: ${{ github.workspace }}/var/storage
        run: php bin/console doctrine:migrations:migrate --no-interaction
      
      - name: Run tests
        env:
          APP_ENV: test
          FILE_STORAGE_PATH: ${{ github.workspace }}/var/storage
        run: vendor/bin/phpunit
```

## Docker Compose (E2E Tests)

For Docker-based E2E tests, the setup is already handled in `docker-compose.yml`:

```yaml
backend:
  environment:
    FILE_STORAGE_PATH: "/app/var/storage"
  command: >
    sh -c "
      mkdir -p /app/var/storage &&
      chown -R www-data:www-data /app/var &&
      chmod -R 0777 /app/var &&
      ...
    "
```

## Local Development

No configuration needed! The service uses the default path `%kernel.project_dir%/var/storage` when `FILE_STORAGE_PATH` is not set.

## Debugging

Check the logs for the configured path:

```bash
# The service logs at initialization:
[debug] PhysicalFolderStorage initialized {"basePath":"/path/to/var/storage"}
```

To see debug logs, set `APP_DEBUG=1` or check `var/log/dev.log`.

## Troubleshooting

### Error: "mkdir(): Permission denied"

**Cause**: Storage directory doesn't exist or has wrong permissions.

**Solution**: 
1. Ensure `mkdir -p var/storage` runs before PHP commands
2. Set correct permissions: `chmod -R 0777 var`
3. Verify `FILE_STORAGE_PATH` points to the created directory

### Error: "Failed to create directory: /some/path"

**Cause**: Parent directory doesn't exist.

**Solution**: Use `mkdir -p` (recursive) instead of `mkdir`:
```bash
mkdir -p var/storage  # Creates var/ and var/storage/
```

### Verify Configuration

Add this debug step to your CI:

```yaml
- name: Debug storage configuration
  env:
    FILE_STORAGE_PATH: ${{ github.workspace }}/var/storage
  run: |
    echo "FILE_STORAGE_PATH: $FILE_STORAGE_PATH"
    ls -la var/ || echo "var/ doesn't exist"
    ls -la var/storage/ || echo "var/storage/ doesn't exist"
    php -r "echo 'Resolved path: ' . (\$_ENV['FILE_STORAGE_PATH'] ?? '%kernel.project_dir%/var/storage') . PHP_EOL;"
```
