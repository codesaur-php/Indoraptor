# 🦖 Indoraptor Framework - Бүрэн танилцуулга

[![PHP Version](https://img.shields.io/badge/php-%5E8.2.1-777BB4.svg?logo=php)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](../../LICENSE)

> **codesaur/indoraptor** - PSR стандартууд дээр суурилсан, олон давхаргат архитектуртай PHP CMS фреймворк.

---

## Агуулга

1. [Танилцуулга](#1-танилцуулга)
2. [Суулгах](#2-суулгах)
3. [Тохиргоо (.env)](#3-тохиргоо)
4. [Архитектур](#4-архитектур)
5. [Middleware pipeline](#5-middleware-pipeline)
6. [Модулиуд](#6-модулиуд)
7. [Twig Template систем](#7-twig-template-систем)
8. [Routing](#8-routing)
9. [Controller](#9-controller)
10. [Model](#10-model)
11. [Хэрэглээний жишээ](#11-хэрэглээний-жишээ)

---

## 1. Танилцуулга

`codesaur/indoraptor` нь **Web** (нийтийн сайт) болон **Dashboard** (админ панель) гэсэн хоёр давхаргат бүтэцтэй, PSR-7/PSR-15 middleware суурьтай PHP фреймворк юм.

### Гол боломжууд

- ✔ **PSR-7/PSR-15** middleware суурьтай архитектур
- ✔ **JWT + Session** нэвтрэлт баталгаажуулалт
- ✔ **RBAC** (Role-Based Access Control) эрхийн удирдлага
- ✔ **Олон хэл** дэмжлэг (Localization)
- ✔ CMS модулиуд: Мэдээ, Хуудас, Файл, Лавлах, Тохиргоо
- ✔ MySQL / PostgreSQL / SQLite дэмжлэг
- ✔ **Twig** template engine
- ✔ **OpenAI** интеграци (moedit editor)
- ✔ Зураг optimize хийх (GD)
- ✔ PSR-3 лог систем
- ✔ **Brevo** API и-мэйл илгээх

### codesaur экосистем

Indoraptor нь дараах codesaur packages-тэй хамтран ажиллана:

| Package | Зориулалт |
|---------|-----------|
| `codesaur/http-application` | PSR-15 Application, Router, Middleware суурь |
| `codesaur/dataobject` | PDO суурьтай ORM (Model, LocalizedModel) |
| `codesaur/template` | Twig template engine wrapper |
| `codesaur/http-client` | HTTP client (OpenAI API дуудлага) |
| `codesaur/container` | PSR-11 Dependency Injection Container |

---

## 2. Суулгах

### Шаардлага

- PHP **8.2.1+**
- Composer
- MySQL / PostgreSQL / SQLite
- PHP extensions: `ext-gd`, `ext-intl`

### Composer ашиглан суулгах

```bash
composer create-project codesaur/indoraptor my-project
```

Composer-ийн `post-root-package-install` скрипт нь:
1. `.env.example` файлыг `.env` руу автоматаар хуулна (байхгүй бол)
2. `INDO_JWT_SECRET` нууц түлхүүрийг автоматаар үүсгэнэ

> Хэрэв `.env` файл үүсээгүй бол `cp .env.example .env` командаар гараар хуулж, `INDO_JWT_SECRET` утгыг өөрөө тохируулна.

### Гараар суулгах

```bash
git clone https://github.com/codesaur-php/indoraptor.git my-project
cd my-project
composer install
cp .env.example .env
```

---

## 3. Тохиргоо

`.env` файлын бүх тохиргоонуудын тайлбар:

### Орчин ба Апп

```env
# Орчны горим: development эсвэл production
CODESAUR_APP_ENV=development

# Аппликейшний нэр
CODESAUR_APP_NAME=indoraptor

# Цагийн бүс (заавал биш)
#CODESAUR_APP_TIME_ZONE=Asia/Ulaanbaatar
```

- `development` горимд алдааг дэлгэцэн дээр харуулахын зэрэгцээ `logs/code.log` файлд бичнэ
- `production` горимд зөвхөн `logs/code.log` файлд бичнэ

### Өгөгдлийн сан

```env
INDO_DB_HOST=localhost
INDO_DB_NAME=indoraptor
INDO_DB_USERNAME=root
INDO_DB_PASSWORD=
INDO_DB_CHARSET=utf8mb4
INDO_DB_COLLATION=utf8mb4_unicode_ci
INDO_DB_PERSISTENT=false
```

- Localhost (127.0.0.1) дээр ажиллаж байвал database автоматаар үүсгэнэ
- `INDO_DB_PERSISTENT=true` байвал PDO persistent холболт ашиглана

### JWT (JSON Web Token)

```env
INDO_JWT_ALGORITHM=HS256
INDO_JWT_LIFETIME=2592000
INDO_JWT_SECRET=auto-generated
#INDO_JWT_LEEWAY=10
```

- `INDO_JWT_SECRET` - Composer-ийн скриптээр автоматаар 128 тэмдэгт (64 байт hex) үүсгэнэ
- `INDO_JWT_LIFETIME` - Токений хүчинтэй хугацаа секундээр (2592000 = 30 хоног)
- `INDO_JWT_LEEWAY` - Серверийн цагийн зөрөөг зөвшөөрөх хугацаа

### И-мэйл

```env
INDO_MAIL_FROM=noreply@codesaur.domain
#INDO_MAIL_FROM_NAME="Indoraptor Notification"
#INDO_MAIL_BREVO_APIKEY=""
#INDO_MAIL_REPLY_TO=
```

- Brevo (SendInBlue) API ашиглан и-мэйл илгээнэ

### OpenAI

```env
#INDO_OPENAI_API_KEY=sk-your-api-key-here
```

- moedit editor-ийн AI товчинд ашиглагдана

### Зургийн optimize

```env
INDO_CONTENT_IMG_MAX_WIDTH=1920
INDO_CONTENT_IMG_QUALITY=90
```

- CMS-д зураг upload хийхэд GD extension ашиглан optimize хийнэ

### Серверийн тохиргоо

Apache болон Nginx серверийн жишээ тохиргоонууд [`docs/conf.example/`](../conf.example/) хавтаст байна:

| Файл | Тайлбар |
|------|---------|
| `.env.example` | Орчны тохиргооны лавлагаа |
| `.htaccess.example` | Apache URL rewrite болон HTTPS redirect |
| `.nginx.conf.example` | Nginx серверийн блок (HTTP, HTTPS, PHP-FPM) |

---

## 4. Архитектур

### Хоёр давхаргат бүтэц

```
public_html/index.php (Entry point)
│
├── /dashboard/* → Dashboard\Application (Админ панель)
│    ├── Middleware: ErrorHandler → MySQL → Session → JWT → Container → Localization → Settings
│    ├── Routers: Login, Users, Organization, RBAC, Localization, Contents, Logs, Template
│    └── Controllers → Twig Templates → HTML Response
│
└── /* → Web\Application (Нийтийн вэб сайт)
     ├── Middleware: ExceptionHandler → MySQL → Container → Session → Localization → Settings
     ├── Router: HomeRouter (/, /page/{id}, /news/{id}, /contact, /language/{code})
     └── TemplateController → Twig Templates → HTML Response
```

### Request-ийн дамжих урсгал

```
Browser → index.php → .env → ServerRequest
  → Application сонгох (URL path-аар)
    → Middleware chain (дарааллаар)
      → Router match
        → Controller::action()
          → Model (DB)
          → TwigTemplate → render()
            → HTML Response → Browser
```

### Директорийн бүтэц

```
indoraptor/
├── application/
│   ├── raptor/                    # Суурь framework (Dashboard + shared)
│   │   ├── Application.php        # Dashboard Application суурь
│   │   ├── Controller.php         # Бүх Controller-ийн суурь анги
│   │   ├── MySQLConnectMiddleware.php
│   │   ├── PostgresConnectMiddleware.php
│   │   ├── SQLiteConnectMiddleware.php
│   │   ├── ContainerMiddleware.php
│   │   ├── authentication/        # Login, JWT, Session
│   │   ├── content/               # CMS модулиуд
│   │   │   ├── file/              # Файлын менежмент
│   │   │   ├── news/              # Мэдээ
│   │   │   ├── page/              # Хуудас
│   │   │   ├── reference/         # Лавлагаа
│   │   │   └── settings/          # Системийн тохиргоо
│   │   ├── localization/          # Хэл, орчуулга
│   │   ├── organization/          # Байгууллага
│   │   ├── rbac/                  # Эрхийн удирдлага
│   │   ├── user/                  # Хэрэглэгч
│   │   ├── template/              # Dashboard UI template
│   │   ├── log/                   # PSR-3 лог
│   │   ├── mail/                  # И-мэйл
│   │   └── exception/             # Алдаа барих
│   ├── dashboard/                 # Dashboard Application
│   │   ├── Application.php
│   │   └── home/                  # Dashboard Home Router
│   └── web/                       # Web Application
│       ├── Application.php
│       ├── SessionMiddleware.php
│       ├── LocalizationMiddleware.php
│       ├── home/                  # Public page controllers + templates
│       │   ├── HomeRouter.php
│       │   ├── HomeController.php
│       │   ├── home.html
│       │   ├── page.html
│       │   └── news.html
│       └── template/              # Web layout
│           ├── TemplateController.php
│           ├── ExceptionHandler.php
│           └── index.html
├── public_html/
│   ├── index.php                  # Entry point
│   ├── .htaccess                  # Apache URL rewrite
│   └── assets/                    # CSS, JS (dashboard, moedit, motable)
├── docs/
│   ├── conf.example/              # Серверийн тохиргооны жишээ
│   │   ├── .env.example           # Орчны тохиргоо
│   │   ├── .htaccess.example      # Apache rewrite дүрмүүд
│   │   └── .nginx.conf.example    # Nginx серверийн тохиргоо
│   ├── en/                        # Англи баримтжуулалт
│   └── mn/                        # Монгол баримтжуулалт
├── logs/                          # Алдааны лог файлууд
├── private/                       # Хамгаалагдсан файлууд
├── composer.json
└── LICENSE
```

---

## 5. Middleware Pipeline

Middleware бол PSR-15 стандартын дагуу request/response-г боловсруулах давхаргууд юм. Бүртгэгдсэн дараалал чухал!

### Dashboard Middleware

| # | Middleware | Зориулалт |
|---|-----------|-----------|
| 1 | `ErrorHandler` | Алдааг JSON/HTML хэлбэрээр хариулна |
| 2 | `MySQLConnectMiddleware` | PDO холболт үүсгэж request-д inject хийнэ |
| 3 | `SessionMiddleware` | PHP session эхлүүлж удирдна |
| 4 | `JWTAuthMiddleware` | JWT шалгаж `User` объект үүсгэнэ |
| 5 | `ContainerMiddleware` | DI Container-г inject хийнэ |
| 6 | `LocalizationMiddleware` | Хэл, орчуулгыг тодорхойлно |
| 7 | `SettingsMiddleware` | Системийн тохиргоог inject хийнэ |

### Web Middleware

| # | Middleware | Зориулалт |
|---|-----------|-----------|
| 1 | `ExceptionHandler` | Template ашиглан алдааны хуудас рендерлэнэ |
| 2 | `MySQLConnectMiddleware` | PDO холболт |
| 3 | `ContainerMiddleware` | DI Container |
| 4 | `SessionMiddleware` | Session (хэл хадгалах) |
| 5 | `LocalizationMiddleware` | Олон хэл |
| 6 | `SettingsMiddleware` | Тохиргоо (logo, title, footer) |

### Database Middleware сонголт

Зөвхөн **нэг** database middleware ашиглана:

```php
// MySQL (default)
$this->use(new \Raptor\MySQLConnectMiddleware());

// PostgreSQL
$this->use(new \Raptor\PostgresConnectMiddleware());

// SQLite
$this->use(new \Raptor\SQLiteConnectMiddleware());
```

---

## 6. Модулиуд

### 6.1 Authentication (Нэвтрэлт)

**Классууд:** `LoginRouter`, `LoginController`, `JWTAuthMiddleware`, `SessionMiddleware`, `User`

- JWT + Session хосолсон authentication
- Login / Logout / Forgot password / Signup
- Байгууллага сонгох (олон байгууллагатай хэрэглэгч)
- JWT нь `$_SESSION['RAPTOR_JWT']` дотор хадгалагдана
- `User` объект нь profile, organization, RBAC permissions агуулна

### 6.2 User (Хэрэглэгч)

**Классууд:** `UsersRouter`, `UsersController`, `UsersModel`

- Хэрэглэгчийн CRUD (Create, Read, Update, Deactivate)
- Нууц үг bcrypt hash ашиглан хадгална
- Profile мэдээлэл: username, email, phone, first_name, last_name
- Avatar зураг upload

### 6.3 Organization (Байгууллага)

**Классууд:** `OrganizationRouter`, `OrganizationController`, `OrganizationModel`, `OrganizationUserModel`

- Байгууллагын CRUD
- Хэрэглэгч-байгууллагын холбоос удирдлага
- Нэг хэрэглэгч олон байгууллагад харьяалагдах боломжтой

### 6.4 RBAC (Эрхийн удирдлага)

**Классууд:** `RBACRouter`, `RBACController`, `RBAC`, `Roles`, `Permissions`, `RolePermissions`, `UserRole`

- Role (дүр) үүсгэх, удирдах
- Permission (эрх) үүсгэх, удирдах
- Role-Permission хамаарал
- User-Role оноох
- Controller дотроос эрх шалгах:

```php
// Хэрэглэгч system байгууллага дээр "admin" дүртэй эсэх
$this->isUser('system_admin');

// Хэрэглэгч "news_edit" эрхтэй эсэх
$this->isUserCan('news_edit');
```

### 6.5 Content - Files (Файл)

**Классууд:** `FilesController`, `FilesModel`, `PrivateFilesController`

- Файл upload (native JS, FormData)
- Зураг optimize хийх (GD)
- Файлыг модуль/хүснэгтээр ангилах
- MIME type тодорхойлох
- Private файл (зөвхөн нэвтэрсэн хэрэглэгчдэд)

### 6.6 Content - News (Мэдээ)

**Классууд:** `NewsController`, `NewsModel`

- Мэдээний CRUD
- Нүүр зураг upload
- Хавсралт файлууд
- Нийтлэх огноо удирдах
- Үзэлтийн тоо (read_count)
- moedit editor ашиглан контент засварлах

### 6.7 Content - Pages (Хуудас)

**Классууд:** `PagesController`, `PagesModel`

- Хуудасны CRUD
- Parent-child бүтэц (олон түвшний меню)
- `position` талбараар эрэмбэлэх
- `type` талбар: ердийн хуудас, `special-page`
- `is_featured` талбар: Footer-д онцлох холбоос болгох
- `link` талбар: Гадаад URL холбоос
- SEO slug үүсгэх (`generateSlug`)
- Файл хавсаргах

### 6.8 Content - References (Лавлагаа)

**Классууд:** `ReferencesController`, `ReferencesModel`

- Лавлагааны хүснэгтүүд (key-value хэлбэрийн)
- Олон хэлтэй (LocalizedModel)
- Динамик хүснэгтийн нэр

### 6.9 Content - Settings (Тохиргоо)

**Классууд:** `SettingsController`, `SettingsModel`, `SettingsMiddleware`

- Системийн ерөнхий тохиргоо (олон хэлтэй)
- Сайтын гарчиг, лого, тайлбар
- Favicon, Apple Touch Icon
- Холбоо барих мэдээлэл (утас, имэйл, хаяг)
- Footer мэдээлэл (copyright, социал холбоосууд)
- `SettingsMiddleware` нь тохиргоог request attributes-д inject хийнэ

### 6.10 Localization (Олон хэл)

**Классууд:** `LocalizationRouter`, `LocalizationController`, `LanguageModel`, `TextModel`, `LocalizationMiddleware`

- Хэл нэмэх / засах / устгах
- Орчуулгын текст удирдах (key → value)
- Session дээр суурилсан хэл сонголт
- Twig template дотор `{{ 'key'|text }}` ашиглах

### 6.11 Log (Лог)

**Классууд:** `LogsRouter`, `LogsController`, `Logger`

- PSR-3 стандартын лог систем
- Өгөгдлийн санд лог хадгалах
- Лог түвшин: emergency, alert, critical, error, warning, notice, info, debug
- Server request metadata автоматаар бүртгэх
- Хэрэглэгчийн мэдээлэл автоматаар бүртгэх

### 6.12 Mail (И-мэйл)

**Классууд:** `Mailer`

- Brevo (SendInBlue) API ашиглан и-мэйл илгээх
- Template-based и-мэйл илгээх

### 6.13 Template (Dashboard UI)

**Классууд:** `TemplateRouter`, `TemplateController`

- Dashboard-ийн layout (sidebar, header, content area)
- SweetAlert2, motable, moedit зэрэг JS компонентууд
- Responsive Bootstrap 5 дизайн

---

## 7. Twig Template систем

Indoraptor нь `codesaur/template` package-ийн `TwigTemplate` классыг ашиглана.

### Суурь хувьсагчид

Controller дотроос `twigTemplate()` дуудахад доорх хувьсагчид автоматаар нэмэгднэ:

| Хувьсагч | Тайлбар |
|----------|---------|
| `user` | Нэвтэрсэн хэрэглэгчийн `User` объект (null байж болно) |
| `index` | Script path (subdirectory дэмжлэг) |
| `localization` | Хэл, орчуулгын мэдээлэл |
| `request` | Одоогийн URL path |

### Twig filter-ууд

| Filter | Хэрэглээ | Тайлбар |
|--------|----------|---------|
| `text` | `{{ 'key'\|text }}` | Орчуулгын текст авах |
| `link` | `{{ 'route'\|link({'id': 5}) }}` | Route нэрээр URL үүсгэх |
| `basename` | `{{ path\|basename }}` | Файлын нэр гаргах (Web template-д) |

### Жишээ

```twig
{# Орчуулга #}
<h1>{{ 'welcome'|text }}</h1>

{# Route link #}
<a href="{{ 'page'|link({'id': page.id}) }}">{{ page.title }}</a>

{# Хэрэглэгч шалгах #}
{% if user is not null %}
    <p>Сайн байна уу, {{ user.profile.first_name }}!</p>
{% endif %}

{# Хэл солих #}
{% for code, language in localization.language %}
    <a href="{{ 'language'|link({'code': code}) }}">{{ language.title }}</a>
{% endfor %}
```

---

## 8. Routing

Indoraptor нь `codesaur/http-application` package-ийн Router классыг ашиглана.

### Route тодорхойлох

```php
class MyRouter extends \codesaur\Router\Router
{
    public function __construct()
    {
        // GET маршрут
        $this->GET('/path', [Controller::class, 'method'])->name('route-name');

        // POST маршрут
        $this->POST('/path', [Controller::class, 'method'])->name('route-name');

        // PUT маршрут
        $this->PUT('/path/{uint:id}', [Controller::class, 'method'])->name('route-name');

        // DELETE маршрут
        $this->DELETE('/path', [Controller::class, 'method'])->name('route-name');

        // GET + POST (форм)
        $this->GET_POST('/path', [Controller::class, 'method'])->name('route-name');

        // GET + PUT (засах форм)
        $this->GET_PUT('/path/{uint:id}', [Controller::class, 'method'])->name('route-name');
    }
}
```

### Динамик параметрууд

| Pattern | Тайлбар | Жишээ |
|---------|---------|-------|
| `{name}` | String параметр | `/page/{slug}` |
| `{uint:id}` | Unsigned integer | `/page/{uint:id}` |
| `{code}` | String (хэлний код) | `/language/{code}` |

### Router бүртгэх

Application класс дотроос:

```php
$this->use(new MyRouter());
```

---

## 9. Controller

### Суурь Controller (Raptor\Controller)

Бүх Controller-ууд `Raptor\Controller` ангиас удамшина. Доорх боломжуудыг нийтлэг авна:

| Метод | Тайлбар |
|-------|---------|
| `$this->pdo` | PDO холболт |
| `getUser()` | Нэвтэрсэн хэрэглэгч (`User\|null`) |
| `getUserId()` | Хэрэглэгчийн ID |
| `isUserAuthorized()` | Нэвтэрсэн эсэх |
| `isUser($role)` | RBAC дүр шалгах |
| `isUserCan($permission)` | RBAC эрх шалгах |
| `getLanguageCode()` | Идэвхтэй хэлний код |
| `getLanguages()` | Бүх хэлний жагсаалт |
| `text($key)` | Орчуулгын текст |
| `twigTemplate($file, $vars)` | Twig template объект |
| `respondJSON($data, $code)` | JSON хариулт |
| `redirectTo($route, $params)` | Redirect хийх |
| `indolog($table, $level, $msg)` | Лог бичих |
| `generateRouteLink($name, $params)` | URL үүсгэх |
| `getContainer()` | DI Container |
| `getService($id)` | Service авах |
| `errorLog($e)` | Алдаа логлох |

### Жишээ: Шинэ Controller бичих

```php
namespace Dashboard\Products;

class ProductsController extends \Raptor\Controller
{
    public function index()
    {
        // Эрх шалгах
        if (!$this->isUserCan('product_read')) {
            throw new \Error('Эрх хүрэлцэхгүй', 403);
        }

        // Model ашиглах
        $model = new ProductsModel($this->pdo);
        $products = $model->getRows(['WHERE' => 'is_active=1']);

        // Template рендерлэх
        $twig = $this->twigTemplate(__DIR__ . '/index.html', [
            'products' => $products
        ]);
        $twig->render();
    }

    public function store()
    {
        $body = $this->getRequest()->getParsedBody();
        $model = new ProductsModel($this->pdo);
        $id = $model->insert($body);

        // Лог бичих
        $this->indolog('products', \Psr\Log\LogLevel::INFO, 'Бүтээгдэхүүн нэмлээ', [
            'product_id' => $id
        ]);

        // JSON хариулт
        $this->respondJSON(['status' => 'success', 'id' => $id]);
    }
}
```

---

## 10. Model

Indoraptor нь `codesaur/dataobject` package-ийн Model классуудыг ашиглана.

### Model (нэг хэлтэй)

```php
use codesaur\DataObject\Column;
use codesaur\DataObject\Model;

class ProductsModel extends Model
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
            new Column('name', 'varchar', 255),
            new Column('price', 'decimal', '10,2'),
           (new Column('is_active', 'tinyint'))->default(1),
            new Column('created_at', 'datetime'),
        ]);
        $this->setTable('products');
    }
}
```

### LocalizedModel (олон хэлтэй)

```php
use codesaur\DataObject\Column;
use codesaur\DataObject\LocalizedModel;

class CategoriesModel extends LocalizedModel
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        // Үндсэн хүснэгт
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
           (new Column('is_active', 'tinyint'))->default(1),
        ]);

        // Хэл тус бүрийн контент
        $this->setContentColumns([
            new Column('title', 'varchar', 255),
            new Column('description', 'text'),
        ]);

        $this->setTable('categories');
    }
}
```

### Гол методууд

| Метод | Тайлбар |
|-------|---------|
| `insert($record)` | Бичлэг нэмэх |
| `updateById($id, $record)` | ID-р шинэчлэх |
| `deleteById($id)` | ID-р устгах |
| `getRowWhere($with_values)` | WHERE key=value хэлбэрийн нөхцөлөөр нэг мөр авах |
| `getRows($options)` | Олон мөр авах |
| `getName()` | Хүснэгтийн нэр авах |

### LocalizedModel өгөгдлийн бүтэц

`LocalizedModel::getRows()` буцаах бүтэц:

```php
[
    1 => [
        'id' => 1,
        'is_active' => 1,
        'localized' => [
            'mn' => ['title' => 'Монгол гарчиг', 'description' => '...'],
            'en' => ['title' => 'English title', 'description' => '...'],
        ]
    ],
    // ...
]
```

---

## 11. Хэрэглээний жишээ

### Шинэ Router нэмэх

1. Router класс үүсгэх:

```php
// application/dashboard/products/ProductsRouter.php
namespace Dashboard\Products;

class ProductsRouter extends \codesaur\Router\Router
{
    public function __construct()
    {
        $this->GET('/dashboard/products', [ProductsController::class, 'index'])->name('products');
        $this->GET_POST('/dashboard/products/insert', [ProductsController::class, 'insert'])->name('product-insert');
    }
}
```

2. `composer.json` дотор namespace бүртгэх:

```json
{
    "autoload": {
        "psr-4": {
            "Dashboard\\Products\\": "application/dashboard/products/"
        }
    }
}
```

Дараа нь autoloader-г шинэчлэх:

```bash
composer dump-autoload
```

3. Application дотор Router бүртгэх:

```php
// application/dashboard/Application.php
class Application extends \Raptor\Application
{
    public function __construct()
    {
        parent::__construct();
        $this->use(new Home\HomeRouter());
        $this->use(new Products\ProductsRouter());  // Шинэ router
    }
}
```

### Web хуудас нэмэх

```php
// application/web/home/HomeRouter.php
$this->GET('/products', [HomeController::class, 'products'])->name('products');
```

```php
// application/web/home/HomeController.php
public function products()
{
    $model = new ProductsModel($this->pdo);
    $products = $model->getRows(['WHERE' => 'is_active=1']);
    $this->template(__DIR__ . '/products.html', ['products' => $products])->render();
}
```

### Database сонгох

`Application.php` дотор database middleware-г солих:

```php
// MySQL (default)
$this->use(new \Raptor\MySQLConnectMiddleware());

// PostgreSQL руу шилжих
$this->use(new \Raptor\PostgresConnectMiddleware());

// SQLite руу шилжих
$this->use(new \Raptor\SQLiteConnectMiddleware());
```

---

## Дараагийн алхмууд

- 📚 [API тайлбар](api.md) - Бүх класс, методуудын дэлгэрэнгүй API reference
- 🦖 [codesaur ecosystem](https://github.com/codesaur-php) - Бусад packages
