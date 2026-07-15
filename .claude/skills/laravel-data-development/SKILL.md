---
name: laravel-data-development
description: "spatie/laravel-data conventions and tooling for this application — apply whenever creating or modifying a Data/Resource class, wiring a FormRequest or Model to a DTO via the WithData trait, or touching casts, name mappers, computed/optional properties, or Eloquent Data casts. Covers the 'get data from a class quickly' pattern (WithData trait + $request->getData() / $model->getData()), and the laravel-data mechanics the other skills lean on but never teach (::from() creation and magic fromX() methods, ::collect(), name mappers, #[WithCast] casts, Optional vs nullable, #[Computed], collection typing, Eloquent Data casting, make:data). Critically documents WHY this app sets validation_strategy => Disabled and enables FormRequestNormalizer so getData() defers to the FormRequest and never re-validates against the DTO's auto-inferred rules. Does NOT cover where DTOs live, the request lifecycle, or API response output — which uses Laravel's built-in Eloquent API Resources, not laravel-data (see app-architecture). Pairs with app-architecture and uuid-model-identifiers."
---

# laravel-data Development

`spatie/laravel-data` is the backbone of every typed **input** boundary in this app: input DTOs (`extends Data`). API responses are **not** laravel-data — they are shaped by Laravel's built-in Eloquent API Resources (see `app-architecture`). This skill owns the **mechanics and tooling**. It deliberately does not repeat its neighbour:

- **Where DTOs live, how they're named, the Controller → FormRequest → DTO → Action lifecycle, the Eloquent API Resource output layer (allowlists, collections/paginators, leak tests)** → `app-architecture`.

Read that for the *what/where*. Read this for the *how*: getting data out of a class quickly, and the casting / mapping / optionality toolkit.

📖 Full API digest: [`reference.md`](reference.md) (read it with a sub-agent when you need an exact attribute or method name).

## Get data from a class quickly — `WithData`

Instead of `CreateUserData::from($request->validated())` at every call site, attach the `WithData` trait and let the FormRequest (or Model) own which DTO it produces.

### On a FormRequest (the house default)

```php
use App\Data\Admin\CreateUserData;
use Spatie\LaravelData\WithData;

class StoreUserRequest extends FormRequest
{
    use ProfileValidationRules;
    /** @use WithData<CreateUserData> */
    use WithData;

    public function rules(): array { /* … */ }

    /**
     * @return class-string<CreateUserData>
     */
    protected function dataClass(): string
    {
        return CreateUserData::class;
    }
}
```

```php
// Controller — the validated payload resolves into the typed DTO:
public function store(StoreUserRequest $request, CreateUser $createUser): RedirectResponse
{
    $createUser->handle($request->getData(), $request->user());
    // …
}
```

- The `/** @use WithData<CreateUserData> */` line is **mandatory** — it binds the trait's generic so `getData()` is typed as `CreateUserData` and larastan (level 7) stays green. Without it `getData()` is `mixed` and fails the type check.
- On a **Model**: `protected string $dataClass = UserData::class;` then `$user->getData()`.

### The gotcha that bites — validation strategy

`getData()` calls `SomeData::from($this)` with the *request object*. By laravel-data's default `ValidationStrategy::OnlyRequests`, **creating a Data object from a request re-runs validation against the DTO's auto-inferred rules** — which routinely conflict with the FormRequest's. Real example: `SaveRoleData`'s `array $permissions = []` infers `required`, rejecting the empty array the FormRequest deliberately allows.

This app resolves it the architecturally-correct way in `config/data.php` — **the FormRequest is the only validator**:

```php
// config/data.php
'validation_strategy' => ValidationStrategy::Disabled->value, // DTOs never auto-validate
'normalizers' => [
    ModelNormalizer::class,
    FormRequestNormalizer::class, // getData() builds from $request->validated(), not raw input
    // …
],
```

Consequences you must respect:

- **Never put validation responsibility on a Data object.** No `#[WithoutValidation]` workarounds, no relying on inferred rules — validation is the FormRequest's job (`app-architecture`). `Data::from()` and `getData()` only *carry* already-validated data.
- `FormRequestNormalizer` is what makes `getData()` read `validated()` rather than the raw input — keep it enabled.

## The mechanics toolkit

The bits the other skills assume you know. Full detail in [`reference.md`](reference.md); the house rules:

- **Creating**: `Data::from($arrayOrModelOrRequest)` is polymorphic. Add a magic `public static function fromModel(User $u): self` for non-trivial mapping from a model into an input DTO. `Data::collect($iterable)` for collections; `Data::optional($maybeNull)` to get `null` instead of an empty object.
- **Name mapping**: use `#[MapInputName(SnakeCaseMapper::class)]` when request keys are snake_case but properties are camelCase. **Never** `#[MapName]` on input DTOs — it also remaps the serialized *output* to snake_case, breaking the camelCase contract the API response exposes. Reach for a mapper only when keys genuinely differ; our payloads are camelCase end-to-end, so most DTOs need none.
- **Casts**: `#[WithCast(DateTimeInterfaceCast::class)]` for dates, `EnumCast` for enums; register app-wide defaults under `'casts'` in `config/data.php` rather than annotating every property. Write a custom `Cast` only for value objects.
- **Optional vs nullable** — they mean different things and serialize to different JSON:
  - `?string $x` → key always present, value may be `null` → JSON `"x": null`.
  - `string|Optional $x` (default `new Optional`) → key *absent* from the serialized payload when not provided. Use `Optional` for "field was not supplied, leave it untouched" (e.g. `UpdateUserData::$role`); use `?T` for "explicitly null".
- **Computed & defaults**: `#[Computed]` for a derived property set in the constructor; plain `= …` defaults are fine but are **not** validated. Don't make a computed property a constructor parameter.
- **Collections**: type them with `#[DataCollectionOf(SongData::class)]` or a `@var SongData[]` docblock — required for correct casting and correct serialization.
- **Eloquent casting**: store a Data object on a model with `protected function casts(): array { return ['settings' => SettingsData::class]; }`.
- **Scaffolding**: `php artisan make:data SomethingData` (then move it into `app/Data/{Domain}/` per `app-architecture`).

## Definition of done

- [ ] Output DTO shape exposes `uuid` (not `id`) and the right `?:` / `| null` for each optional/nullable property.
- [ ] FormRequest→DTO uses `WithData` + `$request->getData()` with the `/** @use WithData<…> */` binding.
- [ ] Backend QA gate green (`app-architecture`): `vendor/bin/rector`, `vendor/bin/pint --dirty`, `vendor/bin/phpstan analyse --memory-limit=1G`, `php artisan test --compact`.

## See also

- `app-architecture` — DTO location, naming, the request lifecycle, and the Eloquent API Resource output layer (allowlists / collections / leak tests); the input-DTO `::from($request->validated())` form that `getData()` is shorthand for.
- `uuid-model-identifiers` — why API Resources expose `uuid`, never the integer `id`.
