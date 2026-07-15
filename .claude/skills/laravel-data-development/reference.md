# laravel-data v4 — API Reference

A condensed, corrected digest of [spatie/laravel-data v4](https://spatie.be/docs/laravel-data/v4/introduction), scoped to what this codebase uses. The `SKILL.md` holds the house rules; this file is the lookup table. Project conventions are flagged with **▸**.

---

## 1. Creating data objects

```php
use Spatie\LaravelData\Data;

class CreateUserData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $role = null,
    ) {}
}
```

`from()` is polymorphic — array, Eloquent model, request, JSON string, another Data object, or a mix:

```php
CreateUserData::from(['name' => 'Ada', 'role' => null]);
CreateUserData::from($user);                 // reads matching properties off the model
CreateUserData::from($request->validated()); // an array — no validation runs
UserData::optional($maybeNullModel);         // returns null instead of an empty object
```

**Magic creation methods** — any `public static` method named `from*` (but not exactly `from`) is used by `from()` when the argument type matches:

```php
public static function fromModel(User $user): self { /* explicit mapping */ }
```

▸ Input DTOs are usually built straight from the validated array (or `$request->getData()`); use a `fromModel()` magic method for non-trivial mapping from a model. API responses are shaped by Eloquent API Resources, not laravel-data (`app-architecture`).

**Base classes**: `Data` (full-featured input), `Resource` (output only — skips validation/authorization), `Dto` (stripped-down). ▸ This app uses `Data` for input; it does **not** use laravel-data `Resource` for output — outbound API responses go through Laravel's built-in Eloquent API Resources (`app-architecture`).

## 2. Nesting & collections

```php
class AlbumData extends Data
{
    public function __construct(
        public string $title,
        public ArtistData $artist,                 // nested object
        #[DataCollectionOf(SongData::class)]
        public array $songs,                       // collection — see typing note
    ) {}
}
```

Type collections with **`#[DataCollectionOf(SongData::class)]`** or a `/** @var SongData[] */` docblock. Required for correct casting **and** correct serialization (a bare `array` is not transformed into `SongData` instances).

```php
SongData::collect($eloquentCollection);   // array|Collection|paginator in → same type out
SongData::collect(Song::paginate());      // paginator preserved
```

## 3. Optional vs nullable vs default

| Declaration | Missing input behaviour | Serialized output |
|---|---|---|
| `?string $x` | becomes `null` | key present, `null` |
| `string\|Optional $x = new Optional` | key omitted entirely | key absent |
| `string $x = 'def'` | uses default | key present |

- `Spatie\LaravelData\Optional` = "this field was not supplied; leave the target untouched." ▸ `UpdateUserData::$role` uses it so an omitted role leaves existing roles alone while an explicit `null` clears them.
- Defaults are **not validated**. Properties with a default cannot also be a constructor-promoted parameter that you intend to validate via inference.
- `SongData::factory()->withoutOptionalValues()->from(...)` converts `Optional` to `null`.

## 4. Computed values

```php
use Spatie\LaravelData\Attributes\Computed;

#[Computed]
public string $fullName;   // set in the constructor from other properties
```

Cannot be passed in the payload (throws `CannotSetComputedValue` unless the `ignore_exception_when_trying_to_set_computed_property_value` feature is on). Not re-evaluated when dependencies change.

## 5. Name mapping

```php
use Spatie\LaravelData\Attributes\{MapInputName, MapName, MapOutputName};
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName('record_company')] public string $recordCompany;       // input only
#[MapOutputName(SnakeCaseMapper::class)] public string $recordCompany; // output only
#[MapName(SnakeCaseMapper::class)] public string $recordCompany;       // both directions
```

Mappers: `SnakeCaseMapper`, `CamelCaseMapper`, `KebabCaseMapper`, `StudlyCaseMapper`, `LowerCaseMapper`, `UpperCaseMapper`, `ProvidedNameMapper`. Class-level attributes map every property; global defaults via `config/data.php` `name_mapping_strategy`.

▸ **Use `#[MapInputName(SnakeCaseMapper::class)]`, never `#[MapName]`, on input DTOs** — `MapName` also remaps serialized output to snake_case, breaking the camelCase contract the API output relies on. Most payloads here are camelCase end-to-end and need no mapper. When filtering (`only`/`except`) or writing validation rules, always use the **original** property name, not the mapped alias.

## 6. Casts

```php
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\{DateTimeInterfaceCast, EnumCast};

#[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d')] public CarbonImmutable $date;
#[WithCast(EnumCast::class)] public Status $status;
```

Custom cast implements `Spatie\LaravelData\Casts\Cast::cast(DataProperty $p, mixed $v, array $props, CreationContext $ctx)`. Value objects implement `Castable::castUsing()`. Register app-wide defaults under `config/data.php` `casts` (keyed by type) instead of annotating every property. Note: casts never receive `null` (null is preserved as-is).

## 7. Transformers (object → array/JSON)

```php
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

#[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')] public Carbon $date;
```

Global transformers under `config/data.php` `transformers` (the published config already maps `DateTimeInterface`, `Arrayable`, and `BackedEnum`). `toArray()` recurses; `all()` returns properties without transformation. `max_transformation_depth` guards circular references.

## 8. Validation

▸ **This app disables laravel-data validation** (`config/data.php` → `validation_strategy => ValidationStrategy::Disabled`). Validation is always the FormRequest's job (`app-architecture`). The material below is reference only — do **not** start validating via Data objects.

- Rules are auto-inferred from property types (`string` → `required|string`, `?string` → `nullable|string`, non-nullable `array` → `required|array`). This inference is exactly why creating from a request under the default `OnlyRequests` strategy can reject payloads the FormRequest allows.
- Manual rules: `public static function rules(): array`. Validation attributes: `#[Required]`, `#[Max(255)]`, `#[Email]`, `#[Exists('roles','name')]`, `#[Unique(...)]`, etc. `#[WithoutValidation]` skips a property. `#[MergeValidationRules]` merges manual with inferred.
- `validateAndCreate()` / `validate()` validate explicitly; `from()` does not (unless the strategy says otherwise). `authorize()`, `messages()`, `attributes()`, `redirect()`, `withValidator()` hooks exist when a Data object *is* the validator — not used here.

## 9. Serialization (laravel-data mechanics)

```php
SongData::from($song)->toArray();   // recursive transform
SongData::from($song)->toJson();
SongData::collect(Song::all());     // → array of serialized objects
SongData::empty();                  // template with null/default values
```

Data objects are `Arrayable` + `Responsable`, so they *can* serialize to JSON on their own. ▸ In this app they do **not** shape API responses, though — outbound responses go through Laravel's built-in Eloquent API Resources (allowlists, paginators, leak tests → `app-architecture`). These methods are for the odd internal serialization, not the HTTP output layer.

### Lazy properties

```php
use Spatie\LaravelData\Lazy;

songs: Lazy::whenLoaded('songs', $album, fn () => SongData::collect($album->songs)), // only when eager-loaded
extra: Lazy::when(fn () => $includeExtra, fn () => ExtraData::from($model)),          // only when the condition holds
```

Class/property attributes: `#[AutoLazy]`, `#[AutoWhenLoadedLazy]`. `->include('songs')`, `->exclude(...)`, `->only(...)`, `->except(...)` (and `*When`/`*Permanently` variants) control inclusion; `allowedRequestIncludes()` whitelists query-string-driven includes. A lazy property stays out of the serialized payload until it is explicitly included.

### Wrapping

`->wrap('data')` / `withoutWrapping()`, or global `config/data.php` `wrap`. ▸ Off by default here (responses are unwrapped); `toArray()`/`toJson()` never wrap regardless.

## 10. Eloquent casting

```php
protected function casts(): array
{
    return [
        'settings' => SettingsData::class,
        'songs'    => DataCollection::class.':'.SongData::class,
    ];
}
```

Append `:default` / `,default` to hydrate from defaults when the column is null. Abstract Data + `enforceMorphMap()` for polymorphic stored data. `,encrypted` for encrypted columns.

## 11. Get data from a class quickly — `WithData`

```php
use Spatie\LaravelData\WithData;

// Model:        protected string $dataClass = UserData::class;  →  $user->getData()
// FormRequest:  protected function dataClass(): string { return CreateUserData::class; }  →  $request->getData()
```

`getData()` = `dataClass()::from($this)`. ▸ Bind the trait generic for larastan: `/** @use WithData<CreateUserData> */` above `use WithData;`. ▸ With `FormRequestNormalizer` enabled, `getData()` builds from `$request->validated()`. See SKILL.md for the validation-strategy gotcha.

## 12. Pipeline, normalizers, factories, commands

- **Pipeline**: `from()` runs payload through pipes (authorize → map names → fill route params → validate → defaults → cast). Override via `public static function pipeline(): DataPipeline`. Rarely needed here.
- **Normalizers** (`config/data.php` `normalizers`) turn input into an array: `ModelNormalizer`, `FormRequestNormalizer` (▸ enabled — `$request->validated()`), `ArrayableNormalizer`, `ObjectNormalizer`, `ArrayNormalizer`, `JsonNormalizer`. First non-null wins; magic `from*` methods take precedence.
- **Factory**: `SongData::factory()->withoutValidation()->withoutMagicalCreation()->withCast(...)->from(...)` for per-call overrides.
- **Commands**: `php artisan make:data NameData` (move to `app/Data/{Domain}/` afterwards); `php artisan data:cache-structures` caches reflection in production (auto-disabled in tests).

## 13. Quick gotcha list

1. Type every collection (`#[DataCollectionOf]` / docblock) — for casting and serialization both.
2. `Optional` omits the key; `?T` keeps it as `null`. They serialize to different JSON.
3. Defaults are not validated.
4. `#[MapName]` remaps output too — use `#[MapInputName]` for input-only.
5. Creating from a request validates under the default strategy — ▸ disabled here on purpose.
6. Wrapping never affects `toArray()`/`toJson()`.
7. Casts never receive `null`.
