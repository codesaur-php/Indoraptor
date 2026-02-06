# Contributing to codesaur/indoraptor

First of all, thank you for taking the time to contribute!
Contributions of any kind are welcome and greatly appreciated.

---

## Ways to Contribute

You can contribute by:

- **Reporting bugs** - Help us identify and fix issues
- **Suggesting new features** - Share your ideas for improvements
- **Improving documentation** - Make the docs clearer and more comprehensive
- **Submitting pull requests** - Contribute code improvements and new features
- **Code review** - Review and provide feedback on pull requests

---

## Getting Started

### Prerequisites

- PHP 8.2.1 or higher
- Composer
- Git
- MySQL, PostgreSQL, or SQLite
- PHP extensions: `ext-gd`, `ext-intl`

### Setup Steps

1. **Fork and clone the repository:**

```bash
git clone https://github.com/codesaur-php/Indoraptor.git
cd Indoraptor
```

2. **Install dependencies:**

```bash
composer install
```

3. **Configure environment:**

If `.env` was not auto-created, copy it manually:

```bash
cp .env.example .env
```

4. **Set up database:**

Configure your database connection in `.env`:

```env
INDO_DB_HOST=localhost
INDO_DB_NAME=indoraptor
INDO_DB_USERNAME=root
INDO_DB_PASSWORD=
```

5. **Access the application:**

Point your web server document root to the `public_html/` directory. The application routes automatically:

- `/dashboard/*` - Dashboard (admin panel)
- `/*` - Web (public website)

---

## Development Workflow

### 1. Create a Branch

Create a new branch for your changes:

```bash
git checkout -b feature/your-feature-name
# or
git checkout -b fix/your-bug-fix
```

### 2. Make Your Changes

- Write clean, readable code
- Follow existing code style and conventions
- Add PHPDoc comments for public methods and classes
- Update documentation if needed

### 3. Commit Your Changes

Use clear and descriptive commit messages following the [Conventional Commits](https://www.conventionalcommits.org/) format:

```bash
git commit -m "feat: add support for custom middleware"
git commit -m "fix: resolve localized data access bug"
git commit -m "docs: update API reference for Pages module"
```

### 4. Push and Create Pull Request

```bash
git push origin feature/your-feature-name
```

Then create a pull request on GitHub.

---

## Coding Guidelines

### Code Style

- Follow **PSR-12** coding standard
- Use meaningful variable and method names
- Keep methods focused and single-purpose
- Add PHPDoc comments for public methods and classes
- Maintain consistency with existing codebase

### Code Structure

- **PSR-7 & PSR-15 compliance** - All code must adhere to PSR standards
- **Middleware pattern** - Follow the middleware pipeline architecture
- **Two-layer architecture** - Dashboard (admin) and Web (public) layers are separate
- **Type hints** - Use strict type declarations where possible

### Naming Conventions

- **Controllers** - `PascalCase` with `Controller` suffix (e.g., `PagesController`)
- **Models** - `PascalCase` with `Model` suffix (e.g., `PagesModel`)
- **Routers** - `PascalCase` with `Router` suffix (e.g., `ContentsRouter`)
- **Middleware** - `PascalCase` with `Middleware` suffix (e.g., `JWTAuthMiddleware`)
- **Templates** - `kebab-case` Twig files (e.g., `index-files.html`)

---

## Pull Request Guidelines

### Before Submitting

- Code follows project style guidelines
- Documentation is updated (if needed)
- Commit messages are clear and descriptive
- Branch is up to date with main branch

### PR Description Template

```markdown
## Description
Brief description of what this PR does.

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Checklist
- [ ] Code follows style guidelines
- [ ] Documentation updated
- [ ] Self-reviewed
```

### PR Rules

- **One logical change per PR** - Keep PRs focused and manageable
- **Clear description** - Explain what and why, not just how
- **Reference issues** - Link to related issues if applicable
- **Small commits** - Break large changes into smaller, logical commits

---

## Commit Message Format

Use [Conventional Commits](https://www.conventionalcommits.org/) format:

### Format

```
<type>(<scope>): <subject>
```

### Types

- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `chore`: Maintenance tasks

### Examples

```bash
feat(pages): add is_featured field for footer pages
fix(news): resolve localized content access pattern
docs(api): update Files module documentation
refactor(auth): simplify login flow
```

---

## Project Structure

Understanding the project structure helps with contributions:

```
Indoraptor/
├── application/
│   ├── raptor/              # Core framework
│   │   ├── authentication/  # Login, JWT, Session
│   │   ├── content/         # CMS (files, news, pages, references, settings)
│   │   ├── localization/    # Languages & translations
│   │   ├── organization/    # Organization management
│   │   ├── rbac/            # Roles & permissions
│   │   ├── user/            # User management
│   │   ├── template/        # Dashboard UI base
│   │   ├── log/             # Logging
│   │   └── mail/            # Email (Brevo + PHPMailer)
│   ├── dashboard/           # Dashboard application
│   └── web/                 # Public website application
├── public_html/             # Document root (index.php, assets/)
├── docs/                    # Documentation (mn/, en/)
├── logs/                    # Error logs
├── private/                 # Protected files
├── .env.example             # Environment template
└── composer.json            # Dependencies
```

---

## Documentation

If your change affects usage or public API:

### Required Updates

- **README.md** - Update examples, usage instructions, or feature list
- **docs/en/api.md** - Update API documentation for new methods or changed behavior
- **docs/mn/api.md** - Mongolian API documentation (if applicable)
- **CHANGELOG.md** - Add entry for notable changes

### Documentation Style

- Use clear, concise language
- Include code examples where helpful
- Keep examples up-to-date and working

---

## Code of Conduct

### Our Standards

- Be respectful and constructive
- Welcome newcomers and help them learn
- Focus on what is best for the community
- Show empathy towards other community members

### Unacceptable Behavior

- Harassment or discriminatory language
- Personal attacks or trolling
- Publishing others' private information
- Other conduct that could reasonably be considered inappropriate

---

## Security Issues

**Please do not open public issues for security vulnerabilities.**

For security-related issues, please follow the instructions in [SECURITY.md](SECURITY.md) or contact the maintainer directly:

- **Email:** codesaur@gmail.com
- **Phone:** [+976 99000287](https://wa.me/97699000287)

---

## Questions?

If you have questions or need help:

- Open an issue with the `question` label
- Check existing documentation ([docs/en/](../docs/en/), [docs/mn/](../docs/mn/))
- Review existing issues and pull requests

---

Thank you for helping improve the codesaur ecosystem!
