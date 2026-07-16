---
name: laravel-expressive-idioms
description: "Expressive, lesser-known Laravel idioms that make backend code state its intent — apply whenever writing, reviewing, or refactoring ANY PHP in this application: controllers, Actions, Query classes, FormRequests, models, policies, seeders, commands, or tests. Trigger especially on the smells it exists to replace: comparing raw foreign keys (`$pet->user_id === $user->id`), passing `->id`/`->uuid` into route()/attach()/associate-style calls, `count() > 0` existence checks, `first()` where exactly one row is expected, `whereHas` closures with a single condition, hand-rolled enum casting of request input, `env()` fallbacks for required secrets, and orderBy('created_at') chains. Covers pass-the-model-not-the-id (route(), attach(), updateExistingPivot(), whereBelongsTo(), whereKey(), relation is(), associate()/dissociate(), modelKeys(), toQuery()), intent-shaped retrieval (exists/doesntExist, sole, value/soleValue, pluck with keys, firstOr/findOr, firstWhere, latest/oldest, whereRelation, withWhereHas, whereAny/whereAll/whereNone, upsert), the #[Scope] attribute convention for this app's first scopes, enums end-to-end (Rule::enum()->only()/except(), $request->enum()/enums(), collect()->mapInto(), AsEnumCollection, enum route binding), typed request/config getters, Env::getOrFail(), Model::shouldBeStrict() guardrails, prose helpers (to_route, tap, throw_if, rescue, filled/blank, str(), Number), and the saveQuietly audit-trail caution. Complements laravel-best-practices (which owns performance, security, caching) — this skill owns readability."
---

# Laravel Expressive Idioms — Say What You Mean

Laravel almost always has a sentence-shaped method for the thing you are about to hand-wire. Code that compares foreign-key integers, plucks ids to feed back into queries, or counts rows to learn whether any exist is telling the reader *how* instead of *what*. These idioms collapse that mechanical code into intent — and several of them are load-bearing in this app, not just prettier.

Boundaries with sibling skills:

- `laravel-best-practices` owns performance, security, caching, and architecture rules. Some idioms below are *also* faster (`exists()`, `upsert()`), but they earn their place here by being clearer.
- `app-architecture` still decides *where* code lives (Controller → FormRequest → DTO → Action, Query classes, spatie/laravel-query-builder for client-driven filtering). This skill decides what the individual lines look like.
- Consistency first: use these forms in new code and on any line you are already editing. Don't mass-rewrite untouched neighbors just to modernize them.

## 1. Pass the Model, Not the ID

Eloquent methods that accept a key almost always accept the model itself and resolve the right key for you. In this application this is not cosmetic — **route keys are uuids** (`HasUuid::getRouteKeyName()`), so manual key-plumbing is a live bug risk:

```php
route('pets.show', $pet->id);   // ❌ BUG here: URL gets the integer id, binding expects uuid → 404
route('pets.show', $pet->uuid); // ⚠️ works, but hardcodes the route-key decision at every call site
route('pets.show', $pet);       // ✅ Laravel asks the model for its route key
```

The same "hand the model over" shape applies across the board:

```php
// Pivot operations — attach/detach/sync/updateExistingPivot parse models
$user->roles()->attach($role->id);                        // ⚠️
$user->roles()->attach($role);                            // ✅

$team->members()->updateExistingPivot($user->id, [...]);  // ⚠️
$team->members()->updateExistingPivot($user, [...]);      // ✅

// Primary keys from a collection
$pets->pluck('id');                                       // ⚠️ stringly-typed
$pets->modelKeys();                                       // ✅ "the primary keys", whatever column that is

// Key match inside a relationship query
$user->pets()->where('id', $pet->id)->exists();           // ⚠️
$user->pets()->whereKey($pet)->exists();                  // ✅ whereKey() accepts a model
```

### `whereBelongsTo()` — query by parent without naming the FK

```php
Pet::where('user_id', $user->id)->get();        // ⚠️ couples call sites to the column name
Pet::whereBelongsTo($user, 'owner')->get();     // ✅ relationship resolves the FK
```

It guesses the relation from the given model's class name (`$user` → `user`), so name it explicitly when the relation differs — `Pet`'s relation is `owner`, so the second argument is required there. It also accepts a whole collection of parents (`whereBelongsTo($users)` → `WHERE user_id IN (...)`), and `whereMorphedTo()` is the morph equivalent.

### `is()` / `isNot()` — identity checks that read as the question

```php
$pet->user_id === $user->id;     // ⚠️ FK plumbing; silently true for the wrong pair of types
$pet->owner()->is($user);        // ✅ no query — BelongsTo compares the FK it already holds
$petA->is($petB);                // ✅ same key + table + connection
```

Call `is()` on the relationship *method* (`owner()`), not the property — `BelongsTo`, `HasOne`, `MorphTo`, and `MorphOne` compare without loading the related model. This is the natural vocabulary for ownership checks in Policies.

### `associate()` / `dissociate()` — say the relationship changed

```php
$pet->update(['user_id' => $user->id]);      // ⚠️ writes a column
$pet->owner()->associate($user)->save();     // ✅ re-parents the pet, and the loaded relation stays in sync

$pet->update(['user_id' => null]);           // ⚠️
$pet->owner()->dissociate()->save();         // ✅
```

### `toQuery()` — bulk-operate on models you already have

```php
User::whereIn('id', $users->pluck('id'))->update(['active' => true]);  // ⚠️
$users->toQuery()->update(['active' => true]);                          // ✅ one UPDATE, no id round-trip
```

Mind that `toQuery()`/`update()` bypasses model events — see §8 before using it on audited models.

## 2. Ask the Question You Mean

Retrieval methods exist for nearly every *shape* of question. Using the general-purpose one and post-processing hides the question from the reader.

```php
Pet::where(...)->count() > 0;        // ⚠️ "how many?" when you meant "any?"
Pet::where(...)->exists();           // ✅ (and doesntExist() for the negation)

Team::where('slug', $slug)->first(); // ⚠️ tolerates duplicates silently
Team::where('slug', $slug)->sole();  // ✅ exactly one — throws MultipleRecordsFoundException
                                     //    when a uniqueness invariant is broken, and
                                     //    ModelNotFoundException when missing

User::where('uuid', $uuid)->first()->email;      // ⚠️ loads a model for one column
User::where('uuid', $uuid)->value('email');      // ✅ one column, one row (soleValue() = strict twin)

Pet::query()->get()->keyBy('uuid')->map->name;   // ⚠️ hydrates models for a lookup table
Pet::query()->pluck('name', 'uuid');             // ✅ pluck takes a key column too

Pet::orderBy('created_at', 'desc')->get();       // ⚠️
Pet::latest()->get();                            // ✅ latest()/oldest(), optionally latest('updated_at')

Pet::where('microchip', $chip)->first();         // ⚠️ builder ceremony for one condition
Pet::firstWhere('microchip', $chip);             // ✅
```

### When "not found" deserves more than a 404

`findOrFail`/`firstOrFail` abort; `findOr`/`firstOr` let you express the fallback inline instead of `if ($x === null)` scaffolding:

```php
$pet = Pet::findOr($id, fn () => throw new PetRetiredException($id));
$team = Team::where('slug', $slug)->firstOr(fn () => Team::createDefaultFor($user));
```

### Relationship existence without closure ceremony

```php
// One condition? whereRelation says it in one line:
Pet::whereHas('vaccinations', fn ($q) => $q->where('expires_at', '<', now()))->get(); // ⚠️
Pet::whereRelation('vaccinations', 'expires_at', '<', now())->get();                  // ✅

// Filter AND eager-load on the same condition — one closure, so the two can't drift apart:
Pet::whereHas('vaccinations', $due)->with(['vaccinations' => $due])->get();  // ⚠️ duplicated constraint
Pet::withWhereHas('vaccinations', $due)->get();                              // ✅
```

### Multi-column matching: `whereAny` / `whereAll` / `whereNone`

```php
// ⚠️ nested orWhere closure soup
Pet::where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('breed', 'like', "%{$term}%"));

// ✅ the SQL grouping is handled for you
Pet::whereAny(['name', 'breed', 'microchip'], 'like', "%{$term}%")->get();
```

`whereAll` = every column matches; `whereNone` = no column matches (e.g. exclusion filters).

### Write-or-update in bulk

```php
Vaccination::upsert($rows, uniqueBy: ['pet_id', 'vaccine'], update: ['expires_at']);
```

One statement instead of a loop of `updateOrCreate()`. Like all bulk writes it skips model events — §8 applies.

## 3. Scopes: the `#[Scope]` Attribute

No model in this app defines scopes yet. When the first one lands, use the attribute form — not the legacy `scopePopular()` naming convention, which larastan can't type and readers must know the prefix-stripping magic for:

```php
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Scope the query to pets of the given species.
 *
 * @param  Builder<Pet>  $query
 */
#[Scope]
protected function ofSpecies(Builder $query, PetSpecies $species): void
{
    $query->where('species', $species);
}

// Usage: Pet::ofSpecies(PetSpecies::Dog)->get();
```

A boolean parameter with `when()` gives one scope that reads correctly in both directions, instead of two half-duplicated scopes:

```php
#[Scope]
protected function spayed(Builder $query, bool $spayed = true): void
{
    $query->where('is_spayed', $spayed);
}

Pet::spayed()->get();
Pet::spayed(false)->get();
```

Worth knowing alongside the attribute:

- **Higher-order `orWhere`** chains scopes without a closure: `User::popular()->orWhere->active()->get()`.
- **Pending attributes**: a scope built with `$query->withAttributes(['hidden' => true])` both filters queries *and* seeds those attributes onto models created through it — the filter and the default can't disagree.
- **Placement**: scopes are for backend-internal constraints reused across Query classes and Actions. Client-driven filtering/sorting stays with spatie/laravel-query-builder per `app-architecture`.

## 4. Enums End-to-End

This app is enum-rich (`PetSpecies`, `TeamRole`, `PetAnxietyLevel`, …). Keep values inside the enum type from the HTTP boundary inward — every stringly-typed gap is a place invalid state slips through.

```php
// Validation — including subset rules, which read like the authorization decision they are:
'species' => ['required', Rule::enum(PetSpecies::class)],
'role'    => ['required', Rule::enum(TeamRole::class)->except(TeamRole::Owner)],  // can't grant ownership
'level'   => [Rule::enum(PetAnxietyLevel::class)->only([PetAnxietyLevel::None, PetAnxietyLevel::Mild])],

// Retrieval — typed, no ::from() / ::tryFrom() ceremony:
$species = $request->enum('species', PetSpecies::class);          // ?PetSpecies
$speciesList = $request->enums('species', PetSpecies::class);     // PetSpecies[] from an array input

// Collections of enum input:
$permissions = $request->collect('permissions')->mapInto(TeamPermission::class);
if ($permissions->contains(TeamPermission::AddMember)) { ... }

// A JSON column holding many enum values:
protected function casts(): array
{
    return ['preferred_species' => AsEnumCollection::of(PetSpecies::class)];
}

// Route segments — implicit backed-enum binding 404s invalid values before your code runs:
Route::get('/pets/species/{species}', ...);   // PetSpecies $species in the action
```

In this app, request *bodies* flow FormRequest → laravel-data DTO, and the DTO's typed properties already cast enums — don't duplicate that. `$request->enum()`/`enums()`/`collect()` shine where DTOs aren't in play: query-string parameters, middleware, and artisan commands.

## 5. Typed Input and Config — Fail at the Boundary

An unvalidated `$request->input()` or `config()` read returns `mixed`, and a wrong assumption surfaces three calls later as a confusing type error. The typed getters move the failure to the line that made the assumption.

```php
$request->boolean('remember');                 // "1", "true", "on" → true — no manual normalizing
$request->integer('page', 1);
$request->date('valid_until');                 // Carbon|null, with optional format + tz args
$request->string('search')->trim()->lower();   // Stringable — chain instead of nesting functions
$request->whenFilled('breed', fn ($breed) => ...);

// The same getters exist on validated input — ValidatedInput shares the trait:
$request->safe()->collect();
$request->safe()->enum('species', PetSpecies::class);
```

Config reads get the same treatment — these throw on a type mismatch instead of flowing `null` onward:

```php
config()->string('app.name');
config()->integer('session.lifetime');
config()->boolean('services.reverb.tls');
config()->array('mail.mailers');
```

And inside `config/*.php` files (the only place `env()` belongs), required secrets should refuse to boot rather than default to `null` and fail at first use, mid-request:

```php
'secret' => env('REVERB_APP_SECRET'),                 // ⚠️ null-defaults; explodes later, elsewhere
'secret' => Env::getOrFail('REVERB_APP_SECRET'),      // ✅ missing var = immediate, named failure
```

Use `getOrFail` for values with no sane default (API keys, signing secrets); keep `env('X', $default)` for genuinely optional knobs.

## 6. Guardrails: Strict Models in Development

`Model::shouldBeStrict()` turns three classes of silent wrongness into loud development-time exceptions:

- lazy loading (the N+1 you didn't notice) — `preventLazyLoading()`
- assigning an attribute that isn't fillable and being silently ignored — `preventSilentlyDiscardingAttributes()`
- reading an attribute that doesn't exist, e.g. the `$pet->nam` typo, which otherwise just yields `null` — `preventAccessingMissingAttributes()`

It belongs in `AppServiceProvider::configureDefaults()`, next to the existing `DB::prohibitDestructiveCommands()`:

```php
Model::shouldBeStrict(! app()->isProduction());
```

Production stays lenient on purpose: an overlooked lazy load in a rarely-hit path should degrade to a slow query there, not a 500. If this call isn't present yet, add it the next time that file is touched — and expect it to surface real latent issues in tests the first time.

## 7. Helpers That Read Like Prose

```php
// Redirects — on the routes that do redirect, prefer the terse helper over the facade:
return redirect()->route('pets.show', $pet);   // ⚠️
return to_route('pets.show', $pet);            // ✅

// tap() — do the side effect, return the subject; ideal for Actions that must return the model:
$pet->update($data);                           // ⚠️ update() returns bool,
return $pet;                                   //    forcing a two-statement dance
return tap($pet)->update($data);               // ✅ updates, returns the Pet

// Guard clauses as single declarative lines:
throw_if($team->isPersonal(), CannotDeletePersonalTeamException::class);
throw_unless($vaccination->pet()->is($pet), VaccinationMismatchException::class);
abort_unless($request->hasValidSignature(), 403);   // HTTP-shaped guards only —
                                                    // real authorization stays in Policies

// rescue() — "allowed to fail" without try/catch scaffolding (reports by default; report: false to skip):
$avatar = rescue(fn () => $gravatar->fetch($user->email), default: null, report: false);

// filled() / blank() — one vocabulary instead of the isset/empty/trim dance:
if (filled($request->header('X-Team'))) { ... }

// Fluent strings and human formatting — chains instead of nested calls and sprintf gymnastics:
str($pet->breed)->squish()->title();
Number::fileSize($upload->getSize());      // "2 MB"
Number::forHumans(1_500_000);              // "1.5 million"

// Higher-order collection messages — when the closure would only forward to one method/property:
$pets->each->touch();
$users->map->name;
$members->every->hasVerifiedEmail();
```

## 8. Quiet Mutations — Know What Silence Costs Here

`saveQuietly()` / `updateQuietly()` / `deleteQuietly()` suppress model events, and read innocently. In this application model events are load-bearing: `RecordsActivity` writes the audit trail from them (see `activity-logging`), and mutations are expected to broadcast (see `realtime-features`). "Quietly" therefore means **no audit log entry** for that change.

Legitimate: seeders and backfills (pair with `WithoutModelEvents`), fixing denormalized counters. Wrong: any user-facing flow. The same caution applies to the bulk paths that never fire events in the first place — `upsert()`, `toQuery()->update()`, `Query\Builder::update()`.

## How to Apply

1. Writing new code: reach for these forms by default; the left-hand `⚠️` patterns should not appear in fresh diffs.
2. Editing existing code: upgrade the lines you touch; leave untouched neighbors alone.
3. Reviewing: each `⚠️` pattern above is a review comment waiting to happen — point at this skill.
4. Unfamiliar method? Verify the signature with `search-docs` before assuming — several of these gained parameters in recent versions.
5. The QA gate (`rector`, `pint`, `larastan`, tests) still decides done — these idioms are all larastan-friendly, including `#[Scope]`.
