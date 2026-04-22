# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

TODO

## Code Standards

Follow clean code principles and SOLID design patterns when working with this codebase:
- Write clean, readable, and maintainable code
- Apply SOLID principles (Single Responsibility, Open/Closed, Liskov Substitution, Interface Segregation, Dependency Inversion)
- Use meaningful variable and method names
- Keep methods and classes focused on a single responsibility
- Favor composition over inheritance
- Write code that is easy to test and extend

### Service Definitions in Plugins
- **Services live in `src/Resources/config/services.xml`** (XML, not YAML, not PHP). This is the Symfony Best Practices recommendation for reusable bundles — XML is unambiguous about types and avoids the whitespace footguns of YAML.
- **No autowire, no autoconfigure, no resource-based auto-discovery.** Every service is declared explicitly with its class, arguments, and tags. Plugins must not rely on the host application's autowire hints: what works in the test app may silently break in a consumer app with different bindings.
- **One file per root folder under `src/`.** `services.xml` acts as a dispatcher that uses `<imports>` to pull in per-folder files kept at `src/Resources/config/services/<folder>.xml`. Example mapping:
  - `src/Resolver/` → `src/Resources/config/services/resolver.xml`
  - `src/Generator/` → `src/Resources/config/services/generator.xml`
  - `src/Tracker/` → `src/Resources/config/services/tracker.xml`
  - `src/Action/` → `src/Resources/config/services/action.xml`

  Folders that contain no services (`Model/`, `Resources/`, `DependencyInjection/`) get no file. The rule keeps each XML file small and the service surface grep-able.
- **Service IDs use the fully-qualified class name.** Interfaces are registered as `alias` entries pointing at the concrete implementation, e.g.:
  ```xml
  <service id="Setono\SyliusQRCodePlugin\Generator\QRCodeGenerator">
      <argument type="service" id="..."/>
  </service>
  <service id="Setono\SyliusQRCodePlugin\Generator\QRCodeGeneratorInterface"
           alias="Setono\SyliusQRCodePlugin\Generator\QRCodeGenerator"/>
  ```
- **Services are private by default** (Symfony 5.4+ default). Mark `public="true"` only when the service must be fetched from the container directly (e.g. controllers invoked as services, console command tags, actions referenced by a router `controller` attribute).
- **Inside the test application (`tests/Application/`)**, autowire/autoconfigure are fine — that's a typical Symfony app, not a reusable bundle.

### Doctrine Entity Conventions
- **Nullable property + nullable getter when no sensible default exists.** If a property does not have a sensible literal default (e.g. `false`, `0`, a meaningful enum), declare it `?Type $x = null` and type the getter `?Type`. Do NOT reach for empty-string/placeholder sentinels (`''`, `'unknown'`, `new \DateTimeImmutable()`) just to satisfy a non-nullable type. Example: `protected string $userAgent = '';` → `protected ?string $userAgent = null;` with `getUserAgent(): ?string`.
- **No constructor logic to set defaults.** Doctrine entities should not initialize scalar fields (timestamps, strings, numbers) in the constructor — keep them nullable instead. Collection fields that must never be null (e.g. `ArrayCollection`) are the pragmatic exception.
- **No service injection in entity constructors.** It's a Symfony pattern that Doctrine entities don't take collaborators — state lives on them, behavior lives in services.
- **Use Gedmo Timestampable for `createdAt` / `updatedAt`.** Never set these in the constructor or in a lifecycle callback by hand. Declare the ORM mapping field with `<gedmo:timestampable on="create"/>` (for `createdAt`/`scannedAt`-style fields) or `<gedmo:timestampable on="update"/>` (for `updatedAt`). Enable the listener once via `stof_doctrine_extensions.orm.default.timestampable: true` (the test app already does this). Gedmo populates the field at flush time; the PHP property stays nullable before persistence. This is the standard Sylius pattern — `stof/doctrine-extensions-bundle` is already registered in the test application.
- Database-level NOT NULL constraints are independent of this — the property can be nullable in PHP while the column is `nullable="false"` in the ORM mapping. The caller (factory, controller, listener, or Gedmo listener) is responsible for populating before flush.

### Testing Requirements
- **Aspire to 100% code coverage.** Any class under `src/` (including entities, form types, actions, services) should have a matching test class. If a line is genuinely untestable — e.g. a defensive `throw` for a case the type system already rules out, or framework glue with no meaningful seam — document that explicitly in the test or exclude it from coverage with a reason. "This is boilerplate" is not a reason to skip coverage; plain accessors on entities still get tested.
- Write unit tests for all new functionality
- Follow the BDD-style naming convention for test methods (e.g., `it_should_do_something_when_condition_is_met`)
- **MUST use Prophecy for mocking** - Use the `ProphecyTrait` and `$this->prophesize()` for all mocks, NOT PHPUnit's `$this->createMock()`
- **Form testing** - Use Symfony's best practices for form testing as documented at https://symfony.com/doc/current/form/unit_testing.html
  - Extend `Symfony\Component\Form\Test\TypeTestCase` for form type tests
  - Use `$this->factory->create()` to create form instances
  - Test form submission, validation, and data transformation
- Ensure tests are isolated and don't depend on external state
- Test both happy path and edge cases

### Test Layout
Tests are split by type, mirroring the source tree inside each bucket:

```
tests/
├── Application/          # test Symfony app used by functional tests (NOT a test bucket)
├── Entity/QRCode/        # app-level Doctrine entities for the test app (STI concretes) — NOT tests
├── Functional/           # end-to-end tests that boot the test app (HTTP layer, admin CRUD, etc.)
├── Integration/          # tests that hit real infra (Doctrine against SQLite/MySQL, container, filesystem)
├── PHPStan/              # loaders used by phpstan.neon (console_application.php, object_manager.php) — NOT tests
└── Unit/                 # pure unit tests, no container, no DB; mirror src/ structure underneath
    └── DependencyInjection/
```

Conventions:
- **Unit tests go in `tests/Unit/<MirroredSourcePath>/<ClassName>Test.php`** with namespace `Setono\SyliusQRCodePlugin\Tests\Unit\<MirroredSourcePath>`. Example: `src/Resolver/TargetUrlResolver.php` → `tests/Unit/Resolver/TargetUrlResolverTest.php`.
- **Integration tests go in `tests/Integration/...`** with namespace `Setono\SyliusQRCodePlugin\Tests\Integration\...`. Use when a real EntityManager or container is required.
- **Functional tests go in `tests/Functional/...`** with namespace `Setono\SyliusQRCodePlugin\Tests\Functional\...`. Boot the test application kernel.
- PHPUnit exposes three testsuites named `unit`, `integration`, `functional` — run one with `vendor/bin/phpunit --testsuite unit`.
- Empty testsuite directories are tracked with `.gitkeep` files.

## Development Commands

Based on the `composer.json` scripts section:

### Code Quality & Testing
- `composer analyse` - Run PHPStan static analysis (level 8)
- `composer check-style` - Check code style with ECS (Easy Coding Standard)
- `composer fix-style` - Fix code style issues automatically with ECS
- `composer phpunit` - Run PHPUnit tests

### Static Analysis

#### PHPStan Configuration
PHPStan is configured in `phpstan.neon` with:
- **Analysis Level**: max (strictest)
- **Extensions**: Auto-loaded via `phpstan/extension-installer`
  - `phpstan/phpstan-symfony` - Symfony framework integration
  - `phpstan/phpstan-doctrine` - Doctrine ORM integration
  - `phpstan/phpstan-phpunit` - PHPUnit test integration
  - `jangregor/phpstan-prophecy` - Prophecy mocking integration
- **Symfony Integration**: Uses console application loader (`tests/console_application.php`)
- **Doctrine Integration**: Uses object manager loader (`tests/object_manager.php`)
- **Exclusions**: Test application directory and Configuration.php
- **Baseline**: Generate with `composer analyse -- --generate-baseline` to track improvements

### Test Application
The plugin includes a test Symfony application in `tests/Application/` for development and testing:
- Navigate to `tests/Application/` directory
- Run `yarn install && yarn build` to build assets
- Use standard Symfony commands for the test app
- **Sylius Backend Credentials**: Username: `sylius`, Password: `sylius`

## Bash Tools Recommendations

Use the right tool for the right job when executing bash commands:

- **Finding FILES?** → Use `fd` (fast file finder)
- **Finding TEXT/strings?** → Use `rg` (ripgrep for text search)
- **Finding CODE STRUCTURE?** → Use `ast-grep` (syntax-aware code search)
- **SELECTING from multiple results?** → Pipe to `fzf` (interactive fuzzy finder)
- **Interacting with JSON?** → Use `jq` (JSON processor). **NEVER use `python`/`python3` for JSON parsing — always `jq`.**
- **Interacting with YAML or XML?** → Use `yq` (YAML/XML processor)

Examples:
- `fd "*.php" | fzf` - Find PHP files and interactively select one
- `rg "function.*validate" | fzf` - Search for validation functions and select
- `ast-grep --lang php -p 'class $name extends $parent'` - Find class inheritance patterns

## Architecture Overview

### Translations
The plugin provides multilingual support through translation files in `src/Resources/translations/`:

- **Translation Files**: Available in 10 languages (en, da, de, es, fr, it, nl, no, pl, sv)
- **Translation Domains**:
  - `messages.*` - General UI translations
  - `flashes.*` - Flash message translations (success/error messages)

Key translation keys:
- `setono_sylius_qr_code.ui.*` - UI labels
- `setono_sylius_qr_code.form.*` - Form field labels
- `setono_sylius_qr_code.single_message` - A flash message
