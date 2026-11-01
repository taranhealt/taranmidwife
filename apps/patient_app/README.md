# TaranMidwife Patient App

Flutter Android application for the patient-facing part of TaranMidwife.

This application is part of the TaranMidwife monorepository maintained
by PT Taran Rainmaker Group.

## Current scope

The current source tree includes:

- Application entry point and configuration.
- Authentication page.
- Home navigation shell.
- Service listing.
- Booking form.
- Order listing.
- Profile screen.
- Emergency information widget.
- Retry and error presentation widget.

The presence of a page or widget does not by itself establish that
its complete user workflow has passed end-to-end testing.

## Shared package

The application uses the local `taran_core` package:

```yaml
taran_core:
  path: ../../packages/taran_core
```

The shared package provides API, authentication, model, status,
formatting, theme, and token-storage components.

## Requirements

- Flutter SDK compatible with the project's Dart constraint.
- Android SDK.
- Java 17.
- A configured backend API endpoint.

## Development

```bash
flutter pub get
flutter analyze
flutter test
```

Build a debug APK:

```bash
flutter build apk --debug
```

The project workflow may supply the API endpoint using:

```bash
--dart-define=API_BASE_URL=https://your-api-host.example/api/v1
```

Use an endpoint intended for development or testing.
Do not commit credentials or production secrets.

## Android package

The current Android Gradle configuration declares:

`com.taranrainmakergroup.taranmidwife`

Verify package identity and release configuration before publishing.

## Release status

A debug APK is not a production release.

The current release configuration must be changed to use a company-controlled
upload key before producing a signed release AAB for Google Play.

See `backend/docs/PLAY_RELEASE_STATUS.md`.

## Privacy and healthcare data

Use synthetic data during development and testing.

Do not place real patient records, credentials, or private documents
in the repository, test fixtures, screenshots, or public issue reports.
