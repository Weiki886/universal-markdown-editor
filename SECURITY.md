# Security Policy

## Supported Versions

The project is under active development and has not published an official
release. Security fixes are made against the current `main` branch. Development
ZIPs are provided for evaluation and are not production-supported builds.

The plugin declares WordPress 6.4 or later and PHP 8.1 or later as minimum
requirements. This declaration is not a claim that every supported-version
combination has completed compatibility testing; see [Testing](docs/testing.md).

## Reporting a Vulnerability

Please do not report an exploitable vulnerability in a public issue. Use GitHub
Private Vulnerability Reporting for this repository when the **Report a
vulnerability** option is available. If it is unavailable, contact the
maintainer privately through the GitHub profile and ask for a secure reporting
channel. Do not include credentials, production post contents, or personal data
in the report.

There is currently no published response-time or remediation-time guarantee.
Please include the affected commit or development ZIP source SHA, impact,
reproduction steps using fictional data, and any suggested mitigation. Allow
the maintainer an opportunity to investigate before public disclosure.

## Security Boundaries

- Markdown conversion and HTML sanitization run on the server.
- Markdown source is private post metadata and is not exposed through generic
  post metadata REST responses.
- Preview and save requests require WordPress authentication and permission to
  edit the target post; media insertion uses WordPress's media capability.
- The plugin writes standard HTML to `post_content`, which remains readable
  after deactivation.

These are intended controls, not a guarantee that the plugin is vulnerability
free. Review the current automated checks and their limits in
[docs/testing.md](docs/testing.md).
