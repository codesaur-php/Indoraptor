# 📚 Indoraptor API Reference (EN)

> Detailed reference for all modules, classes, and methods.

---

## Table of Contents

1. [Raptor\Controller](#raptorcontroller)
2. [Raptor\Application](#raptorapplication)
3. [Authentication](#authentication)
4. [User](#user)
5. [Organization](#organization)
6. [RBAC](#rbac)
7. [Content - Files](#content--files)
8. [Content - News](#content--news)
9. [Content - Pages](#content--pages)
10. [Content - References](#content--references)
11. [Content - Settings](#content--settings)
12. [Localization](#localization)
13. [Log](#log)
14. [Mail](#mail)
15. [Database Middleware](#database-middleware)
16. [Web Layer](#web-layer)

---

## Raptor\Controller

**File:** `application/raptor/Controller.php`
**Extends:** `codesaur\Http\Application\Controller`
**Uses:** `codesaur\DataObject\PDOTrait`

Base class for all controllers.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `$pdo` | `\PDO` | Database connection (via PDOTrait) |

### Methods

#### `__construct(ServerRequestInterface $request)`
Extracts PDO instance from request and assigns to `$this->pdo`.

#### `getUser(): ?User`
Returns the authenticated `User` object, or `null` if not logged in.

#### `getUserId(): ?int`
Returns the authenticated user's ID, or `null` if not logged in.

#### `isUserAuthorized(): bool`
Returns whether a user is authenticated.

#### `isUser(string $role): bool`
Checks if the user has a specific RBAC role.

#### `isUserCan(string $permission): bool`
Checks if the user has a specific RBAC permission.

#### `getLanguageCode(): string`
Returns the active language code (`'mn'`, `'en'`, etc.). Returns `''` if not set.

#### `getLanguages(): array`
Returns all registered languages.

#### `text(string $key, mixed $default = null): string`
Returns translation text. Returns `$default` or `{key}` if not found.

#### `twigTemplate(string $template, array $vars = []): TwigTemplate`
Creates a Twig template with auto-injected variables: `user`, `index`, `localization`, `request`. Registers `text` and `link` filters.

#### `respondJSON(array $response, int|string $code = 0): void`
Outputs a JSON response with `Content-Type: application/json` header.

#### `redirectTo(string $routeName, array $params = []): void`
Redirects to a named route (302). Calls `exit`.

#### `indolog(string $table, string $level, string $message, array $context = []): void`
Writes a system log entry. Server request metadata and user info are auto-appended.

#### `generateRouteLink(string $routeName, array $params = [], bool $is_absolute = false, string $default = '#'): string`
Generates a URL from a route name.

#### `getContainer(): ?ContainerInterface`
Returns the DI Container.

#### `getService(string $id): mixed`
Gets a service from the container.

#### `errorLog(\Throwable $e): void`
Writes error to `error_log()` in development mode only.

#### `headerResponseCode(int|string $code): void`
Sets HTTP response code. Ignores non-standard codes.

#### `getScriptPath(): string`
Returns the script path (subdirectory support).

#### `getDocumentRoot(): string`
Returns the document root path.

---

## Raptor\Application

**File:** `application/raptor/Application.php`
**Extends:** `codesaur\Http\Application\Application`

Base for the Dashboard Application. Registers the middleware pipeline and routers.

### Constructor Pipeline

1. `ErrorHandler` - Error handling
2. `MySQLConnectMiddleware` - DB connection
3. `SessionMiddleware` - Session management
4. `JWTAuthMiddleware` - JWT authentication
5. `ContainerMiddleware` - DI Container
6. `LocalizationMiddleware` - Multi-language
7. `SettingsMiddleware` - System settings
8. `LoginRouter`, `UsersRouter`, `OrganizationRouter`, `RBACRouter`, `LocalizationRouter`, `ContentsRouter`, `LogsRouter`, `TemplateRouter`

---

## Authentication

### JWTAuthMiddleware

**File:** `application/raptor/authentication/JWTAuthMiddleware.php`
**Implements:** `MiddlewareInterface`

#### `generate(array $data): string`
Generates a JWT token. Payload includes `iat`, `exp`, `seconds` + `$data`.

#### `validate(string $jwt): array`
Decodes and validates JWT. Throws `RuntimeException` if expired. Requires `user_id` and `organization_id`.

#### `process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface`
1. Reads `$_SESSION['RAPTOR_JWT']`
2. Validates JWT
3. Fetches user profile from DB
4. Verifies organization membership
5. Loads RBAC permissions
6. Creates `User` object and adds to request attributes
7. On failure, redirects to `/dashboard/login`

### SessionMiddleware (Dashboard)

**File:** `application/raptor/authentication/SessionMiddleware.php`
**Implements:** `MiddlewareInterface`

Starts PHP session (`session_start()`).

### LoginRouter

**File:** `application/raptor/authentication/LoginRouter.php`

| Route | Method | Name | Description |
|-------|--------|------|-------------|
| `/dashboard/login` | GET | `login` | Login page |
| `/dashboard/login/try` | POST | `entry` | Login attempt |
| `/dashboard/login/logout` | GET | `logout` | Logout |
| `/dashboard/login/forgot` | POST | `login-forgot` | Forgot password |
| `/dashboard/login/signup` | POST | `signup` | Sign up |
| `/dashboard/login/language/{code}` | GET | `language` | Switch language |
| `/dashboard/login/set/password` | POST | `login-set-password` | Set new password |
| `/dashboard/login/organization/{uint:id}` | GET | `login-select-organization` | Select organization |

### User (Value Object)

**File:** `application/raptor/authentication/User.php`

| Property | Type | Description |
|----------|------|-------------|
| `$profile` | `array` | User profile data |
| `$organization` | `array` | Organization data |
| `$permissions` | `array` | RBAC permissions |

| Method | Description |
|--------|-------------|
| `is(string $role): bool` | Check role |
| `can(string $permission): bool` | Check permission |

---

## User

### UsersModel

**File:** `application/raptor/user/UsersModel.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `indo_users`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `username` | varchar(50) | Login name |
| `email` | varchar(100) | Email address |
| `password` | varchar(255) | Bcrypt hash |
| `phone` | varchar(50) | Phone |
| `first_name` | varchar(50) | First name |
| `last_name` | varchar(50) | Last name |
| `photo` | varchar(255) | Avatar image |
| `is_active` | tinyint | Active status |
| `created_at` | datetime | Created date |
| `updated_at` | datetime | Updated date |

---

## Organization

### OrganizationModel

**File:** `application/raptor/organization/OrganizationModel.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `indo_organization`

### OrganizationUserModel

**File:** `application/raptor/organization/OrganizationUserModel.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `indo_organization_users`

User-organization relationship table.

---

## RBAC

### RBAC

**File:** `application/raptor/rbac/RBAC.php`

Loads all roles and permissions for a user and returns them via `jsonSerialize()`.

### Roles

**File:** `application/raptor/rbac/Roles.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `indo_rbac_roles`

### Permissions

**File:** `application/raptor/rbac/Permissions.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `indo_rbac_permissions`

### RolePermissions

**File:** `application/raptor/rbac/RolePermissions.php`

Role-Permission relationships.

### UserRole

**File:** `application/raptor/rbac/UserRole.php`

User-Role relationships.

---

## Content - Files

### FilesModel

**File:** `application/raptor/content/file/FilesModel.php`
**Extends:** `codesaur\DataObject\Model`

Stores file metadata. Table name is dynamic (`setTable()`).

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `record_id` | bigint | Related record ID |
| `file` | varchar(255) | Original filename |
| `path` | varchar(255) | Stored path |
| `size` | bigint | File size (bytes) |
| `type` | varchar(50) | File type (image, video, document...) |
| `mime_content_type` | varchar(100) | MIME type |
| `keyword` | varchar(255) | Keyword |
| `description` | text | Description |
| `is_active` | tinyint | Active status |
| `created_at` | datetime | Created date |
| `created_by` | bigint | Created by user |
| `updated_at` | datetime | Updated date |
| `updated_by` | bigint | Updated by user |

### FilesController

**File:** `application/raptor/content/file/FilesController.php`

| Method | Description |
|--------|-------------|
| `index()` | File management page |
| `list(string $table)` | JSON file list |
| `upload()` | Upload file (move only, no DB record) |
| `post(string $table)` | Upload + register in DB |
| `modal(string $table)` | File selection modal |
| `update(string $table, int $id)` | Update file metadata |
| `deactivate(string $table)` | Soft delete |

### ContentsRouter - File Routes

| Route | Method | Name |
|-------|--------|------|
| `/dashboard/files` | GET | `files` |
| `/dashboard/files/list/{table}` | GET | `files-list` |
| `/dashboard/files/upload` | POST | `files-upload` |
| `/dashboard/files/post/{table}` | POST | `files-post` |
| `/dashboard/files/modal/{table}` | GET | `files-modal` |
| `/dashboard/files/{table}/{uint:id}` | PUT | `files-update` |
| `/dashboard/files/{table}/deactivate` | DELETE | `files-deactivate` |
| `/dashboard/private/file` | GET | `private-files-read` |

---

## Content - News

### NewsModel

**File:** `application/raptor/content/news/NewsModel.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `indo_news`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `code` | varchar(6) | Language code |
| `title` | varchar(255) | Title |
| `content` | longtext | HTML content |
| `photo` | varchar(255) | Cover image |
| `published` | tinyint | Published status |
| `published_at` | datetime | Published date |
| `read_count` | int | View count |
| `is_active` | tinyint | Active status |
| `created_at` | datetime | Created date |
| `created_by` | bigint | Created by user |
| `updated_at` | datetime | Updated date |
| `updated_by` | bigint | Updated by user |

### ContentsRouter - News Routes

| Route | Method | Name |
|-------|--------|------|
| `/dashboard/news` | GET | `news` |
| `/dashboard/news/list` | GET | `news-list` |
| `/dashboard/news/insert` | GET+POST | `news-insert` |
| `/dashboard/news/{uint:id}` | GET+PUT | `news-update` |
| `/dashboard/news/read/{uint:id}` | GET | `news-read` |
| `/dashboard/news/view/{uint:id}` | GET | `news-view` |
| `/dashboard/news/deactivate` | DELETE | `news-deactivate` |

---

## Content - Pages

### PagesModel

**File:** `application/raptor/content/page/PagesModel.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `indo_pages`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `parent_id` | bigint | Parent page ID |
| `code` | varchar(6) | Language code |
| `type` | varchar(50) | Page type |
| `title` | varchar(255) | Title |
| `content` | longtext | HTML content |
| `photo` | varchar(255) | Cover image |
| `link` | varchar(255) | External link |
| `position` | int | Sort order |
| `published` | tinyint | Published status |
| `published_at` | datetime | Published date |
| `is_featured` | tinyint | Featured page |
| `read_count` | int | View count |
| `is_active` | tinyint | Active status |
| `created_at` | datetime | Created date |
| `created_by` | bigint | Created by user |
| `updated_at` | datetime | Updated date |
| `updated_by` | bigint | Updated by user |

#### `generateSlug(string $title): string`
Generates an SEO-friendly slug from a title.

#### `getBySlug(string $slug): array`
Finds a page by its slug.

#### `getExcerpt(string $content, int $length = 150): string`
Extracts a plain-text excerpt from HTML content.

### ContentsRouter - Page Routes

| Route | Method | Name |
|-------|--------|------|
| `/dashboard/pages` | GET | `pages` |
| `/dashboard/pages/list` | GET | `pages-list` |
| `/dashboard/pages/insert` | GET+POST | `page-insert` |
| `/dashboard/pages/{uint:id}` | GET+PUT | `page-update` |
| `/dashboard/pages/read/{uint:id}` | GET | `page-read` |
| `/dashboard/pages/view/{uint:id}` | GET | `page-view` |
| `/dashboard/pages/deactivate` | DELETE | `page-deactivate` |

---

## Content - References

### ReferencesModel

**File:** `application/raptor/content/reference/ReferencesModel.php`
**Extends:** `codesaur\DataObject\LocalizedModel`

Reference table with dynamic table name.

### ContentsRouter - Reference Routes

| Route | Method | Name |
|-------|--------|------|
| `/dashboard/references` | GET | `references` |
| `/dashboard/references/{table}` | GET+POST | `reference-insert` |
| `/dashboard/references/{table}/{uint:id}` | GET+PUT | `reference-update` |
| `/dashboard/references/view/{table}/{uint:id}` | GET | `reference-view` |
| `/dashboard/references/deactivate` | DELETE | `reference-deactivate` |

---

## Content - Settings

### SettingsModel

**File:** `application/raptor/content/settings/SettingsModel.php`
**Extends:** `codesaur\DataObject\LocalizedModel`

**Table:** `raptor_settings`

#### Primary Columns

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `email` | varchar(70) | Contact email |
| `phone` | varchar(70) | Contact phone |
| `favico` | varchar(255) | Favicon path |
| `apple_touch_icon` | varchar(255) | Apple icon path |
| `config` | text | JSON config |
| `is_active` | tinyint | Active status |

#### Content Columns (per language)

| Column | Type | Description |
|--------|------|-------------|
| `title` | varchar(70) | Site title |
| `logo` | varchar(255) | Logo |
| `description` | varchar(255) | SEO description |
| `urgent` | text | Urgent message |
| `contact` | text | Contact info |
| `address` | text | Address |
| `copyright` | varchar(255) | Copyright |

#### `retrieve(): array`
Gets the active (`is_active=1`) settings record. Returns `[]` if empty.

### SettingsMiddleware

**File:** `application/raptor/content/settings/SettingsMiddleware.php`
**Implements:** `MiddlewareInterface`

Reads settings from DB and injects into request attributes as `settings`.

### ContentsRouter - Settings Routes

| Route | Method | Name |
|-------|--------|------|
| `/dashboard/settings` | GET | `settings` |
| `/dashboard/settings` | POST | - |
| `/dashboard/settings/files` | POST | `settings-files` |

---

## Localization

### LanguageModel

**File:** `application/raptor/localization/language/LanguageModel.php`
**Extends:** `codesaur\DataObject\Model`

Language registration table.

### TextModel

**File:** `application/raptor/localization/text/TextModel.php`
**Extends:** `codesaur\DataObject\Model`

Translation texts (key → value).

#### `retrieve(array $languageCodes): array`
Returns all translations structured as language code → key → value.

### LocalizationMiddleware

**File:** `application/raptor/localization/LocalizationMiddleware.php`
**Implements:** `MiddlewareInterface`

Injects `localization` array into request attributes:

```php
[
    'code'     => 'mn',           // Active language code
    'language' => [...],          // All languages list
    'text'     => ['key' => 'value', ...]  // Translation texts
]
```

---

## Log

### Logger

**File:** `application/raptor/log/Logger.php`
**Extends:** `\Psr\Log\AbstractLogger`

PSR-3 standard logging system. Stores logs in database.

#### `setTable(string $table): void`
Sets the log table name.

#### `log(mixed $level, string|\Stringable $message, array $context = []): void`
Writes a log entry.

### LogsRouter Routes

| Route | Method | Name |
|-------|--------|------|
| `/dashboard/logs` | GET | `logs` |
| `/dashboard/logs/{table}` | GET | `logs-list` |
| `/dashboard/logs/view/{table}/{uint:id}` | GET | `log-view` |

---

## Mail

### Mailer

**File:** `application/raptor/mail/Mailer.php`

Sends email via Brevo API with PHPMailer fallback.

---

## Database Middleware

### MySQLConnectMiddleware

**File:** `application/raptor/MySQLConnectMiddleware.php`

1. Reads DB config from ENV
2. Creates PDO connection to MySQL
3. Auto-creates database on localhost
4. Sets charset/collation
5. Injects `pdo` into request attributes

### PostgresConnectMiddleware

**File:** `application/raptor/PostgresConnectMiddleware.php`

PostgreSQL variant. DSN: `pgsql:host=...;dbname=...`

### SQLiteConnectMiddleware

**File:** `application/raptor/SQLiteConnectMiddleware.php`

SQLite variant. DB file: `private/database.sqlite`

### ContainerMiddleware

**File:** `application/raptor/ContainerMiddleware.php`

Injects PSR-11 DI Container into request. Registers PDO and User ID in the container.

---

## Web Layer

### Web\Application

**File:** `application/web/Application.php`
**Extends:** `codesaur\Http\Application\Application`

Public website Application. Middleware pipeline:
ExceptionHandler → MySQL → Container → Session → Localization → Settings → HomeRouter

### HomeRouter

**File:** `application/web/home/HomeRouter.php`

| Route | Method | Name | Description |
|-------|--------|------|-------------|
| `/` | GET | `home` | Home page |
| `/home` | GET | - | Home alias |
| `/language/{code}` | GET | `language` | Switch language |
| `/page/{uint:id}` | GET | `page` | View page |
| `/news/{uint:id}` | GET | `news` | View news |
| `/contact` | GET | `contact` | Contact page |

### HomeController

**File:** `application/web/home/HomeController.php`
**Extends:** `TemplateController`

| Method | Description |
|--------|-------------|
| `index()` | Home page (latest 20 news) |
| `page(int $id)` | Display page + files + read_count |
| `news(int $id)` | Display news + files + read_count |
| `contact()` | Contact page (link LIKE '%/contact') |
| `language(string $code)` | Switch language + redirect |

### TemplateController

**File:** `application/web/template/TemplateController.php`
**Extends:** `Raptor\Controller`

| Method | Description |
|--------|-------------|
| `template(string $template, array $vars): TwigTemplate` | Merges web layout + content |
| `getMainMenu(string $code): array` | Builds multi-level main menu |
| `getFeaturedPages(string $code): array` | Gets featured pages list |

### Moedit AI

**Route:** `POST /dashboard/content/moedit/ai`
**Name:** `moedit-ai`

OpenAI API proxy for the moedit editor's AI button.

---

## ContentsRouter - All Routes

**File:** `application/raptor/content/ContentsRouter.php`

Central router that registers all content module routes: Files, News, Pages, References, Settings, Moedit AI.
