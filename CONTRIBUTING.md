# Contributing

## Workflow

1. Create a focused branch from `main` using `feature/`, `fix/`, or `chore/`.
2. Make one cohesive change and add or update tests.
3. Run `bash tools/check.sh`.
4. Submit the focused change with its test results and any relevant screenshots.

Do not commit generated ZIP files, credentials, customer data, or licensed third-party assets.

## Code expectations

- Follow the WordPress Coding Standards.
- Support the PHP and WordPress versions declared in the plugin header.
- Prefix global symbols and hooks with `my_gallery_pro_`.
- Check capabilities and nonces before state changes.
- Sanitize input, validate business rules, and escape output at the point of rendering.
- Use prepared SQL for any query that cannot use a WordPress data API.
- Preserve image alt text and keyboard operation in every gallery experience.
- Avoid loading editor or front-end assets globally.

## Commits and pull requests

Use short, imperative commit subjects, for example `Add gallery post type`. Pull requests should explain the user-facing outcome, testing performed, screenshots for UI changes, and compatibility or migration impact.

Breaking changes require a migration plan and a clearly documented deprecation period.
