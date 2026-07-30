# Security Policy

> Deutsch: [SECURITY.de.md](SECURITY.de.md)

## Supported Versions

| Version | Supported |
|---------|-----------|
| 1.0.x   | Yes       |

Security fixes are released for the latest minor version. Upgrade to the newest patch release when possible.

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

Instead, report them privately by emailing the maintainer or opening a [GitHub Security Advisory](https://github.com/TimUx/grav-plugin-opencalendar/security/advisories/new).

Include:

- A description of the vulnerability and its potential impact
- Steps to reproduce
- Affected versions
- Any proof-of-concept code or logs (redact secrets)
- Suggested remediation if you have one

You should receive an acknowledgment within **72 hours**. We will work with you on a fix and coordinate disclosure timing.

## Scope

The following are in scope for security reports:

- Authentication or authorization bypass in the OpenCalendar API
- SQL injection or unsafe deserialization in storage layers
- Server-side request forgery (SSRF) via calendar source URLs
- Cross-site scripting (XSS) in rendered event output or admin forms
- Information disclosure of credentials stored for CalDAV sources

Out of scope:

- Denial of service through intentionally large ICS files when no rate limiting is documented
- Issues in Grav core or third-party themes unless directly triggered by OpenCalendar
- Social engineering or physical access scenarios

## Safe Configuration

- Store CalDAV credentials in environment-specific config overrides, not in version control.
- Restrict API access when exposing OpenCalendar on public sites (see [docs/en/API.md](docs/en/API.md)).
- Use HTTPS for remote ICS and CalDAV endpoints.
- Keep PHP and Grav updated.

## Recognition

We credit reporters in release notes when they agree to be named. We do not currently offer a bug bounty program.
