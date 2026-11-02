# English Cards project rules

These project rules take precedence over generic command examples in the generated Laravel Boost guidelines.

## Requirements and scope

- English Cards uses Laravel 13, PHP 8.4, and MySQL. Read the relevant files in `docs/` before changing behavior or data structures. The business documents define product behavior; `docs/architecture/data-model.md` defines the agreed database design.
- Follow the existing application architecture and directory structure. Change Docker configuration or dependencies only when needed and after the project owner approves the specific change.
- Do not install PHP, Composer, Node.js, MySQL, or other development tools directly in WSL2.

## Development environment and commands

- The source code is on the WSL2 Ubuntu filesystem. The development runtime is Docker Desktop with Docker Compose, without Laravel Sail.
- Run PHP, Composer, Artisan, PHPUnit, and Pint from the repository root through the `app` service. Examples: `docker compose exec -T app php artisan migrate`, `docker compose exec -T app php artisan test`, `docker compose exec -T app composer show --direct`, `docker compose exec -T app vendor/bin/phpunit`, and `docker compose exec -T app vendor/bin/pint --dirty`.
- Run MySQL commands through the existing `db` service. Keep database tests on a separate MySQL test database; never run destructive reset commands on the development database.
- Check `compose.yaml` before running npm. It currently defines `app`, `nginx`, and `db`, with no Node.js service; the `app` image does not install Node.js. Do not run npm in WSL2 or assume it works in `app`. If a task needs npm, agree on a Docker Compose Node.js service before running it there.

## Staged tasks

- For tasks split into stages, complete only the current explicitly requested stage, including all of its requirements, relevant tests, and documentation. Mark a checklist item complete only after it is implemented and verified.
- After completing or blocking a stage, report changes and test results, then stop. Start the next stage only after the project owner explicitly requests it.
- Do not create a Git commit or push without an explicit request. Propose commit messages in Conventional Commits form, for example `feat: add users and learning profiles database foundation.`
