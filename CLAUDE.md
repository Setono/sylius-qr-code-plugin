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

### Git Commit Policy
**Never commit without explicit user instruction.** Even after a feature is complete, tests green, and everything verified — do NOT run `git commit` unless the user says to. The user drives the commit cadence. When ready to commit, the user will say so explicitly.

### Service Definitions in Plugins
- **Services live in `src/Resources/config/services.xml`** (XML, not YAML, not PHP). This is the Symfony Best Practices recommendation for reusable bundles — XML is unambiguous about types and avoids the whitespace footguns of YAML.
- **No autowire, no autoconfigure, no resource-based auto-discovery.** Every service is declared explicitly with its class, arguments, and tags. Plugins must not rely on the host application's autowire hints: what works in the test app may silently break in a consumer app with different bindings.
- **One file per root folder under `src/`.** `services.xml` acts as a dispatcher that uses `<imports>` to pull in per-folder files kept at `src/Resources/config/services/<folder>.xml`. Example mapping:
  - `src/Resolver/` → `src/Resources/config/services/resolver.xml`
  - `src/Generator/` → `src/Resources/config/services/generator.xml`
  - `src/Tracker/` → `src/Resources/config/services/tracker.xml`
  - `src/Action/` → `src/Resources/config/services/action.xml`

  Folders that contain no services (`Model/`, `Resources/`, `DependencyInjection/`) get no file. The rule keeps each XML file small and the service surface grep-able.
- **Service IDs are ALWAYS the fully-qualified class name.** No exceptions — this includes factory decorators, event listeners, actions, validators, everything. The only non-FQCN IDs that exist in the container come from `sylius_resource` auto-registration (`{plugin}.factory.{resource}`, `{plugin}.repository.{resource}`, etc.) — those are the decorated targets, not our declarations.
- **Interfaces are registered as `alias` entries.** For a plain service the alias points at the concrete class:
  ```xml
  <service id="Setono\SyliusQRCodePlugin\Generator\QRCodeGenerator">
      <argument type="service" id="..."/>
  </service>
  <service id="Setono\SyliusQRCodePlugin\Generator\QRCodeGeneratorInterface"
           alias="Setono\SyliusQRCodePlugin\Generator\QRCodeGenerator"/>
  ```
  For a Sylius factory decorator the alias points at the **Sylius-registered ID**, because decoration replaces the service at that ID — see "Sylius Factory Decoration" below.
- **Services are private by default** (Symfony 5.4+ default). Mark `public="true"` only when the service must be fetched from the container directly (e.g. controllers invoked as services, console command tags, actions referenced by a router `controller` attribute).
- **Inside the test application (`tests/Application/`)**, autowire/autoconfigure are fine — that's a typical Symfony app, not a reusable bundle.
- **Always declare `decoration-priority` explicitly on any `decorates` service, and use a positive number (default: `100`).** Never rely on Symfony's implicit default. Higher priority = outer decorator (called first). Plugin decorators default to `decoration-priority="100"` so adopting apps that declare their own decorator at the implicit `0` priority automatically slot *inside* our decorator — the plugin's post-processing still runs on top, and the app doesn't have to know about ours to extend the chain. Explicit priority also prevents surprises when multiple decorators collide later.

### Sylius Factory Decoration
When a resource needs a custom factory (to seed defaults, derive a slug, snapshot a value at create time, etc.), **always decorate the Sylius-generated factory** rather than instantiating the entity directly.

- Keep `classes.factory` in the Configuration tree at the generic `Sylius\Component\Resource\Factory\Factory::class`. Sylius will then auto-register `{app_name}.factory.{resource_name}` as a generic factory that creates entities of the configured model class.
- Write a decorator that takes `Sylius\Resource\Factory\FactoryInterface` (the decorated factory) as **the first constructor argument** and delegates `createNew()` to it:
  ```php
  final class WidgetFactory implements WidgetFactoryInterface
  {
      public function __construct(
          private readonly FactoryInterface $decoratedFactory,
          private readonly SomeConfig $config,
      ) {}

      public function createNew(): WidgetInterface
      {
          $widget = $this->decoratedFactory->createNew();
          assert($widget instanceof WidgetInterface);
          $widget->setSomething($this->config->default());
          return $widget;
      }
  }
  ```
- Register the decorator in `services/factory.xml` using Symfony's `decorates` attribute:
  - **Service ID**: the decorator's FQCN (per "Service Definitions in Plugins" above — no exceptions).
  - **`decorates`**: the Sylius-registered factory ID `{plugin_alias}.factory.{resource_name}`.
  - **`decoration-priority`**: explicit value (never implicit). Use `"100"` for plugin factories — see "Service Definitions in Plugins" above.
  - **Inner service reference**: the expanded form `{FQCN}.inner` (not the shorthand `.inner`), so the wiring stays explicit and grep-able.
  - **Interface alias**: points at the Sylius-registered ID (not at our decorator's FQCN) — decoration replaces the service at that ID, so consumers asking for the interface get the decorator transparently.
  ```xml
  <service id="My\Plugin\Factory\WidgetFactory"
           decorates="my_plugin.factory.widget"
           decoration-priority="100">
      <argument type="service" id="My\Plugin\Factory\WidgetFactory.inner"/>
      <argument type="service" id="..."/>
  </service>
  <service id="My\Plugin\Factory\WidgetFactoryInterface"
           alias="my_plugin.factory.widget"/>
  ```
- Consumers inject `WidgetFactoryInterface` (or `my_plugin.factory.widget` by ID) — both resolve to the decorator.
- **Why:** Respects whatever factory class the adopting app may have customized via `classes.factory`, preserves the Sylius resource-ID contract, and keeps the entity construction path consistent across plain creates and decorated creates. Directly `new`ing entities bypasses all of that.

### Doctrine Entity Conventions
- **Plugin-side Doctrine mappings are always `<mapped-superclass>`.** Never `<entity>`. Sylius's Resource Bundle rewrites the mapping to `<entity>` at runtime based on the `classes.model` declared in the resource config. Shipping `<entity>` in the plugin breaks the app's ability to override the model via `sylius_resource.resources.*.classes.model` — which is the primary customisation seam apps rely on. This applies to every plugin entity, including STI subtypes (`ProductRelatedQRCode`, `TargetUrlQRCode`, etc.): each stays a `<mapped-superclass>` on the plugin side; the app-level entities extend them and declare STI (`InheritanceType`, `DiscriminatorColumn`, `DiscriminatorMap`) against the app-level class hierarchy.
- **Nullable property + nullable getter when no sensible default exists.** If a property does not have a sensible literal default (e.g. `false`, `0`, a meaningful enum), declare it `?Type $x = null` and type the getter `?Type`. Do NOT reach for empty-string/placeholder sentinels (`''`, `'unknown'`, `new \DateTimeImmutable()`) just to satisfy a non-nullable type. Example: `protected string $userAgent = '';` → `protected ?string $userAgent = null;` with `getUserAgent(): ?string`.
- **No constructor logic to set defaults.** Doctrine entities should not initialize scalar fields (timestamps, strings, numbers) in the constructor — keep them nullable instead. Collection fields that must never be null (e.g. `ArrayCollection`) are the pragmatic exception.
- **No service injection in entity constructors.** It's a Symfony pattern that Doctrine entities don't take collaborators — state lives on them, behavior lives in services.
- **Use Gedmo Timestampable for `createdAt` / `updatedAt`.** Never set these in the constructor or in a lifecycle callback by hand. Declare the ORM mapping field with `<gedmo:timestampable on="create"/>` (for `createdAt`/`scannedAt`-style fields) or `<gedmo:timestampable on="update"/>` (for `updatedAt`). Enable the listener once via `stof_doctrine_extensions.orm.default.timestampable: true` (the test app already does this). Gedmo populates the field at flush time; the PHP property stays nullable before persistence. This is the standard Sylius pattern — `stof/doctrine-extensions-bundle` is already registered in the test application.
- Database-level NOT NULL constraints are independent of this — the property can be nullable in PHP while the column is `nullable="false"` in the ORM mapping. The caller (factory, controller, listener, or Gedmo listener) is responsible for populating before flush.
- **Don't inject `EntityManagerInterface` (or `ObjectManager`) into services.** Inject the `ManagerRegistry` instead, via `setono/doctrine-orm-trait`'s `ORMTrait`. The trait gives you `getManager($entityOrClass)` / `getRepository($entityOrClass)` and picks the right manager per entity — important once the adopting app has multiple entity managers. Rationale: <https://matthiasnoback.nl/2014/05/inject-the-manager-registry-instead-of-the-entity-manager/>. Register the service with `doctrine` as the registry argument:
  ```xml
  <service id="My\Plugin\Tracker\Foo">
      <argument type="service" id="..."/>
      <argument type="service" id="doctrine"/>
  </service>
  ```

### Assertions
- **Never use PHP's native `assert()` function.** It's bypassable (disabled when `zend.assertions=-1`) and throws `\AssertionError`, which conflates programmer errors with runtime guards.
- **Always use `Webmozart\Assert\Assert`** for runtime invariant checks. Example: `Assert::isInstanceOf($x, Foo::class)` instead of `assert($x instanceof Foo)`. It throws `\InvalidArgumentException` with a descriptive message, cannot be disabled, and gives PHPStan enough information to narrow the type of the checked variable afterwards.
- `webmozart/assert` is a runtime dependency in `composer.json`.
- Tests that previously expected `\AssertionError` must expect `\InvalidArgumentException` instead.

### Testing Requirements
- **Aspire to ~100% code coverage.** Any class under `src/` (including entities, form types, actions, services) should have a matching test class. If a line is genuinely untestable — e.g. a defensive `throw` for a case the type system already rules out, or framework glue with no meaningful seam — document that explicitly in the test or exclude it from coverage with a reason. "This is boilerplate" is not a reason to skip coverage; plain accessors on entities still get tested.
- **New features MUST ship with tests.** Every new feature requires unit tests, and additionally functional tests when it makes sense (HTTP endpoints, admin CRUD, full request/response flows). Pure logic only needs unit tests; framework-integrated behaviour gets both. A feature is not done until the tests are in place.
- Follow the BDD-style naming convention for test methods (e.g., `it_should_do_something_when_condition_is_met`)
- **MUST use Prophecy for mocking** - Use the `ProphecyTrait` and `$this->prophesize()` for all mocks, NOT PHPUnit's `$this->createMock()`
- **Form testing** - Use Symfony's best practices for form testing as documented at https://symfony.com/doc/6.4/form/unit_testing.html
  - Extend `Symfony\Component\Form\Test\TypeTestCase` for form type tests
  - Use `$this->factory->create()` to create form instances
  - When a form depends on a custom child type that needs collaborators (e.g. Sylius's `ProductAutocompleteChoiceType`), register a stub via a `PreloadedExtension` returned from `getExtensions()` — do NOT try to instantiate the real child type with mocked services
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
The plugin includes a test Symfony application at `tests/Application/` for development and manual browser testing. **Sylius Backend credentials after fixtures load:** `sylius` / `sylius`.

**First-time setup** (run from the project root; never `cd` into subdirectories per Path Conventions):

```bash
# 1. MySQL must be running on 127.0.0.1 with root user and no password (see DATABASE_URL in
#    tests/Application/.env — database name is setono_sylius_qr_code_plugin_{env}).
# 2. Create the database and schema for the dev environment.
tests/Application/bin/console --env=dev doctrine:database:create --if-not-exists
tests/Application/bin/console --env=dev doctrine:schema:create

# 3. Load Sylius default fixtures (admin user, products, channels, etc.).
tests/Application/bin/console --env=dev sylius:fixtures:load default --no-interaction

# 4. Symlink bundle assets into tests/Application/public/bundles/.
tests/Application/bin/console --env=dev assets:install tests/Application/public --symlink --relative

# 5. Install and build the front-end bundle (Webpack Encore). Use `yarn --cwd` to stay out of
#    the subdirectory.
yarn --cwd tests/Application install
yarn --cwd tests/Application build

# 6. Start the web server. Either of these works; both run from the project root:
#    Symfony CLI (preferred): `symfony server:start --dir=tests/Application`
#    Built-in Symfony console: `tests/Application/bin/console --env=dev server:run`
```

**Reset the app from scratch** (useful after ORM/schema changes during development):

```bash
tests/Application/bin/console --env=dev doctrine:schema:drop --force --full-database
tests/Application/bin/console --env=dev doctrine:schema:create
tests/Application/bin/console --env=dev sylius:fixtures:load default --no-interaction
```

**Admin URL**: `http://localhost:8000/admin/` (or whatever port `symfony server:start` reports).
**QR Code admin grid**: `http://localhost:8000/admin/qr-codes/`.

### Route File Dispatchers
- **Every file under `src/Resources/config/routes/` must be imported in BOTH `src/Resources/config/routes.yaml` AND `src/Resources/config/routes_no_locale.yaml`.** The two files are parallel dispatchers — `routes.yaml` is used when the host app uses localized URLs (`/{_locale}/...`), `routes_no_locale.yaml` when it doesn't. Forgetting to add an import to `routes_no_locale.yaml` means the route silently vanishes for non-localized stores. Admin and global (non-locale-prefixed) routes appear identically in both; only shop routes differ (the localized dispatcher wraps them in the `/{_locale}` prefix).
- **After changing routes or service wiring, verify the booted kernel sees the change:** `rm -rf tests/Application/var/cache/ && tests/Application/bin/console --env=dev cache:warmup`, then `tests/Application/bin/console --env=dev debug:router | rg <route-name>` to confirm registration. Stale dev cache is the most common reason a "correct-looking" route appears missing.

### Running SQL Against the Test App
- **Always go through `tests/Application/bin/console dbal:run-sql "<query>"`** (add `--force-fetch` to see returned rows — without it, only row counts are printed, so `SHOW INDEX FROM ...` and other `SELECT`-shaped statements appear to return 0 rows). Do NOT reach for a `mysql` / `psql` client binary — it won't be on `$PATH` in every environment, it bypasses Doctrine's configured connection, and it sidesteps the `DATABASE_URL` that the rest of the test app uses.

## Bash Tools Recommendations

Use the right tool for the right job when executing bash commands:

- **Finding FILES?** → Use `fd` (fast file finder)
- **Finding TEXT/strings?** → Use `rg` (ripgrep for text search)
- **Finding CODE STRUCTURE?** → Use `ast-grep` (syntax-aware code search)
- **SELECTING from multiple results?** → Pipe to `fzf` (interactive fuzzy finder)
- **Interacting with JSON?** → Use `jq` (JSON processor). **NEVER use `python`/`python3` for JSON parsing — always `jq`.**
- **Interacting with YAML or XML?** → Use `yq` (YAML/XML processor)

### Path Conventions
- **Never use absolute paths in Bash commands.** Always use relative paths from the project root. For example, `tests/Application/bin/console` rather than `/Users/.../tests/Application/bin/console`.
- **Don't `cd` into subdirectories just to run a binary.** Invoke the binary at its relative path instead — e.g. `tests/Application/bin/console <command>`, not `cd tests/Application && bin/console <command>`. Symfony console commands work from any working directory when invoked by path. Preserving the working directory keeps subsequent commands consistent.

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
