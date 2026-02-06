# 🦖 codesaur/indoraptor

[![PHP Version](https://img.shields.io/badge/php-%5E8.2.1-777BB4.svg?logo=php)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**Цэвэр архитектуртай объект хандалттай веб хөгжүүлэлтийн фреймворк**<br>
**Clean architecture object-oriented web development framework**

---

## Агуулга / Table of Contents

1. [Монгол](#1-монгол-тайлбар) | 2. [English](#2-english-description) | 3. [Getting Started](#3-getting-started)

---

## 1. Монгол тайлбар

`codesaur/indoraptor` нь PSR стандартууд (PSR-3, PSR-7, PSR-15) дээр суурилсан, **олон давхаргат архитектуртай**, **бүрэн CMS боломжтой** PHP веб фреймворк юм.

Фреймворк нь **Web** (нийтийн сайт) болон **Dashboard** (админ панель) гэсэн хоёр давхаргад хуваагдан ажилладаг бөгөөд codesaur экосистемийн бусад packages-тэй хамтран ажиллана.

### Гол боломжууд

- ✔ PSR-7/PSR-15 middleware суурьтай архитектур
- ✔ JWT + Session нэвтрэлт баталгаажуулалт
- ✔ RBAC (Role-Based Access Control) эрхийн удирдлага
- ✔ Олон хэл дэмжлэг (Localization)
- ✔ CMS модулиуд: Мэдээ, Хуудас, Файл, Лавлах, Тохиргоо
- ✔ MySQL / PostgreSQL / SQLite дэмжлэг
- ✔ Twig template engine
- ✔ OpenAI интеграци (moedit editor)
- ✔ Зураг optimize хийх (GD)
- ✔ PSR-3 лог систем

### Дэлгэрэнгүй мэдээлэл

- 📖 [Бүрэн танилцуулга](docs/mn/README.md) - Суулгах, тохируулах, архитектур, хэрэглээ
- 📚 [API тайлбар](docs/mn/api.md) - Бүх модуль, класс, методуудын дэлгэрэнгүй

---

## 2. English Description

`codesaur/indoraptor` is a **multi-layered**, **full-featured CMS** PHP web framework built on PSR standards (PSR-3, PSR-7, PSR-15).

The framework operates in two layers - **Web** (public website) and **Dashboard** (admin panel) - and works together with other packages in the codesaur ecosystem.

### Key Features

- ✔ PSR-7/PSR-15 middleware-based architecture
- ✔ JWT + Session authentication
- ✔ RBAC (Role-Based Access Control)
- ✔ Multi-language support (Localization)
- ✔ CMS modules: News, Pages, Files, References, Settings
- ✔ MySQL / PostgreSQL / SQLite support
- ✔ Twig template engine
- ✔ OpenAI integration (moedit editor)
- ✔ Image optimization (GD)
- ✔ PSR-3 logging system

### Documentation

- 📖 [Full Documentation](docs/en/README.md) - Installation, configuration, architecture, usage
- 📚 [API Reference](docs/en/api.md) - All modules, classes, and methods

---

## 3. Getting Started

### Requirements

- PHP **8.2.1+**
- Composer
- MySQL / PostgreSQL / SQLite
- PHP extensions: `ext-gd`, `ext-intl`

### Installation

```bash
composer create-project codesaur/indoraptor my-project
```

### Configuration

`composer create-project` ашигласан бол `.env` файл автоматаар үүсэх бөгөөд `INDO_JWT_SECRET` мөн автоматаар generate хийгдэнэ. Хэрэв `.env` үүсээгүй бол гараар хуулна:

If you used `composer create-project`, the `.env` file is auto-created and `INDO_JWT_SECRET` is auto-generated. If `.env` was not created, copy it manually:

```bash
cp .env.example .env
```

Гол тохиргоонууд / Key configuration:

```env
# Environment (development / production)
CODESAUR_APP_ENV=development

# Database
INDO_DB_HOST=localhost
INDO_DB_NAME=indoraptor
INDO_DB_USERNAME=root
INDO_DB_PASSWORD=

# JWT (secret is auto-generated)
INDO_JWT_ALGORITHM=HS256
INDO_JWT_LIFETIME=2592000
```

### Quick Architecture

```
public_html/index.php
 ├── /dashboard/* → Dashboard\Application (Admin Panel)
 │    ├── Middleware stack (Session, JWT, RBAC, Localization, Settings)
 │    ├── Routers (Login, Users, Organization, RBAC, Content, Logs)
 │    └── Controllers → Twig Templates
 │
 └── /* → Web\Application (Public Website)
      ├── Middleware stack (Session, Localization, Settings)
      ├── HomeRouter (/, /page/{id}, /news/{id}, /contact, /language/{code})
      └── TemplateController → Twig Templates
```

**Request Flow:** index.php → Application → Middleware chain → Router match → Controller → Response

### Directory Structure

```
indoraptor/
├── application/
│   ├── raptor/              # Core framework (Controllers, Models, Middleware)
│   │   ├── authentication/  # Login, JWT, Session
│   │   ├── content/         # CMS (files, news, pages, references, settings)
│   │   ├── localization/    # Languages & translations
│   │   ├── organization/    # Organization management
│   │   ├── rbac/            # Roles & permissions
│   │   ├── user/            # User management
│   │   ├── template/        # Dashboard UI
│   │   ├── log/             # Logging
│   │   └── mail/            # Email
│   ├── dashboard/           # Dashboard application
│   └── web/                 # Public website application
├── public_html/             # Document root
│   ├── index.php            # Entry point
│   ├── .htaccess            # Apache URL rewrite
│   └── assets/              # CSS, JS (dashboard, moedit, motable)
├── logs/                    # Error logs
├── private/                 # Protected files
├── .env.example             # Environment configuration template
├── composer.json            # Dependencies
└── LICENSE                  # MIT License
```

---

## Did You Know?

**Velociraptor** (/vɪˈlɒsɪræptər/ - Латинаар "swift seizer" буюу "хурдан баригч") нь Cretaceous галавын сүүл үе буюу ойролцоогоор 75-71 сая жилийн өмнө амьдарч байсан dromaeosaurid theropod үлэг гүрвэлийн төрөл юм. Одоогоор хоёр зүйлийг хүлээн зөвшөөрсөн бөгөөд *V. mongoliensis* энэ зүйлийн олдворуудыг **Монгол** улсаас олсон байдаг. Хоёр дахь зүйл *V. osmolskae*-г 2008 онд Өвөр Монголоос олдсон гавлын материалаар нэрлэсэн.

**Indoraptor** нь "Jurassic World: Fallen Kingdom" киноны гол антагонист болсон шинэ эрлийз динозавр юм. Бидний фреймворкийн нэр яг эндээс үүдэлтэй!

## Acknowledgements

Энэ фреймворкийг хөгжүүлэхэд [**Gerege Systems LLC**](https://gerege.com/) ивээн тэтгэж, компанийн үүсгэн байгуулагч **Ц.Эрдэнэбат** багш удирдан зааварлаж чиглүүлсэн билээ.

This framework was developed with the sponsorship of [**Gerege Systems LLC**](https://gerege.com/) and under the guidance of **Ts.Erdenebat**, founder of Gerege Systems.

---

## Changelog

- 📝 [CHANGELOG.md](CHANGELOG.md) - Version history

## Contributing & Security

- 🤝 [Contributing Guide](.github/CONTRIBUTING.md)
- 🔐 [Security Policy](.github/SECURITY.md)

## License

This project is licensed under the MIT License.

## Author

**Narankhuu**<br>
📧 codesaur@gmail.com<br>
📱 +976 99000287<br>
🌐 https://github.com/codesaur

🦖 **codesaur ecosystem:** https://codesaur.net
