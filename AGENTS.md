# Repository Guidelines

## Project Structure & Module Organization
`src/` contains the main PHP application, organized by domain-oriented namespaces such as `Command/`, `Controller/`, `Domain/`, `Entity/`, and `Service/`. Twig templates live in `templates/`, public assets in `public/`, runtime data in `storage/`, and longer-form design or ops notes in `docs/`. Tests for the PHP app are in `tests/Unit/`. The Python sidecar is isolated under `docker/sidecar/`, while small auxiliary tools live in `tools/`.

## Build, Test, and Development Commands
Use Composer scripts for the PHP app:

- `composer lint` checks formatting with Pint.
- `composer format` rewrites PHP formatting.
- `composer analyse` runs PHPStan.
- `composer test` runs PHPUnit unit tests.

For the sidecar:

- `cd docker/sidecar && ruff check app/` runs Python linting.
- `cd docker/sidecar && pytest` runs sidecar tests.

CI mirrors these commands in [`.github/workflows/ci.yml`](/home/fsd42/dev/claudriel/.github/workflows/ci.yml).

## Coding Style & Naming Conventions
Follow PSR-4 autoloading: PHP classes use the `Claudriel\\` namespace and live under matching paths in `src/`. Keep class names descriptive and singular where appropriate, for example `Claudriel\\Service\\...` or `Claudriel\\Command\\...`. Use Pint for PHP formatting; do not hand-format around it. In the sidecar, use Python 3.11, `snake_case` module names, and Ruff’s configured 120-character line limit.

## Testing Guidelines
Add PHP tests under `tests/Unit/` with names ending in `Test.php`; current PHPUnit config only includes that suite. Add sidecar tests as `test_*.py` files under `docker/sidecar/app/`. Run the smallest relevant test set locally before opening a PR, then run the full repo checks if your change crosses PHP and sidecar boundaries.

## Commit & Pull Request Guidelines
Recent history uses short imperative subjects, for example `Make recurring schedule edits safe by default` and `Format schedule times in dashboard`. Keep commits focused and avoid bundling unrelated files. Open PRs against `main` with a clear description, linked issue when applicable, and screenshots for UI changes. Standard flow is branch -> PR -> merge -> GitHub Actions deploy; do not treat direct production edits as normal workflow.

## Security & Configuration Tips
This repo uses local path Composer repositories during development, so avoid hardcoding machine-specific paths outside the existing setup. Treat `storage/`, deployment config, and server access as sensitive; inspect production over SSH only when explicitly requested.
