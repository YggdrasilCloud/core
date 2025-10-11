# YggdrasilCloud API

REST API for photo management built with Domain-Driven Design (DDD) architecture using Symfony 7.3.

## Features

- 🏗️ **Domain-Driven Design**: Clean architecture with bounded contexts
- 📸 **Photo Management**: Upload, organize, and list photos by folders
- 🔒 **File Validation**: Configurable file size limits and MIME type restrictions
- 🧪 **100% Mutation Coverage**: Comprehensive test suite with Infection
- 🐳 **Docker Ready**: FrankenPHP + PostgreSQL with Docker Compose
- 🚀 **CQRS Pattern**: Separate commands and queries with Symfony Messenger

## Tech Stack

- **Framework**: Symfony 7.3
- **Runtime**: FrankenPHP (PHP 8.3 + Caddy)
- **Database**: PostgreSQL 16
- **Testing**: PHPUnit 12 + Infection
- **Architecture**: DDD with CQRS

## Project Structure

```
src/
└── Photo/                           # Photo bounded context
    ├── Application/
    │   ├── Command/                # Commands (write operations)
    │   │   ├── CreateFolder/
    │   │   └── UploadPhotoToFolder/
    │   └── Query/                  # Queries (read operations)
    │       └── ListPhotosInFolder/
    ├── Domain/
    │   ├── Model/                  # Aggregates and Value Objects
    │   │   ├── Photo.php
    │   │   ├── Folder.php
    │   │   ├── PhotoId.php
    │   │   ├── FileName.php
    │   │   ├── FolderName.php
    │   │   └── StoredFile.php
    │   ├── Event/                  # Domain Events
    │   ├── Repository/             # Repository Interfaces
    │   └── Service/
    │       └── FileValidator.php   # File validation service
    ├── Infrastructure/
    │   ├── Persistence/Doctrine/   # Doctrine ORM implementation
    │   └── Storage/                # File storage implementation
    └── UserInterface/
        └── Http/Controller/        # REST API controllers
```

## Getting Started

### Prerequisites

- Docker and Docker Compose
- Git

### Installation

1. Clone the repository:
```bash
git clone git@github.com:YggdrasilCloud/core.git
cd core
```

2. Start the services:
```bash
docker compose up -d
```

3. Create the database and run migrations:
```bash
docker compose exec php bin/console doctrine:database:create
docker compose exec php bin/console doctrine:migrations:migrate -n
```

4. The API is now available at `http://localhost:8000`

## API Endpoints

### Health Check
```bash
GET /health
GET /health/ready
```

### Folders
```bash
# Create a folder
POST /api/folders
Content-Type: application/json

{
  "name": "Vacances 2025"
}

# Response: 201 Created
{
  "id": "01936ef5-8f6a-7f3e-b9c6-0242ac120002",
  "name": "Vacances 2025",
  "createdAt": "2025-10-11T12:00:00+00:00"
}
```

### Photos
```bash
# Upload a photo to a folder
POST /api/folders/{folderId}/photos
Content-Type: multipart/form-data

file: <binary>

# Response: 201 Created
{
  "id": "01936ef6-a2b4-7890-1234-0242ac120003",
  "fileName": "photo.jpg",
  "mimeType": "image/jpeg",
  "sizeInBytes": 2048576
}

# List photos in a folder
GET /api/folders/{folderId}/photos?page=1&perPage=50

# Response: 200 OK
{
  "items": [
    {
      "id": "01936ef6-a2b4-7890-1234-0242ac120003",
      "fileName": "photo.jpg",
      "storagePath": "/storage/photos/...",
      "mimeType": "image/jpeg",
      "sizeInBytes": 2048576,
      "uploadedAt": "2025-10-11T12:05:00+00:00"
    }
  ],
  "total": 1,
  "page": 1,
  "perPage": 50
}
```

## Configuration

### Environment Variables

```env
# Database
DATABASE_URL="postgresql://app:secret@postgres:5432/app?serverVersion=16&charset=utf8"

# Photo Upload Settings
PHOTO_MAX_FILE_SIZE=20971520                                    # 20MB (-1 = unlimited)
PHOTO_ALLOWED_MIME_TYPES="image/jpeg,image/png,image/gif,image/webp"

# Storage
PHOTO_STORAGE_PATH=/storage/photos
```

## Development

### Run Tests

```bash
# Unit tests
docker compose exec php vendor/bin/phpunit

# Mutation testing (100% MSI required)
docker compose exec php vendor/bin/infection
```

### Code Quality

```bash
# PHP CS Fixer (if configured)
docker compose exec php vendor/bin/php-cs-fixer fix

# PHPStan (if configured)
docker compose exec php vendor/bin/phpstan analyse
```

### Database Migrations

```bash
# Create a new migration
docker compose exec php bin/console make:migration

# Run migrations
docker compose exec php bin/console doctrine:migrations:migrate
```

## Testing

The project has comprehensive test coverage:

- **72 unit tests** covering Value Objects, Aggregates, and Services
- **100% Mutation Score Indicator (MSI)** with Infection
- **114 assertions** ensuring edge cases and boundaries

### Test Structure

```
tests/
└── Unit/
    └── Photo/
        ├── Domain/
        │   ├── Model/
        │   │   ├── PhotoIdTest.php        # UUID v7 generation
        │   │   ├── FileNameTest.php       # Filename validation
        │   │   ├── FolderNameTest.php     # Folder name validation
        │   │   ├── StoredFileTest.php     # File metadata validation
        │   │   ├── PhotoTest.php          # Photo aggregate
        │   │   └── FolderTest.php         # Folder aggregate
        │   └── Service/
        │       └── FileValidatorTest.php  # File validation service
```

### Run Specific Tests

```bash
# Run all tests
docker compose exec php vendor/bin/phpunit

# Run specific test class
docker compose exec php vendor/bin/phpunit tests/Unit/Photo/Domain/Model/PhotoTest.php

# Run with coverage
docker compose exec php vendor/bin/phpunit --coverage-html coverage
```

## Architecture Decisions

### Why Domain-Driven Design?

- **Clear boundaries**: Photo context is isolated and can evolve independently
- **Business logic in domain**: Rules like "photos must be images" are in the domain
- **Testability**: Domain logic is pure PHP, easy to test without framework
- **Flexibility**: Can swap infrastructure (Doctrine → another ORM) without touching domain

### Why CQRS?

- **Separation of concerns**: Commands (write) vs Queries (read)
- **Scalability**: Can optimize read and write models independently
- **Event sourcing ready**: Commands emit domain events for future event store

### Why Value Objects?

- **Type safety**: `PhotoId` instead of raw strings prevents errors
- **Validation**: Business rules enforced at construction (filename max 255 chars)
- **Immutability**: Value objects can't be changed after creation

### Why separate repositories for read/write?

- **CQRS pattern**: Commands use aggregate repositories, queries use read-optimized repositories
- **Performance**: Read models can be denormalized for faster queries
- **Evolution**: Read and write models can evolve separately

## Domain Concepts

### Aggregates

- **Photo**: Represents an uploaded photo with metadata
- **Folder**: Groups photos together

### Value Objects

- **PhotoId / FolderId**: UUID v7 identifiers
- **FileName**: Validated filename (max 255 chars, trims whitespace)
- **FolderName**: Validated folder name (max 255 chars, non-empty)
- **StoredFile**: File metadata (path, MIME type, size)

### Domain Events

- **PhotoUploaded**: Emitted when a photo is uploaded
- **FolderCreated**: Emitted when a folder is created

These events are currently stored in-memory but can be persisted with the Transactional Outbox pattern (see code comments).

## Future Enhancements

### Documented in Code

- **Transactional Outbox Pattern**: Store domain events in database for reliable publishing
- **Duplicate Detection**: Use SHA-256 hash to detect duplicate uploads
- **EXIF Metadata**: Extract and store camera, location, date taken

### Planned Features

- **User Authentication**: JWT-based authentication
- **Authorization**: Role-based access control (RBAC)
- **Photo Sharing**: Share folders with other users
- **Search**: Full-text search on filenames and EXIF data
- **Thumbnails**: Generate multiple sizes for responsive images
- **Albums**: Virtual collections across folders

## Docker Services

### FrankenPHP (php)
- PHP 8.3 with FrankenPHP (Caddy + PHP)
- Exposed on port 8000
- Hot-reload with volume mount

### PostgreSQL (postgres)
- PostgreSQL 16
- Data persisted in `postgres-data` volume
- Exposed on port 5432

## CORS Configuration

For multi-client support (web, mobile), CORS is configured to accept requests from:
- Web frontend (localhost:5173 in dev, production domain)
- Mobile apps (Android, iOS)

## Contributing

### Commit Style

Simple, descriptive commit messages (no conventional commits):
```
Add Photo domain with DDD architecture
Configure Infection for mutation testing
```

### Branch Strategy

- `main`: Stable, production-ready code
- Feature branches: Create from `main`, merge via PR

## License

MIT

## Related Repositories

- **Frontend**: [YggdrasilCloud/frontend](https://github.com/YggdrasilCloud/frontend) - SvelteKit web application
- **Android**: (Coming soon)
