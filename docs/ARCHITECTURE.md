# System Architecture

## Overview

TaranMidwife is organized as a monorepository containing backend,
mobile application, shared application code, and infrastructure scripts.

```text
Patient Android application
          |
          v
    Shared Dart package
          |
          v
     Laravel REST API
          |
          v
       PostgreSQL
```

This is a conceptual view of the current project structure.
It does not imply that every production integration or deployment
component is already operational.

## Repository components

### Backend

Location: `backend/`

The backend is built with Laravel and provides the API and server-side
application foundation.

Relevant areas include:

- Application logic.
- Authentication and authorization.
- API routes.
- Database migrations and seeders.
- Automated tests.
- Technical documentation.

### Patient application

Location: `apps/patient_app/`

The patient application is built with Flutter and targets Android.

Its current source tree includes authentication, home navigation,
services, booking, orders, profile, and supporting widgets.

The existence of these source files does not establish that all
end-to-end workflows have been fully tested.

### Shared package

Location: `packages/taran_core/`

The shared Dart package contains reusable functionality such as:

- API client.
- API exceptions.
- Authentication controller.
- Data models.
- Status definitions.
- Formatting helpers.
- Theme.
- Token storage.

### Infrastructure

Location: `infrastructure/`

Contains utility scripts for backup, restore testing, and repository
operations.

Review scripts before running them against important environments.

### Automation

Location: `.github/workflows/`

Contains GitHub Actions workflows for backend and Flutter validation
and build automation.

## Data flow

The intended application architecture uses the Flutter client to
communicate with the Laravel API. The backend handles server-side
validation, authorization, workflow transitions, and database access.

PostgreSQL is the documented backend database.

## Environment configuration

Local environment files and secrets must remain outside version control.

Use the example environment files as configuration references.
Never copy production secrets into public documentation or issues.

## Testing boundaries

Testing evidence must identify its environment:

- Local development.
- GitHub Actions.
- Staging.
- Production.

Do not treat a successful build as proof of correct application behavior.

## Architecture changes

Large changes to API contracts, database structure, authentication,
or booking workflows should be discussed through GitHub issues
before implementation.
