# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/) and this project adheres to [Semantic Versioning](https://semver.org/).

---

## [11.0.0] - 2026-02-06

Full CMS framework major release. Multi-DB support, DI Container, OpenAI integration, full documentation.

### Added
- `SQLiteConnectMiddleware` - SQLite database support (on top of MySQL, PostgreSQL)
- `ContainerMiddleware` - PSR-11 Dependency Injection Container (`codesaur/container`)
- OpenAI integration - moedit editor AI button (`INDO_OPENAI_API_KEY`)
- Image optimization via GD extension - `INDO_CONTENT_IMG_MAX_WIDTH`, `INDO_CONTENT_IMG_QUALITY`
- `ext-gd`, `ext-intl` PHP extension requirements
- `is_featured` field in Pages module (featured pages for footer)
- File attachments in Web templates (page.html, news.html)
- `basename` Twig filter for Web templates
- Native JS file upload (replaced Plupload external library)
- Native JS image preview (replaced Fancybox external library)
- `INDO_JWT_SECRET` auto-generation via Composer post-install script
- Full MN/EN documentation (`docs/mn/`, `docs/en/`)
- API Reference (`docs/mn/api.md`, `docs/en/api.md`)
- `CHANGELOG.md` version history

### Changed
- `codesaur/http-application` ^5.7 -> **^6.0.0** (breaking)
- `codesaur/dataobject` ^7.1 -> **^9.0.0** (breaking, `localized` access pattern changed)
- `codesaur/template` ^1.6 -> **^3.0.0** (breaking)
- `firebase/php-jwt` >=6.7 -> **^7.0.2** (HS256 key length requirement added)
- `getImportantMenu()` -> `getFeaturedPages()` refactor
- `ForgotModel`, `SignupModel` removed - authentication flow simplified
- `JsonExceptionHandler` removed
- Full PHPDoc added to all PHP files

### Fixed
- `localized` access pattern bugs in dashboard templates (DataObject ^9.0 refactor)
- Web news.html file list incorrect column names
- Web page.html stray character

---

## [10.0.0] - 2025-09-22

Content modules reorganized into subdirectories. Web layer template system improved. Multi-DB support started.

### Added
- `Web\Template\` namespace - Web ExceptionHandler, TemplateController
- `Web\SessionMiddleware`, `Web\LocalizationMiddleware` (separate from Dashboard)
- `PostgresConnectMiddleware` - PostgreSQL support
- `SignupModel` (user signup request model)

### Changed
- `PDOConnectMiddleware` -> `MySQLConnectMiddleware` renamed
- Content modules moved to subdirectories: `file/`, `news/`, `page/`, `reference/`, `settings/`
- Localization module separated: `language/`, `text/` subdirectories
- `UserRequestModel` -> `SignupModel` renamed
- `codesaur/dataobject` ^5.2 -> ^7.1

---

## [9.0.0] - 2024-09-28

**Full architectural overhaul.** Migrated from library to project/application structure. Two-layer (Dashboard + Web) architecture introduced.

### Added
- `application/` new directory structure (`raptor/`, `dashboard/`, `web/`)
- `Raptor\`, `Dashboard\`, `Web\` new namespaces (migrated from `Indoraptor\`)
- `public_html/index.php` entry point - automatic Dashboard/Web routing
- `.env` configuration (`vlucas/phpdotenv`) - all settings in environment file
- Twig template engine support (`codesaur/template`)
- `Raptor\Application` - Dashboard middleware pipeline base
- `Web\Application` - Public website application
- `Dashboard\Application` - Dashboard application
- Brevo (SendInBlue) email API (`getbrevo/brevo-php`)
- `SettingsMiddleware` - system settings middleware
- `DashboardTrait` - Dashboard UI common functions
- `TemplateRouter`, `TemplateController` - Dashboard template system
- `ErrorHandler` - Dashboard template-based error handling
- PSR-3 `Logger` class (built-in)
- `Mailer` class (Brevo + PHPMailer)
- `User` value object (profile, organization, RBAC permissions)
- `psr/log` ^3.0 direct dependency

### Changed
- **`Indoraptor\` -> `Raptor\`** full namespace change
- **`src/` -> `application/raptor/`** directory structure updated
- `IndoApplication` -> `Raptor\Application`
- `IndoController` -> `Raptor\Controller`
- `codesaur/rbac` -> `Raptor\RBAC\` built-in (separated from external package)
- `codesaur/logger` -> `Raptor\Log\Logger` built-in
- `phpmailer/phpmailer` re-added (^6.8)
- `codesaur/dataobject` ^5.2 re-added (was removed in v5-8)

### Removed
- `InternalRequest`, `InternalController` classes removed
- `JsonResponseMiddleware` removed
- `RecordController`, `StatementController` removed
- `codesaur/rbac` external dependency removed (built-in)
- `codesaur/logger` external dependency removed (built-in)

---

## [8.0.0] - 2024-07-30

Simplified to minimal base library. All CMS modules removed, only core classes remain.

### Changed
- Framework simplified to minimal base (9 PHP files)
- `codesaur/rbac` ^2.3 -> ^2.5
- `codesaur/logger` ^1.5 -> ^2.0

### Removed
- Auth, Localization, Contents, File, Mailer modules fully removed
- `CountriesController`, `CountriesModel`
- `LanguageController`, `LanguageModel`
- `TextController`, `TextModel`, `TextInitial`
- `FilesController`, `FilesModel`, `FileModel`, `FilesRouter`
- `NewsModel`, `PagesController`, `PagesModel`
- `ReferenceController`, `ReferenceModel`, `ReferenceInitial`
- `SettingsModel`
- `MailerController`, `MailerModel`, `MailerRouter`
- `LoggerController`, `LoggerModel`, `LoggerRouter`

---

## [5.0.0] - 2023-07-19

CMS modules added. PHP 8.2.1 requirement. File management, news, pages, references, and settings modules.

### Added
- `Contents` module: `NewsModel`, `PagesController`, `PagesModel`, `ReferenceController`, `ReferenceModel`, `SettingsModel`
- `File` module: `FileModel`, `FilesController`, `FilesModel`, `FilesRouter`
- `Mailer` module: `MailerController`, `MailerModel`, `MailerRouter`
- `Record` module: `RecordController`, `RecordRouter`
- `Statement` module: `StatementController`, `StatementRouter`
- `PDOConnectMiddleware` - DB connection middleware
- `JsonResponseMiddleware` - JSON response middleware
- `JsonExceptionHandler` - JSON exception handler
- `InternalRequest` - Internal API request
- `ContentsRouter` - CMS routes
- `codesaur/http-client` dependency added

### Changed
- PHP >=7.2 -> **PHP 8.2.1** requirement
- `Account\` -> `Auth\` namespace changed
- `TranslationController` -> `TextController` renamed
- `firebase/php-jwt` >=5.2 -> >=6.7
- `codesaur/http-application` >=1.2 -> >=5.5.2
- `codesaur/rbac` >=1.4 -> >=2.3.7

### Removed
- `phpmailer/phpmailer` direct dependency removed
- `codesaur/dataobject` direct dependency removed
- `codesaur/localization` direct dependency removed
- `AccountErrorCode` class removed

---

## [1.0] - 2021-04-18

Initial release. REST API-based server framework.

### Features
- PHP >=7.2 support
- `IndoApplication` - PSR-15 Application base
- `IndoController` - Base Controller
- `IndoExceptionHandler` - Exception handler
- JWT authentication (`firebase/php-jwt`)
- Account module: `AuthController`, `AccountController`, `AccountRouter`
- `OrganizationModel`, `OrganizationUserModel`
- `ForgotModel` - Password recovery
- Localization module: `LanguageController`, `CountriesController`, `TranslationController`
- Logger module: `LoggerController`, `LoggerRouter`
- RBAC access control (`codesaur/rbac`)
- PSR-3 logging system (`codesaur/logger`)
- Email sending (`phpmailer/phpmailer`)
- `codesaur/http-application` PSR-7/PSR-15 base
- `codesaur/dataobject` PDO ORM
- MIT License

[11.0.0]: https://github.com/codesaur-php/Indoraptor/compare/v10.0.0...v11.0.0
[10.0.0]: https://github.com/codesaur-php/Indoraptor/compare/v9.0.0...v10.0.0
[9.0.0]: https://github.com/codesaur-php/Indoraptor/compare/v8.0.0...v9.0.0
[8.0.0]: https://github.com/codesaur-php/Indoraptor/compare/v5.0.0...v8.0.0
[5.0.0]: https://github.com/codesaur-php/Indoraptor/compare/v1.0...v5.0.0
[1.0]: https://github.com/codesaur-php/Indoraptor/releases/tag/v1.0
