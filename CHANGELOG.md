# Changelog

All notable project changes are documented here.

The project is currently in active pre-release development.

## Unreleased

### Added
- Open-source project documentation foundation.
- MIT License.
- Contribution guidelines.
- Community Code of Conduct.
- Security reporting policy.
- Project roadmap.
- GitHub issue and pull request templates.
- Developer architecture documentation.

### Updated
- CI: Flutter pinned to 3.47.9 through one shared composite action; added concurrency, job timeouts, and a signed-AAB matrix for both apps (still skipped until enabled).
- Patient Android build now has the same plugin `compileSdk` guard as the partner app.
- `taran_core`: one shared API base URL validator; `ApiClient` request handling de-duplicated and upload timeout raised to 60 seconds.
- Partner app data layer split into models, JSON helpers, and repository (public API unchanged).
- Backend: named rate limiters for partner endpoints (120/min, document upload 20/min) with tests for offerings isolation and validation.
- Root project overview and repository structure.
- Patient Flutter application documentation.
- Development and release status documentation.

### Notes
- This changelog does not imply a production release.
- Release versions will be documented when formally published.
