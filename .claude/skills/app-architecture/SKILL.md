---
name: app-architecture
description: "Mandatory architecture rules for ALL backend work in this application. Apply this skill whenever creating or modifying controllers, routes, business logic, validation, authorization, Eloquent queries, listing/filtering/search endpoints, save flows, DTOs, or backend tests — even for small changes or bug fixes. Defines the required request lifecycle (Controller → FormRequest → DTO → Action), the Action pattern, spatie/laravel-data DTOs instead of arrays, Policies for every authorization decision, Eloquent Query Classes instead of repositories, spatie/laravel-query-builder for every client-driven query, database transactions, the Pipeline pattern for multi-step saves, the testing rules (Unit + Feature always, positive AND negative cases for every behavior, exhaustive dataset-driven FormRequest validation tests), and the QA gate (rector, pint, larastan, tests) that must be green before any work counts as done."
---

# Application Architecture Guidelines

These are the non-negotiable conventions for this codebase. They exist so that every feature looks the same: a reader who has seen one Action, one Query class, or one DTO has seen them all. Design patterns are a shared vocabulary — when code is organized into named, single-purpose units (an Action, a Query, a Pipe, a Policy), intent is readable from the file tree alone, units are testable in isolation, and changes stay local. A pattern is a means, not a goal: if a class name would just restate what Eloquent already says, you don't need the class.

**Target state vs. today.** The examples below show the *target* architecture. Some directories they reference (`app/Queries/`, `app/Pipelines/`, `app/Data/{Domain}/`) don't exist yet — create them when you first need them. Parts of the existing code predate these rules (the real `CreateTeam` takes scalars, `app/Data/` holds plain readonly classes). Apply the rules in full to all new code; migrate an existing file to conform when you modify it for a real reason. Never refactor untouched, non-conforming code as a side effect of an unrelated change — flag it instead.

## Where Does This Code Go?

| You are writing… | It goes in… | Never in… |
|---|---|---|
| Business logic / state changes | `app/Actions/{Domain}/` | Controllers, FormRequests, Models |
| Validation rules | `app/Http/Requests/` (FormRequest) | Controllers, Actions |
| Authorization decisions | `app/Policies/` | Controllers, FormRequests, inline `abort_unless` |
| Typed input crossing layer boundaries | `app/Data/` (spatie/laravel-data) | Associative arrays |
| API response shape / serialization | `app/Http/Resources/` (Eloquent API Resources) | Inline `->map()`, whole-model output, output DTOs |
| Reusable / important queries | `app/Queries/` | Controllers, fat Models, repositories |
| Client-driven filtering/sorting/pagination | `spatie/laravel-query-builder` | Hand-rolled `when()` chains in controllers |
| Multi-step save flows | `app/Pipelines/{Flow}/` | One giant Action |

## The Request Lifecycle

Every state-changing request flows through the same layers, each with exactly one responsibility:

```
Route
 └─ Controller            orchestrates only — no logic
     ├─ FormRequest       validates input — nothing else
     ├─ Policy            authorizes — via Gate::authorize()
     ├─ Data (DTO)        carries typed input across boundaries
     └─ Action            executes the business logic
         ├─ Query class   reads/writes the database
         └─ DB::transaction wraps multiple writes
```

```php
public function store(SaveTeamRequest $saveTeamRequest, CreateTeam $createTeam): JsonResponse
{
    Gate::authorize('create', Team::class);

    $team = $createTeam->handle(
        $saveTeamRequest->user(),
        CreateTeamData::from($saveTeamRequest->validated()),
    );

    return TeamResource::make($team)
        ->response()
        ->setStatusCode(Response::HTTP_CREATED);
}
```

That is a complete controller method. If a controller method grows beyond authorize → build DTO → call action → respond, the extra lines belong in an Action, a Query class, or a Data object.

## Controllers Stay Thin

A controller method may: resolve the FormRequest, authorize via a Policy, construct a Data object, call one Action (or Query class), and return a response. It may not: contain business branching, run Eloquent queries, map models to arrays inline, or write to the database. Inline `->map(fn (...) => [...])` blocks building a response payload are queries-plus-transformation hiding in a controller — move the query into a Query class and the response shape into an API Resource.

Two narrow exceptions: trivial single-model lookups belong to route model binding (not inline `findOrFail()`), and the declarative `QueryBuilder::for(...)` composition for listing endpoints may live in the controller method (see below) — it is request-to-query whitelisting, not business logic, and its base builder still comes from a Query class.

## Actions

Actions live in `app/Actions/{Domain}/`, are named verb-first after the business operation (`CreateTeam`, `TransferTeamOwnership`, `CancelInvitation`), and expose a single public `handle()` method:

```php
declare(strict_types=1);

namespace App\Actions\Teams;

use App\Data\Teams\CreateTeamData;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTeam
{
    /**
     * Create a new team and add the user as owner.
     */
    public function handle(User $user, CreateTeamData $data): Team
    {
        return DB::transaction(function () use ($user, $data) {
            $team = Team::create([
                'name' => $data->name,
                'is_personal' => $data->isPersonal,
            ]);

            $team->memberships()->create([
                'user_id' => $user->id,
                'role' => TeamRole::Owner,
            ]);

            $user->switchTeam($team);

            return $team;
        });
    }
}
```

Rules that keep Actions clean:

- **One action, one business operation.** If you need section comments inside `handle()`, or the method does several unrelated things, split it — either into smaller actions injected via the constructor, or into a Pipeline (see below).
- **Inputs are domain types**: Eloquent models, Data objects, enums, scalars. Outputs are models or Data objects. Arrays never enter or leave an action — the associative-array shape at the `Team::create([...])` call is the framework edge and stays inside the action. The one exception is a vendor contract that dictates the signature (Fortify's `CreatesNewUsers::create(array $input)`): keep the vendor signature and convert to a Data object on the first line inside.
- **Compose by injection.** An action that needs another action receives it through constructor promotion (`public function __construct(private CreateTeam $createTeam) {}`), never instantiates it with `new`. Type-hint the concrete action class; Laravel's container resolves it automatically, no binding required.
- **Actions are reusable entry points.** Controllers, Artisan commands, jobs, and pipeline pipes all call the same action — business logic written once works everywhere.

Interfaces for actions are **optional**, not required. Type-hint the concrete action class directly — controllers, pipes, jobs, and other actions all consume it that way, and the container autowires it with no binding. Introduce a contract (in `app/Contracts/{Domain}/`, named as a verb phrase like `CreatesTeams`, bound in `AppServiceProvider::register()`) only when an action genuinely needs more than one implementation or a swappable seam — not as a reflex for every action. Don't add a one-implementation interface just to have one; it's indirection without payoff. When you do want to fake an action in a test, `$this->mock(CreateTeam::class)` works on the concrete class.

## FormRequests: Validation Only

A FormRequest contains `rules()`, optionally `messages()`/`attributes()`, optionally an `authorize()` that only delegates to a policy, and at most an `after()` hook limited to request-local validation (`DeleteTeamRequest`'s name-confirmation check is the canonical example). Nothing else.

- **No business logic** — no DB writes, no transformation, no conditionals deciding what happens next. If you are tempted to put a `prepareForValidation()` full of logic or an `after()` hook that reaches beyond the request and its route parameters, that logic belongs in an Action or a custom `Rule` object (`app/Rules/`).
- **No authorization logic** — `authorize()` is a single delegation: `return Gate::allows('delete', $this->route('team'));`. The decision itself lives in the Policy.

```php
class SaveTeamRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new TeamName],
        ];
    }
}
```

## Authorization: Always a Policy

Every authorization decision — in a route, a job, a command, or an action — goes through a Policy method, no exceptions.

- Policies live in `app/Policies/`, one per model, auto-discovered. Methods take `(User $user, Team $team): bool` and delegate to permission enums where they exist (`$user->hasTeamPermission($team, TeamPermission::UpdateTeam)`).
- Invoke with `Gate::authorize('update', $team)` in the controller, or `Gate::allows(...)` inside `FormRequest::authorize()`. Pick one place per endpoint, not both.
- Route middleware like `EnsureTeamMembership` handles a *precondition* (membership) at the route-group level; it does not replace the Policy — the endpoint still authorizes its specific ability (`update`, `delete`, …).
- An inline ownership check like `abort_unless($user->belongsToTeam($team), 403)` **is** an authorization decision — it must be a policy method (`view`, `switch`, …), even when the rule is one line. Add the policy method if it doesn't exist yet; centralizing it means the rule has one home when it inevitably gains conditions.

## DTOs: Arrays Never Cross Boundaries

Input data passed between layers — controller → action, action → action — travels in `spatie/laravel-data` objects, never associative arrays. An array tells the reader nothing about its keys, types, or nullability; a Data class is typed, autocompleted, refactorable, and checked by larastan. (Outbound API responses are **not** DTOs — they are shaped by Laravel's built-in Eloquent API Resources; see [Output: Eloquent API Resources](#output-eloquent-api-resources) below.)

DTOs live in `app/Data/{Domain}/` and use readonly promoted constructor properties in camelCase. They extend `Spatie\LaravelData\Data`:

```php
declare(strict_types=1);

namespace App\Data\Teams;

use Spatie\LaravelData\Data;

class CreateTeamData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly bool $isPersonal = false,
    ) {
    }
}
```

Conventions:

- **DTOs are input-only.** They are named after the operation (`CreateTeamData`, `UpdateMemberRoleData`) and created from validated input: `CreateTeamData::from($request->validated())`. Note that `Data::from()` does **not** validate — validation is the FormRequest's job, which has already run. Use `#[MapInputName(SnakeCaseMapper::class)]` when request keys are snake_case and properties are camelCase (not `MapName`, which would also remap serialized *output* to snake_case). A DTO is never used to shape an API response — that is the API Resource's job (see below).
- The existing plain `readonly` classes in `app/Data/` (`UserTeam`, `TeamPermissions`) predate this rule. When you touch one, change its base class to `Spatie\LaravelData\Data`; moving it into a `{Domain}/` subfolder and adding the `Data` suffix is a deliberate rename refactor (call sites included), not something to do in passing. New classes follow `app/Data/{Domain}/{Name}Data.php` from the start.
- Arrays remain legal only at framework edges: `rules()` arrays, config, and the attribute array given to Eloquent `create()`/`update()` *inside* an action or query class.

## Output: Eloquent API Resources

Outbound API responses are shaped by Laravel's built-in [Eloquent API Resources](https://laravel.com/docs/eloquent-resources) — **not** DTOs. The Resource is the single place that decides which model columns become JSON, so response shaping stays out of controllers and Actions and no `Spatie\LaravelData\Resource` read-model is introduced for output.

Resources live in `app/Http/Resources/`, are named `{Thing}Resource` (`TeamResource`, `TeamMemberResource`), and extend `Illuminate\Http\Resources\Json\JsonResource`. Create them with `php artisan make:resource {Thing}Resource --no-interaction`.

```php
declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Team
 */
class TeamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'is_personal' => $this->is_personal,
            'members' => TeamMemberResource::collection($this->whenLoaded('memberships')),
        ];
    }
}
```

- **Explicit allowlist, never `parent::toArray()`.** List every field by hand so no column can leak into the response by accident — the field-minimization guarantee the old output DTOs gave now lives here.
- **Return straight from the controller.** `return TeamResource::make($team);`. For a non-200 status, chain `->response()->setStatusCode(...)`, as the `store()` example above does with `Response::HTTP_CREATED`.
- **Collections and paginators**: `TeamMemberResource::collection($members)` wraps a collection or a paginator (pagination `meta`/`links` are preserved). Add a dedicated `ResourceCollection` subclass only when the collection needs its own top-level metadata.
- **Conditional relations**: `$this->whenLoaded('memberships')` keeps a relation out of the payload until it has been eager-loaded — never trigger a lazy query from inside `toArray()`.
- **Leak test, always**: a Feature test asserts the serialized payload contains exactly the allowed keys (`assertExactJson`, or `assertJsonStructure` plus an explicit missing-key assertion) — no column leaks, no accidental over-exposure. Every Resource ships with one.
- The `@mixin` PHPDoc tells larastan the `$this->` accessors resolve against the wrapped model, keeping the analysis green without casts.

## Query Classes — Never Repositories

The repository pattern is **forbidden** in this codebase. A repository abstracts Eloquent behind generic CRUD methods, which throws away Eloquent's expressiveness and accumulates loosely related methods behind one interface. We don't hide the database; we organize the queries that matter.

When a query stops being an implementation detail — it appears in more than one place, coordinates several optional filters, carries important eager loading, backs a report/export/job, or deserves its own tests — extract it into a Query class following the [Eloquent Query Classes pattern](https://wendelladriel.com/blog/eloquent-query-classes-pattern):

- Lives in `app/Queries/`, named after the business question, not the SQL: `PendingInvitationsQuery`, `TeamMembersQuery`.
- A `readonly` class with a single public `handle()` method; inputs are typed parameters.
- Returns whatever the callers need: a paginator, a collection, an aggregate — or an `Illuminate\Database\Eloquent\Builder` when callers should decide how to consume it (one paginates, another `->get()`s, another counts).

```php
declare(strict_types=1);

namespace App\Queries;

use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Database\Eloquent\Builder;

readonly class PendingInvitationsQuery
{
    /**
     * Pending (not yet accepted) invitations for the given team.
     *
     * @return Builder<TeamInvitation>
     */
    public function handle(Team $team): Builder
    {
        return TeamInvitation::query()
            ->whereBelongsTo($team)
            ->whereNull('accepted_at')
            ->latest();
    }
}
```

Query classes compose: inject one into another and extend its builder. Don't create one for a trivial one-off lookup or where a local scope reads better — the pattern must reduce friction, not add ceremony. Trivial single-model lookups belong to route model binding; small reusable predicates belong to model local scopes, called from an Action or Query class — neither belongs inline in a controller.

## Client-Driven Queries: spatie/laravel-query-builder Only

Any query shaped by the client — search, filtering, sorting, pagination, includes — goes through `spatie/laravel-query-builder`. Never hand-roll `$request->input('sort')` switches or `when($request->filled(...))` chains in a controller: QueryBuilder gives a stable URL contract (`filter[name]=`, `sort=-created_at`), whitelisting (non-whitelisted `filter`/`sort`/`include` parameters are rejected with HTTP 400), and zero boilerplate.

Combine it with Query classes — the Query class provides the base builder, QueryBuilder layers the user-controlled parts on top:

```php
$invitations = QueryBuilder::for($pendingInvitations->handle($team))
    ->allowedFilters([
        AllowedFilter::partial('email'),
        AllowedFilter::exact('role'),
    ])
    ->allowedSorts(['email', 'created_at'])
    ->defaultSort('-created_at')
    ->paginate(25)
    ->appends($request->query());
```

Rules of thumb:

- Whitelist explicitly: strings are partial filters; use `AllowedFilter::exact()` for ids/enums, `::scope()` for local scopes, `::callback()`/custom `Filter` classes for anything complex.
- Always set `defaultSort()`; `cursorPaginate()` additionally requires a unique-column sort to be deterministic.
- Always `->appends($request->query())` so pagination links keep the active filters.
- Wrap the paginator in an API Resource collection before returning it (`TeamMemberResource::collection($paginator)`) — the pagination `meta`/`links` are preserved and each item is passed through the Resource's allowlist.

## Multi-Step Saves: the Pipeline Pattern

When a save flow consists of many sequential steps — each its own action-sized unit — don't grow one giant Action. Use [Laravel's Pipeline](https://medium.com/insiderengineering/understanding-laravel-pipelines-9717f5d58286) (the Chain of Responsibility pattern, same mechanism as middleware): the payload flows through small, ordered, independently testable pipes.

Pipes live in `app/Pipelines/{Flow}/`, each with `handle($payload, Closure $next)`. The payload is a Data object (or a small context object) — never an array. Each pipe does one thing, usually by delegating to an action or query class, then calls `$next($payload)`; not calling `$next` short-circuits the chain.

```php
declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Contracts\Onboarding\OnboardsNewOwners;
use App\Data\Onboarding\OnboardingData;
use App\Pipelines\Onboarding\AttachDefaultPermissions;
use App\Pipelines\Onboarding\CreateOwnerAccount;
use App\Pipelines\Onboarding\CreatePersonalTeam;
use App\Pipelines\Onboarding\SendWelcomeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Pipeline;

class OnboardNewOwner implements OnboardsNewOwners
{
    /**
     * Run the full owner onboarding flow.
     */
    public function handle(OnboardingData $data): OnboardingData
    {
        return DB::transaction(fn () => Pipeline::send($data)
            ->through([
                CreateOwnerAccount::class,
                CreatePersonalTeam::class,
                AttachDefaultPermissions::class,
                SendWelcomeNotification::class,
            ])
            ->thenReturn());
    }
}
```

Wrap the whole pipeline in `DB::transaction()` whenever its pipes write to the database — a failure in pipe three must roll back pipes one and two. Pipes themselves are wiring: reference them by concrete class in `through()` — same as the actions and query classes they delegate to.

## Database Transactions

More than one database write in a single operation → `DB::transaction()`, no exceptions. The transaction belongs in the Action (or pipeline wrapper), not the controller, so every caller of the action gets the same atomicity. Use `lockForUpdate()` inside the transaction when reading a row you are about to update under concurrency.

## Testing: Both Layers, Always

Every backend change ships with **both** a Unit test and a Feature test. A change without its tests does not pass the QA gate.

- **Unit tests** (`tests/Unit/`) exercise a class directly: an Action's `handle()`, a Query class's filters, a Data object's mapping, a Policy method, a single pipe. When a unit test touches the database, add `uses(TestCase::class, RefreshDatabase::class);` to the file — `Pest.php` binds the Laravel `TestCase` (and `RefreshDatabase`) only to `tests/Feature`, so `uses(RefreshDatabase::class)` alone would run against plain PHPUnit and fail to boot the app.
- **Feature tests** (`tests/Feature/`) exercise the HTTP flow: `$this->actingAs($user)->postJson(route('teams.store'), [...])`, asserting the status code, the JSON response body (`assertJson`, `assertJsonPath`, `assertJsonStructure`), database state (`assertDatabaseHas`), and authorization (`assertForbidden` for the negative case — every policy rule gets a negative test).
- Follow the suite's style: `test('teams can be created', function (): void { ... });` — no `describe`/`it`, factories for models, named routes via `route()`, never literal URLs. Test files follow the suite's existing conventions (they currently omit `strict_types`; larastan does not analyze `tests/`).

### Positive and Negative, Always

Every behavior is tested from both sides — a suite that only proves success is half a suite:

- **Positive**: the happy path succeeds — correct status code, state persisted (`assertDatabaseHas`), correct JSON response body.
- **Negative**: every way the behavior must refuse — invalid input (validation error), unauthorized user (`assertForbidden`), missing or foreign resource (404). A negative test asserts the rejection **and** that nothing changed: the suite's own `test('team deletion requires name confirmation')` is the bar — it asserts `assertSessionHasErrors('name')` *and* that the team still exists undeleted. A 403 with a silently-applied side effect is a bug a status assertion alone never catches.

This applies at both layers, not just over HTTP: a Policy unit test asserts the `false` branch as well as the `true` one; an Action unit test covers its failure path (exception, refused state change), not only success.

### FormRequest Validation: Exhaustive, With Datasets

Every FormRequest gets a dedicated validation test driven by [Pest datasets](https://pestphp.com/docs/datasets) — one named case per rule per field, so the test enumerates the whole rule set instead of sampling it. These are **Feature tests**: always post through the named route so the full HTTP path (middleware, FormRequest resolution, redirect-with-errors) is exercised — never unit-test `rules()` by invoking the `Validator` directly.

```php
test('team creation rejects invalid names', function (mixed $name): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('teams.store'), ['name' => $name])
        ->assertSessionHasErrors('name');
})->with([
    'null' => [null],
    'empty' => [''],
    'too long' => [str_repeat('a', 256)],
    'reserved name (TeamName rule)' => ['admin'],
]);
```

(A `'not a string' => [['an', 'array']]` case belongs in this dataset too — today it surfaces a real bug: `TeamName::validate()` string-casts non-strings without an `is_string` guard and the rules lack `bail`, so the request 500s instead of 422ing. That is exactly the class of bug these tests exist to catch — fix the rule, then add the case.)

- **Cover everything**: every rule of every field — `required`, type, `max`, each custom `Rule` object, and each check in an `after()` hook (`DeleteTeamRequest`'s name-confirmation) — appears as at least one named case. When a rule is added to the FormRequest, its dataset case is added in the same change; a rule without a case is untested code.
- **Multi-field requests need a valid baseline payload**: a helper returns the fully valid body and each case overrides exactly one field (`[...validPetPayload(), 'name' => $name]`). Posting only the field under test makes negatives pass for the wrong reason — every other `required` field errors too — and makes positive boundary cases unwritable.
- **Valid boundaries get a positive dataset too** (`'max length' => [str_repeat('a', 255)]`, `'unicode' => ['Ékezetes Név']`), asserted with `assertValid(['name'])` against the baseline payload — boundary off-by-ones live exactly here. (`assertInvalid(['name'])` is the equivalent negative-side assertion; the suite currently uses `assertSessionHasErrors`, both are fine.)
- **Share datasets across endpoints that share the request**: `SaveTeamRequest` validates both `store` and `update`, so define the cases once with `dataset('invalid team names', [...])` in `tests/Datasets/` (auto-discovered) and `->with('invalid team names')` in both tests.
- Named cases (`'too long' => [...]`) are mandatory — a failing `dataset #3` tells you nothing, a failing `'too long'` is the diagnosis.
- Plain dataset values resolve before the app boots; wrap model- or container-dependent values in closures (bound datasets): `'taken slug' => [fn () => Team::factory()->create()->slug]`. Give bound parameters a concrete type (`function (string $slug)`) — Pest skips closure resolution for `mixed`/`Closure`/`callable` parameters when a case supplies multiple arguments.
- Each dataset case runs as its own test (`beforeEach` re-runs per case, so it saves nothing here) — keep the per-case setup graph minimal, per the `factories-and-seeders` skill.

## Definition of Done: the QA Gate

While iterating, run the minimum needed — a single filtered test (`php artisan test --compact --filter=...`) is fine for the inner loop. But work is **not finished** — not "done except for…", not "I'll fix the style later" — until all four tools pass in this order:

```bash
vendor/bin/rector                         # 1. refactors first, since it rewrites code
vendor/bin/pint --dirty --format agent    # 2. then formatting, cleaning up after rector
vendor/bin/phpstan analyse                # 3. then static analysis (larastan, level 7)
php artisan test --compact                # 4. then the test suite
```

If any step modifies files or fails, fix the cause and re-run from step 1 until a full pass is clean. Never silence larastan with `@phpstan-ignore` or lower the level to get green; fix the types. A red gate means the development is not acceptable — full stop.

## Clean Code Baseline

- `declare(strict_types=1);` in every PHP file under `app/`; explicit parameter and return types everywhere, including closures.
- Descriptive, intention-revealing names: `isRegisteredForDiscounts`, not `discount()`; named arguments for boolean/optional parameters at call sites (`handle($user, $data, isPersonal: true)`).
- Small units: short methods, early returns over nested conditionals, one level of abstraction per method.
- Backed enums with TitleCase cases over magic strings; behavior on the enum (`label()`, `permissions()`) via `match`.
- One-line PHPDoc descriptions on methods; array-shape generics (`@return Collection<int, Team>`); no inline comments narrating what the next line does.
- Shared behavior in `app/Concerns/` traits; custom validation in `app/Rules/`.
- Delete dead code instead of commenting it out — git remembers.
