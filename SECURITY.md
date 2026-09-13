# Security Policy

## Supported Versions

Security fixes are handled for the latest public release.

| Version | Supported |
| --- | --- |
| 1.0.x | Yes |

## Reporting a Vulnerability

Please do not open a public issue for vulnerabilities that expose secrets, enable unauthorized Plesk actions, bypass SNS signature validation, change DNS unexpectedly, or affect smarthost/mail routing safety.

Report security issues through:

- GitHub private vulnerability reporting, if enabled for the repository.
- The support channel listed in the repository metadata or product page.

Include:

- Affected version.
- Plesk version.
- Clear reproduction steps.
- Expected and actual behavior.
- Sanitized logs or screenshots, with secrets removed.

Do not send AWS secret keys, SES SMTP passwords, Cloudflare tokens, private keys, mailbox passwords, root credentials, or unsanitized support bundles.

## Security Design Notes

- AWS, SES SMTP, and Cloudflare secrets are stored with Plesk `pm_Crypt` where supported.
- Mutating panel actions require CSRF validation.
- DNS changes are previewed before apply.
- SPF records are merged rather than overwritten.
- AWS SNS webhook payloads are size-limited and signature-verified.
- SNS signing certificates are restricted to trusted AWS SNS certificate URLs.
- Support bundle output masks stored secrets.

