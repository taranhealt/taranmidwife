<div align="center">

# TaranMidwife

### Digital Midwifery & Maternal Healthcare Platform

**Building practical digital technology for maternal and community healthcare.**

An open-source healthcare technology initiative by
**PT Taran Rainmaker Group**

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Contributions Welcome](https://img.shields.io/badge/Contributions-Welcome-brightgreen.svg)](CONTRIBUTING.md)
[GitHub](https://github.com/taranrainmaker) | [Saweria](https://saweria.co/taranrainmaker)
[GitHub](https://github.com/taranrainmaker) | [Saweria](https://saweria.co/taranrainmaker)
[GitHub](https://github.com/taranrainmaker) | [Saweria](https://saweria.co/taranrainmaker)
[![Pull Requests](https://img.shields.io/github/issues-pr/taranrainmaker/taranmidwife)](https://github.com/taranrainmaker/taranmidwife/pulls)
[![Contributors](https://img.shields.io/github/contributors/taranrainmaker/taranmidwife)](https://github.com/taranrainmaker/taranmidwife/graphs/contributors)
[![Last Commit](https://img.shields.io/github/last-commit/taranrainmaker/taranmidwife)](https://github.com/taranrainmaker/taranmidwife/commits/main)
[![Support on Saweria](https://img.shields.io/badge/Saweria-Support%20Development-orange)](https://saweria.co/taranrainmaker)

[Overview](#overview) •
[Architecture](#architecture) •
[Features](#current-capabilities) •
[Technology](#technology-stack) •
[Getting Started](#getting-started) •
[Contributing](#contributing)

</div>

---

## Overview

TaranMidwife is a digital healthcare technology platform designed to support midwifery services, maternal healthcare workflows, patient engagement, and connected healthcare operations.

Developed by **PT Taran Rainmaker Group**, the project aims to make practical, accessible, and interoperable digital healthcare technology more available to healthcare providers and communities.

The repository is maintained as an evolving open-source project. Its backend, patient application, shared components, and supporting infrastructure are developed as parts of a common platform.

## Project Vision

Our vision is to support the digital transformation of maternal and community healthcare through technology that is:

- **Accessible** — designed to support patients and healthcare providers.
- **Practical** — focused on real operational workflows.
- **Interoperable** — designed with API-based integration in mind.
- **Maintainable** — organized for long-term development.
- **Responsible** — developed with attention to privacy, security, and healthcare considerations.

## Current Capabilities

The following capabilities are represented in the current source code and project documentation. Their testing and implementation maturity may differ.

### Backend and API

- Authentication and role-based authorization.
- Patient and healthcare partner account workflows.
- Service catalog and service-area management.
- Appointment and booking workflows.
- Booking assignment and partner offer management.
- Partner verification and credential-expiry handling.
- Location-based service coverage and matching.
- Complaint management workflows.
- Account deletion and anonymization mechanisms.
- API rate limiting and audit-related functionality.

### Patient Application

The Flutter patient application source currently includes:

- Authentication interface.
- Home navigation.
- Service listing.
- Booking form.
- Order listing.
- User profile.
- Emergency information component.
- Retry and error presentation components.

The availability of a screen or component does not necessarily mean its complete workflow has passed end-to-end testing.

### Web Interfaces

The backend source also contains web interfaces for patient, partner, and administrative workflows. These interfaces have different levels of implementation and testing maturity.

See the [Development Status](backend/docs/DEVELOPMENT_STATUS.md) documentation for details.

## Architecture

TaranMidwife is organized as a monorepository.

```text
TaranMidwife
│
├── backend/
│   └── Laravel REST API
│       ├── Authentication
│       ├── Business Logic
│       ├── API Routes
│       ├── Database
│       └── Automated Tests
│
├── apps/
│   └── patient_app/
│       └── Flutter Android Application
│
├── packages/
│   └── taran_core/
│       └── Shared Dart Components
│
├── docs/
│   └── Architecture and Project Documentation
│
├── infrastructure/
│   └── Operational Utility Scripts
│
└── .github/
    └── Workflows and Repository Templates
## Technology Stack

| Component | Technology |
|---|---|
| Backend | PHP 8.3+ |
| Backend Framework | Laravel 13 |
| Authentication | Laravel Sanctum |
| Database | PostgreSQL |
| Patient Application | Flutter |
| Programming Language | Dart |
| Shared Components | `taran_core` |
| API Architecture | REST |
| Automation | GitHub Actions |

## Repository Components

| Path | Description |
|---|---|
| `backend/` | Laravel backend and REST API |
| `apps/patient_app/` | Flutter patient Android application |
| `packages/taran_core/` | Shared Dart components |
| `docs/` | Architecture and project documentation |
| `infrastructure/` | Utility and operational scripts |
| `.github/` | GitHub workflows and templates |

## Getting Started

### Prerequisites

- PHP 8.3 or compatible newer version.
- Composer.
- PostgreSQL.
- Flutter SDK compatible with the project's Dart constraint.
- Android SDK for Android builds.
- Java 17 for Android tooling.

### Backend Setup

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Configure your local database connection in `.env`, then run:

```bash
php artisan migrate
php artisan serve
```

Run backend tests:

```bash
php artisan test
```

Documentation:

- [Backend README](backend/README.md)
- [Local Run Guide](backend/docs/LOCAL_RUN.md)
- [Deployment Guide](backend/docs/DEPLOYMENT.md)
### Patient Application Setup

```bash
cd apps/patient_app
flutter pub get
flutter analyze
flutter test
```

Build a development APK:

```bash
flutter build apk --debug
```

Configure the backend endpoint for development or testing:

```bash
--dart-define=API_BASE_URL=https://your-api-host.example/api/v1
```

See [Patient Application Documentation](apps/patient_app/README.md).

## Project Status

TaranMidwife is under active development.

The backend contains functionality that has been tested in a local development environment. The patient Flutter application source is available, while mobile end-to-end validation and production release preparation remain ongoing.

The project is not presented as production-ready or clinically certified.

| Area | Current Position |
|---|---|
| Backend foundation | Implemented; local testing documented |
| REST API | Implemented; local testing documented |
| Patient Flutter source | Available; validation ongoing |
| Shared Dart package | Available |
| Web interfaces | Mixed implementation and testing status |
| Production deployment | Not yet established in project status |
| Mobile production release | Not yet approved |
| Legal and clinical review | Pending |
| Security assessment | Ongoing |

Further information:

- [Development Status](backend/docs/DEVELOPMENT_STATUS.md)
- [Roadmap](ROADMAP.md)
- [Changelog](CHANGELOG.md)
- [Google Play Release Status](backend/docs/PLAY_RELEASE_STATUS.md)

## Security and Privacy

Healthcare technology requires responsible handling of personal and sensitive information.

Our development principles:

- Use synthetic data for development and testing.
- Never commit real patient records or production credentials.
- Keep environment files, access tokens, and private keys out of version control.
- Review authentication, authorization, and data access changes carefully.
- Report security vulnerabilities privately rather than through public issues.

Read our [Security Policy](SECURITY.md).

## Contributing

We welcome contributions from developers, designers, testers, documentation writers, and the wider healthcare technology community.

You can contribute by:

- Reporting reproducible bugs.
- Improving documentation.
- Reviewing code.
- Proposing new features.
- Improving accessibility and reliability.
- Submitting tested improvements.

Please read `CONTRIBUTING.md` before submitting a pull request.

See our [Code of Conduct](CODE_OF_CONDUCT.md).

## License

TaranMidwife source code is distributed under the [MIT License](LICENSE().

The MIT License applies to the code covered by the license. Project names, logos, branding, and trademarks associated with TaranMidwife and PT Taran Rainmaker Group remain subject to applicable intellectual property rights.

## Support the Project

Open-source development takes time, research, testing, and continuous improvement.

If you find this project useful and would like to support its development, you can contribute through Saweria.

<br>

<div align="center">

> [Support TaranMidwife Development](https://saweria.co/taranrainmaker)

</div>

---

<center>

**Developed and maintained by PT Taran Rainmaker Group**

Healthcare Technology - Digital Innovation - Open Collaboration

[GitHub](https://github.com/taranrainmaker) | [Saweria](https://saweria.co/taranrainmaker)

</center>
