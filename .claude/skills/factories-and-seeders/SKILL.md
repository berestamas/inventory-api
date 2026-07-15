---
name: factories-and-seeders
description: "Mandatory rules for model factories and database seeders. Apply this skill whenever creating or modifying an Eloquent model, a migration, a factory, or a seeder — even when the user only asks for the model or the migration: every new model MUST ship with a factory and a seeder registered in DatabaseSeeder, and every schema change MUST update the affected factories and seeders. Also apply when generating test data, demo data, or fixing slow tests/seeding: covers recycle(), for()/has()/hasAttached(), Sequence, state methods, afterCreating hooks, createQuietly/insert bulk paths, fake()->unique() pitfalls, WithoutModelEvents, and seeder ordering/performance."
---

# Factories & Seeders

A model without a factory can't be tested; a factory without a seeder gives you no working local data; a seeder that's not registered in `DatabaseSeeder` never runs. The three are one deliverable, and they are also where careless code hurts most: factories multiply — one wasteful definition runs thousands of times across the test suite and every seed — so reuse (`recycle()`) and query-count awareness here pay for themselves more than anywhere else in the codebase.

**Target state vs. today.** The existing codebase predates parts of this skill: there are no per-model seeders yet, no seeder smoke test (`grep`ping tests/ for `seed(` finds nothing — that absence is a gap to fix on the first seeder change, not a convention to follow), `User` and `Team` lack the `@use HasFactory<...>` generic (larastan is currently red on exactly those lines), and `TeamInvitationFactory` omits the event-generated `code` attribute. Apply the rules in full to everything new; backfill a model's seeder/annotation/definition when you modify that model or its schema for a real reason; never as a side effect of an unrelated change — flag it instead.

## The Mandate

Creating a **model** (or a migration introducing a new table) is not done until all four exist:

1. The model (`app/Models/`, with `use HasFactory;` and a `/** @use HasFactory<PetFactory> */` annotation above the trait — larastan level 7 rejects the bare trait usage (`missingType.generics`), and the generic types `Pet::factory()`'s return).
2. A factory in `database/factories/` covering the default state and meaningful named states.
3. A seeder in `database/seeders/` producing realistic local/demo data through the factory.
4. The seeder registered in `DatabaseSeeder::run()` via `$this->call([...])`, ordered so parents seed before children.

Generate them together — never by hand:

```bash
php artisan make:model Pet -mfs --no-interaction   # model + migration + factory + seeder
```

(`-a` also adds policy, resource controller, and form requests when the model needs the full stack.)

Creating a **migration that changes existing schema** (new column, dropped or renamed column, changed constraint, new table) is not done until every affected factory definition, state, and seeder reflects the new schema — a factory that no longer fills a required column is a broken factory, even if no test fails yet.

Two exceptions, and only these:

- **Pivot models** (`Membership extends Pivot`) get no factory or seeder. Pivot rows are created through the relationship — `$team->members()->attach($user, ['role' => TeamRole::Owner->value])` or `hasAttached()` — matching how the whole codebase does it.
- **Framework infrastructure tables** (cache, jobs, sessions, and similar artisan-published tables) have no model and need nothing. Any *other* new table is presumed to need an Eloquent model — and therefore the factory and registered seeder; creating a model-less domain table requires explicit user approval, it is not a loophole.

## Factory Anatomy

Match the style of the existing factories (`UserFactory`, `TeamFactory`): plain `<?php` header (no `strict_types` in `database/` — unlike `app/`), `@extends Factory<Model>` generic, `fake()` helper (never `$this->faker`), one-line PHPDoc on every method. (`TeamInvitationFactory` is a known rule violation — it omits the event-generated `code` attribute, see the second rule below; fix it when you touch it, don't copy it.)

```php
<?php

namespace Database\Factories;

use App\Enums\PetSpecies;
use App\Models\Pet;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pet>
 */
class PetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->firstName(),
            'species' => PetSpecies::Dog,
            'birth_date' => fake()->dateTimeBetween('-15 years'),
        ];
    }

    /**
     * Indicate that the pet is a cat.
     */
    public function cat(): static
    {
        return $this->state(fn (array $attributes) => [
            'species' => PetSpecies::Cat,
        ]);
    }
}
```

Rules:

- **The default state must produce a valid, complete model standalone** — `Pet::factory()->create()` with zero arguments must work. Required relations default to nested factories (`'team_id' => Team::factory()`); callers then `recycle()` to avoid the cascade (below).
- **The definition must fill every attribute a model event would otherwise fill** (generated slugs, codes, …). Seeders run with `WithoutModelEvents`, so `boot()` `creating` hooks never fire there — `TeamFactory` sets `slug` explicitly for exactly this reason, while `TeamInvitationFactory` omits `code` (NOT NULL, unique, filled only by a `creating` hook) and therefore crashes under any `WithoutModelEvents` seeder. A factory relying on a muted event produces null/invalid rows the moment it's used in a seeder.
- **Every meaningful variation is a named state method** (`unverified()`, `accepted()`, `expired()`, `cat()`), returning `static`, built on `$this->state(fn (array $attributes) => [...])`. States can take parameters (`expiresIn(int $value, string $unit = 'days')`). Don't make callers hand-craft attribute arrays for a variation two tests share — that's a state.
- **Side effects that establish app invariants go in `configure()`** via `afterCreating()` (see `UserFactory`: every user gets a personal team). Use sparingly — each hook runs per created row and multiplies queries; check whether the test/seeder actually needs the invariant before adding one.
- **Memoize expensive computed values in a static property**, the `UserFactory` way: `'password' => static::$password ??= Hash::make('password')`. Hashing once per process instead of per row matters — outside tests bcrypt runs at 12 rounds (tests use `BCRYPT_ROUNDS=4`), so an unmemoized hash makes a 1 000-user seed dramatically slower for no benefit.
- **Per-row values that must differ go in lazy closures or `fake()` calls inside `definition()`** — `definition()` re-runs per instance, so both work; use a closure (`'code' => fn () => Str::uuid()`) when the value must be computed after other attributes resolve.

## Reuse Relationships — `recycle()` Is the Default, Cascades Are the Bug

A nested definition factory (`'team_id' => Team::factory()`) creates **one parent per row**: `Pet::factory()->count(1000)->create()` silently inserts 1 000 teams — and each `User::factory()` cascade adds a personal team, a membership, and an update on top. The fix is `recycle()`, and using it is mandatory whenever the caller already has the related model(s) or creates more than one record:

```php
$team = Team::factory()->create();

// Both pets share $team — zero extra Team rows:
Pet::factory()->count(2)->recycle($team)->create();

// A pool: each pet picks a RANDOM team from the 5 — good for volume, not for exact distribution:
Pet::factory()->count(50)->recycle(Team::factory()->count(5)->create())->create();
```

What `recycle()` actually does (so you use it correctly): it carries a pool of models grouped by class; whenever any factory in the graph — definition attributes, `for()` parents, `has()` children, recursively — needs a model of a pooled class, it picks a **random** pool member instead of creating one. It never affects the model the factory itself builds, and repeated `recycle()` calls merge pools, so you can recycle a team *and* a user in one chain.

The rest of the relationship toolbox — prefer these over manually wiring `*_id` keys:

- `for($teamOrFactory)` / magic `forTeam([...])` — sets the BelongsTo parent; accepts an existing model. Within one `count(n)` batch, `for()` already shares its single resolved parent.
- `has(Pet::factory()->count(3))` / magic `hasPets(3, [...])` — creates children after the parent saves.
- `hasAttached($user, ['role' => TeamRole::Member->value], 'members')` — many-to-many with pivot data; accepts existing models, and (when passing a factory) a list of pivot arrays creates one related model + attach per entry.
- `sequence(...)` / `Sequence` — cycles values with modulo, for **deterministic, even spreads** (exactly half cats, exactly 4 pets per team). Use `Sequence` when a test or demo dataset asserts on distribution; use `recycle($collection)`'s random pick when it only needs volume.

```php
use Illuminate\Database\Eloquent\Factories\Sequence;

$teams = Team::factory()->count(3)->create();

Pet::factory()->count(12)
    ->sequence(fn (Sequence $sequence) => ['team_id' => $teams[$sequence->index % 3]->id])
    ->create(); // exactly 4 pets per team, reproducibly
```

## Performance Playbook

Factories run thousands of times per suite; seeders create thousands of rows. Know what each call costs:

| Path | Queries | Events / hooks | Returns models |
|---|---|---|---|
| `count(n)->create()` | n INSERTs (+ children + hooks) | yes | yes |
| `count(n)->createQuietly()` | n INSERTs (+ children + hooks) | model events muted — `afterCreating` and `has()` children still run | yes |
| `insert()` | **1 INSERT**\* | no model events, no `afterCreating`, no `has()` children (`afterMaking` runs) | no (void) |

\* One INSERT *for the model itself*. Nested definition parents (`'team_id' => Team::factory()`) are still resolved with a full `create()` **per row, events included** — `Pet::factory()->count(1000)->insert()` without `recycle()` is 1 bulk pet INSERT plus 1 000 team creates. `recycle()` is just as mandatory on the `insert()` path; `withoutParents()` skips parent resolution entirely when you supply the FK yourself.

- Reach for `Factory::insert()` when seeding large volumes of rows whose IDs you don't need back; stay with `create()` when hooks/children must run. Casts are applied either way. `createQuietly()` is not a query-count optimization — it mutes observers/listeners (and whatever queries *they* run), nothing else.
- `fake()->unique()` throws an `OverflowException` after 10 000 retries — `unique()->word()`-style small domains explode at volume. For large counts, build uniqueness from the index (`sequence(fn ($s) => ['email' => "user-{$s->index}@example.com"])`) instead.
- An `afterCreating` hook costing one query turns a 10k-row seed into 10k extra queries. When bulk-seeding past such a hook deliberately, use `withoutAfterCreating()` and establish the invariant in bulk yourself — but never as a way to skip an invariant the data actually needs. The concrete case in this codebase: `User::factory()->count(500)->withoutAfterCreating()->create()` skips 500 personal-team cascades; only do it when the seeded users genuinely don't need teams.
- In tests, create the minimum graph the assertion needs. When several tests in a file share a parent, create it once in `beforeEach()` and `recycle()` it in each test — one shared `$team` beats a fresh cascade per model, and is the difference between a 2-second and a 30-second suite.

## Seeders

One seeder per domain/model (`TeamSeeder`, `PetSeeder`) in `database/seeders/`; `DatabaseSeeder` orchestrates — its own body holds nothing but the handful of fixed, known records (the dev login user) and the `call()` list:

```php
public function run(): void
{
    if (User::query()->where('email', 'test@example.com')->doesntExist()) {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }

    $this->call([
        TeamSeeder::class,   // parents first —
        PetSeeder::class,    // children consume what parents created
    ]);
}
```

A child seeder reuses what earlier seeders created — query it and `recycle()`; never re-create parents:

```php
<?php

namespace Database\Seeders;

use App\Models\Pet;
use App\Models\Team;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PetSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed pets for every existing non-personal team.
     */
    public function run(): void
    {
        $teams = Team::query()->where('is_personal', false)->get();

        Pet::factory()
            ->count(50)
            ->recycle($teams)
            ->create();
    }
}
```

Rules:

- **Every seeder uses `WithoutModelEvents`** (matching `DatabaseSeeder`) — observers and `boot()` hooks are pure overhead during seeding. Consequence, worth repeating: the factory definition must supply event-generated attributes itself, or the seeder writes broken rows.
- **Order by foreign-key dependency** in `DatabaseSeeder::run()`. A seeder must be able to assume everything before it in the list exists.
- **Fixed, known records** (the dev login user, default settings) are created idempotently with the guarded-factory pattern shown above (`doesntExist()` check, then `Model::factory()->create()`), so re-running `db:seed` on an existing database doesn't crash on unique constraints. Don't reach for a raw `firstOrCreate()` here — it bypasses the factory's defaults *and* its `afterCreating` invariants (a `firstOrCreate`d dev user would have no personal team). Random volume data doesn't need the guard; it's regenerated via `migrate:fresh --seed`.
- **Seed realistic shapes, deterministic where it matters**: enough rows to expose pagination and N+1 problems (dozens, not three), `Sequence` for spreads the UI depends on, states for the interesting variants (`unverified`, `expired`, `trashed`).
- Run with `php artisan db:seed` (or `--class=PetSeeder`); full rebuild via `php artisan migrate:fresh --seed` — the latter wipes the database, so never run it against a database you didn't create.

## Proving It Works

The mandate is honor-system unless tests enforce it. Two cheap tests make it mechanical (in-memory SQLite keeps both fast):

```php
arch('models have factories')
    ->expect('App\Models')
    ->toUseTrait('Illuminate\Database\Eloquent\Factories\HasFactory')
    ->ignoring('App\Models\Membership'); // pivots are exempt

test('database seeder runs', function (): void {
    $this->seed();

    expect(Team::query()->count())->toBeGreaterThan(0)
        ->and(Pet::query()->count())->toBeGreaterThan(0);
});
```

- The smoke test must call `$this->seed()` **with no arguments** — that is what catches a seeder you forgot to register in `DatabaseSeeder` — and must assert a nonzero count for every model your new or changed seeder produces. Add an assertion line whenever you add a seeder.
- `$this->seed(PetSeeder::class)` in other tests is for data setup; it is never a substitute for the registration smoke test.
- The arch test catches a model shipped without its factory; extend its `ignoring()` list only for pivot models.

In tests needing seeded data, prefer `$this->seed(PetSeeder::class)` over re-deriving the setup, and use factory states before hand-rolled attribute arrays. The `app-architecture` QA gate applies to `database/` too: larastan analyzes it at level 7 (the `@extends Factory<Model>` and `@use HasFactory<...>` generics are load-bearing), pint formats it, and the smoke test makes `php artisan test` the proof that seeding works. For factories and seeders themselves, these two tests *are* the required coverage — `app-architecture`'s Unit + Feature rule governs `app/` changes, not `database/`.
