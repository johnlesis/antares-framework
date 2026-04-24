# antares-validation

Lightweight DTO hydration and validation for PHP 8.2+.

Part of the [Antares](https://github.com/johnlesis/antares-framework) framework — but usable standalone in any PHP project.

## Requirements

- PHP 8.2+
- PSR-7 HTTP Message

## Installation

```bash
composer require fatjon-lleshi/antares-validation
```

## Overview

This package provides two things:

- **Hydrator** — takes raw input data and maps it into a typed readonly DTO
- **Validator** — validates DTO properties using PHP attributes

They work together: the Hydrator hydrates the DTO, the Validator validates it. You can also use the Validator independently on any class.

## Defining a DTO

Mark your DTO class with `#[Dto]` and declare properties as readonly constructor parameters:

```php
use Antares\Validation\Attributes\Dto;
use Antares\Validation\Attributes\NotBlank;
use Antares\Validation\Attributes\Email;
use Antares\Validation\Attributes\Min;

#[Dto]
readonly class CreateUserRequest
{
    public function __construct(
        #[NotBlank]
        public string $name,

        #[NotBlank, Email]
        public string $email,

        #[Min(18)]
        public int $age,
    ) {}
}
```

## Hydration

The `Hydrator` takes raw array data and produces a hydrated DTO instance:

```php
use Antares\Hydration\Hydrator;

$hydrator = new Hydrator();

$data = json_decode((string) $request->getBody(), true);

$dto = $hydrator->hydrate(CreateUserRequest::class, $data);
```

If hydration fails (missing required field, wrong type), a `HydrationException` is thrown.

### Strict Mode

Apply `#[Strict]` to the DTO class to reject unknown input fields. Without it, extra fields are silently ignored:

```php
use Antares\Validation\Attributes\Strict;

#[Dto, Strict]
readonly class CreateUserRequest
{
    public function __construct(
        public string $name,
        public int $age,
    ) {}
}
```

Passing `['name' => 'John', 'age' => 25, 'role' => 'admin']` will throw a `HydrationException` because `role` is not a known field.
```

### Nested DTOs

Nested readonly DTOs are hydrated recursively:

```php
#[Dto]
readonly class AddressRequest
{
    public function __construct(
        #[NotBlank]
        public string $city,
        #[NotBlank]
        public string $country,
    ) {}
}

#[Dto]
readonly class CreateUserRequest
{
    public function __construct(
        #[NotBlank]
        public string $name,
        public AddressRequest $address,
    ) {}
}
```

## Validation

The `Validator` inspects a DTO instance and collects all validation errors:

```php
use Antares\Validation\Validator;
use Antares\Validation\Exceptions\ValidationException;

$validator = new Validator();

try {
    $validator->validate($dto);
} catch (ValidationException $e) {
    $errors = $e->getErrors();
}
```

Validation collects all errors before throwing — you get the full list, not just the first failure.

## Available Attributes

### Strings

| Attribute | Description |
|---|---|
| `#[NotBlank]` | Value must not be empty |
| `#[MinLength(n)]` | Minimum string length |
| `#[MaxLength(n)]` | Maximum string length |
| `#[Email]` | Valid email address |
| `#[Url]` | Valid URL |
| `#[Pattern('/regex/')]` | Matches regular expression |
| `#[Alpha]` | Alphabetic characters only |
| `#[AlphaNumeric]` | Alphanumeric characters only |
| `#[Numeric]` | Numeric string |
| `#[HexColor]` | Valid hex color (`#fff` or `#ffffff`) |
| `#[Uuid]` | Valid UUID v4 |
| `#[Phone]` | Valid phone number |
| `#[Ip]` | Valid IP address (v4 or v6) |
| `#[Json]` | Valid JSON string |

### Numbers

| Attribute | Description |
|---|---|
| `#[Min(n)]` | Minimum numeric value |
| `#[Max(n)]` | Maximum numeric value |
| `#[Between(min, max)]` | Value within range (inclusive) |
| `#[Positive]` | Value must be positive |
| `#[Negative]` | Value must be negative |

### General

| Attribute | Description |
|---|---|
| `#[NotNull]` | Value must not be null |
| `#[In([...])]` | Value must be in list |
| `#[InEnum(MyEnum::class)]` | Value must be a valid enum case |
| `#[Size(n)]` | Array must have exactly n elements |
| `#[ArrayOf(type)]` | Array elements must all be of given type |
| `#[Date]` | Valid date string (`Y-m-d`) |
| `#[DateTime]` | Valid datetime string (`Y-m-d H:i:s`) |

### DTO

| Attribute | Description |
|---|---|
| `#[Dto]` | Marks class as a hydratable DTO |
| `#[Strict]` | Enables strict type checking during hydration |

## Custom Validation Attributes

Implement `ValidationAttribute` to create your own:

```php
use Antares\Validation\Attributes\ValidationAttribute;
use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Slug implements ValidationAttribute
{
    public function validate(mixed $value): ?string
    {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $value)) {
            return 'Must be a valid slug.';
        }

        return null;
    }
}
```

Return a string error message if validation fails, `null` if it passes.

## Exceptions

| Exception | Thrown when |
|---|---|
| `HydrationException` | Raw data cannot be mapped to the DTO |
| `ValidationException` | One or more validation rules fail |

```php
use Antares\Hydration\Exceptions\HydrationException;
use Antares\Validation\Exceptions\ValidationException;

try {
    $dto = $hydrator->hydrate(CreateUserRequest::class, $data);
    $validator->validate($dto);
} catch (HydrationException $e) {
    // 400 Bad Request
} catch (ValidationException $e) {
    // 422 Unprocessable Entity
    $errors = $e->getErrors(); // ['email' => 'Must be a valid email address.']
}
```

## Standalone Usage

This package has no dependency on the Antares framework core. You can use it in any PHP 8.2+ project:

```bash
composer require fatjon-lleshi/antares-validation
```

## License

MIT — [Fatjon Lleshi](https://github.com/johnlesis)