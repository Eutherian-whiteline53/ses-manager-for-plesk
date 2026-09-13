# Contributing to SES Manager for Plesk

Thanks for considering a contribution. This project is a Plesk extension for Amazon SES mail delivery management, so changes should be conservative, testable, and safe for production mail/DNS environments.

## Before You Start

- Open an issue for behavior changes, new integrations, or anything that touches DNS, smarthost, SNS webhook handling, or stored credentials.
- Keep changes focused. Avoid unrelated formatting churn.
- Do not commit real AWS, Cloudflare, Plesk, SMTP, SSH, or hosting credentials.
- Do not include customer domains, email addresses, IP addresses, server hostnames, support bundles, or logs unless they are sanitized examples.

## Development Setup

This extension is designed to run inside Plesk Obsidian. Local PHP checks are useful, but they do not replace a real Plesk install/upgrade test.

Useful local checks:

```bash
composer validate --no-check-publish
composer dump-autoload --no-dev
find controllers hooks htdocs library resources sbin scripts views -name '*.php' -o -name '*.phtml' | sort | xargs -n1 php -l
```

Release ZIP builds must be created on a Plesk server:

```bash
cd /usr/local/psa/admin/plib/modules/ses-manager
./scripts/build-release.sh
```

## Pull Request Checklist

- Explain the user-visible change.
- Mention any database, scheduler, DNS, smarthost, AWS, Cloudflare, or SNS impact.
- Include manual test notes from Plesk when the change depends on Plesk APIs.
- Keep secrets masked in logs, screenshots, and support output.
- Update `README.md` or `docs/` when behavior changes.

## Coding Guidelines

- Follow the existing Plesk MVC layout.
- Keep controllers thin; put provider logic in `library/Service` or `library/Client`.
- Use repositories for database access.
- Use CSRF validation for mutating panel actions.
- Preview DNS changes before applying them.
- Merge SPF records instead of overwriting them.
- Return generic public webhook errors; log detailed reasons only after masking sensitive data.

