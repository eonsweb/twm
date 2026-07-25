<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- livewire/flux (FLUXUI_FREE) - v2
- livewire/livewire (LIVEWIRE) - v4
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4
- spatie/laravel-permission (PERMISSION) - v8

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Artisan

- Prefer the relevant Laravel Boost tool when it provides the information or operation needed.
- Use Artisan directly when there is no equivalent Boost tool or when an Artisan command is the correct way to generate or execute application code.
- Use `php artisan list` and `php artisan [command] --help` when command options are uncertain.
- Inspect routes with `php artisan route:list`.
- Pass `--no-interaction` to commands that might otherwise prompt for input.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If frontend changes are not reflected in the UI, verify that the Vite development server or production build is running.
- When tool access permits, run the appropriate command directly: `npm run dev`, `npm run build`, or `composer run dev`.
- Ask the user to run the command only when it cannot be executed from the current environment.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before implementing or changing behavior involving Laravel or an installed ecosystem package.
- Search documentation before assuming an API, component property, command option, testing helper, or version-specific behavior.
- Documentation search is not necessary for purely application-specific naming changes or edits that can be determined entirely from existing project code. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Use Tinker only when an existing Boost tool, test, or Artisan command is not better suited to the task.
- Do not use Tinker to create, update, or delete persistent application records without explicit user approval.
- For test data, prefer factories inside automated tests.
- Prefer `database-query` for read-only database inspection.
- Always use single quotes around `--execute` code to prevent shell expansion:
  `php artisan tinker --execute 'App\Models\User::query()->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every behavioral code change must be covered by an automated test when reasonably testable.
- Add or update tests for application logic, authorization, validation, database behavior, Livewire actions, and regressions.
- Documentation-only, formatting-only, and purely visual CSS changes do not require new automated tests unless the project already has appropriate visual or browser testing.
- Run the smallest relevant test scope first.
- Use `php artisan test --compact` with a specific filename or `--filter` whenever possible.
- Before completing a substantial change, run the broader relevant test suite when practical.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- Use `php artisan make:model` with the appropriate options.
- Create a factory when the model will be used in tests or generated test data.
- Create a seeder when the application requires default, reference, demonstration, or development data.
- Create migrations, policies, controllers, and other related artifacts only when required by the requested feature or existing application architecture.
- Check `php artisan make:model --help` when the required generation options are uncertain.

=== spatie-permission rules ===

# Roles and Permissions

- Use Spatie roles to group permissions and assign roles primarily to users.
- Prefer assigning granular permissions to roles rather than assigning permissions directly to individual users.
- Authorize actions using Laravel's built-in authorization system, including policies, gates, middleware, and `can()`, because Spatie permissions integrate with Laravel's Gate.
- Prefer permission checks such as `$user->can('edit sermons')` over role-name checks such as `$user->hasRole('admin')` when authorizing application actions.
- Use role checks only when the role itself is materially relevant to the business rule.
- Follow the project's existing permission naming convention.
- Do not hard-code authorization decisions only in Blade or Livewire templates; enforce them in the relevant server-side action as well.
- When permissions or roles change, account for Spatie's permission cache.

=== livewire/application rules ===

# Livewire Application Architecture

- Prefer Livewire components for interactive server-driven interfaces.
- Keep components focused on one page, feature, or cohesive responsibility. Extract child components when a component becomes difficult to understand or test.
- Use computed properties for derived data instead of duplicating state.
- Validate and authorize inside every action that changes application state.
- Do not rely only on disabled buttons or hidden interface elements for authorization.
- Use pagination for potentially large database result sets.
- Reset pagination when search or filter values change.
- Avoid loading unbounded Eloquent collections into component state.
- Eager load relationships when rendering related data to avoid N+1 queries.
- Use `wire:key` for elements rendered in loops when stable identity is important.
- Prefer Livewire and Alpine.js over introducing an additional JavaScript framework.
- Follow the project's established convention for class-based, single-file, or multi-file Livewire components.

=== flux-ui rules ===

# Flux UI

- Prefer existing Flux components over recreating equivalent interface controls with raw Blade markup.
- Check the installed Flux documentation and existing project usage before selecting component APIs.
- Preserve accessibility semantics, labels, focus behavior, validation states, and keyboard navigation.
- Reuse shared application components when they already wrap or standardize Flux components.
- Do not assume Flux Pro components are available; this project uses Flux UI Free.

## Database Safety

- Inspect the existing schema and related models before creating or modifying migrations.
- Use `database-query` only for read-only queries.
- Do not run destructive database commands such as `migrate:fresh`, `db:wipe`, `schema:drop`, or broad delete/update queries without explicit user approval.
- Do not modify production or shared environment data.
- When changing an existing column, preserve all intended modifiers and attributes.
- Consider foreign keys, indexes, nullability, uniqueness, existing data, and rollback behavior in every migration.

=== application-specific rules ===

# Church Management Application

- This application contains a public church website and an authenticated administration panel.
- Keep public website components and admin components clearly separated using the project's existing namespaces and directories.
- Use Spatie Permission for administrative roles and permissions.
- Prefer policies and permission checks for resource-level authorization.
- Administrative list pages should support appropriate search, filtering, sorting, pagination, authorization, empty states, and loading states.
- Store uploaded files through Laravel's filesystem abstraction rather than hard-coded public paths.
- Use named routes throughout the application.
- Keep reusable content such as sermons, events, ministries, posts, media, pages, banners, and settings in their appropriate domain models rather than embedding content directly in templates.
- Use database transactions for operations that must update multiple related records atomically.
- Record sensitive administrative changes in the activity log when the application has activity logging enabled.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
