# Security policy

## Supported versions

Security fixes are prepared for the latest published InteractEmbed Creator
version on supported Moodle 4.5 through 5.2 installations.

## Reporting a vulnerability

Do not publish exploitable details in a public issue. Use
[GitHub private vulnerability reporting](https://github.com/InteractEmbed/moodle-local_interactembedcreator/security/advisories/new)
and include the affected version, safe reproduction steps and expected impact.

Never include real learner data, credentials, session cookies or private HTML5
packages in a report.

## Trust boundary

Creator validates editable project imports and builds immutable publications.
Published HTML5 runs through the consumer plugins' sandboxed, cookie-free and
signed-resource delivery boundary. Authoring and publishing capabilities carry
Moodle's `RISK_XSS` flag and should be granted only to trusted roles.
