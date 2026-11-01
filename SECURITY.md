# Security Policy

## Supported Versions

TaranMidwife is under active development. Security fixes are prioritized for the current maintained development branch.

No production support or service-level guarantee is implied by this policy.

## Reporting a Vulnerability

Please do not disclose security vulnerabilities through public GitHub issues.

Report suspected vulnerabilities privately to the project maintainer using a private security advisory if GitHub private vulnerability reporting is enabled for this repository.

If that feature is unavailable, contact PT Taran Rainmaker Group through its officially published contact channel.

Please include:

- A description of the vulnerability.
- The affected component and version or commit.
- Reproduction steps.
- Potential impact.
- Suggested mitigation, if known.

## Responsible Disclosure

Please allow the maintainer reasonable time to investigate and address a report before public disclosure.

Do not access, modify, or expose data belonging to other users.

## Sensitive Information

Never commit:

- `.env` files containing secrets.
- API keys or access tokens.
- Database credentials.
- Private signing keys.
- Real patient records.
- Production backups or confidential documents.

Use synthetic data for development and testing.
