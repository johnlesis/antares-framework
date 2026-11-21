<?php

declare(strict_types=1);

namespace Antares\Tests\Hydration\Input;

use Antares\Hydration\Exceptions\HydrationException;
use Antares\Hydration\Hydrator;
use Antares\Validation\Attributes\Dto;
use Antares\Validation\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Bad input from the client must become a HydrationException (400), never a PHP
 * error or a silently wrong value. Mistakes in the DTO class itself are a
 * LogicException (hidden 500).
 */
class HydratorInputTest extends TestCase
{
    private Hydrator $hydrator;

    protected function setUp(): void
    {
        $this->hydrator = new Hydrator(new Validator());
    }

    // Booleans

    /** @return array<string, array{mixed, bool}> */
    public static function booleans(): array
    {
        return [
            'json true'      => [true, true],
            'json false'     => [false, false],
            'int 1'          => [1, true],
            'int 0'          => [0, false],
            'string "true"'  => ['true', true],
            'string "false"' => ['false', false],
            'string "1"'     => ['1', true],
            'string "0"'     => ['0', false],
            'string "yes"'   => ['yes', true],
            'string "no"'    => ['no', false],
            'string "on"'    => ['on', true],
            'string "off"'   => ['off', false],
        ];
    }

    #[DataProvider('booleans')]
    public function test_booleans_are_parsed_not_cast(mixed $input, bool $expected): void
    {
        $dto = $this->hydrator->hydrate(Flags::class, ['active' => $input]);
        $this->assertSame($expected, $dto->active);
    }

    // Wrong shapes from the client: 400

    /** @return array<string, array{class-string, array<mixed>, string}> */
    public static function badInput(): array
    {
        return [
            'array for a string'      => [Person::class, ['name' => ['a', 'b']], 'Parameter name must be a string.'],
            'object for a string'     => [Person::class, ['name' => ['first' => 'a']], 'Parameter name must be a string.'],
            'bool for a string'       => [Person::class, ['name' => true], 'Parameter name must be a string.'],
            'null for a string'       => [Person::class, ['name' => null], 'Parameter name must not be null.'],
            'array for an int'        => [Counter::class, ['count' => [1]], 'Parameter count must be a valid integer.'],
            'array for a float'       => [Price::class, ['amount' => [1.5]], 'Parameter amount must be a valid float.'],
            'array for a bool'        => [Flags::class, ['active' => [true]], 'Parameter active must be a valid boolean.'],
            'word for a bool'         => [Flags::class, ['active' => 'maybe'], 'Parameter active must be a valid boolean.'],
            'string for a nested dto' => [Order::class, ['address' => 'Main St'], 'Parameter address must be an object.'],
            'null for a nested dto'   => [Order::class, ['address' => null], 'Parameter address must not be null.'],
            'string for an array'     => [Tagged::class, ['tags' => 'a,b'], 'Parameter tags must be an array.'],
        ];
    }

    /** @param array<mixed> $data */
    #[DataProvider('badInput')]
    public function test_wrong_shapes_are_rejected_with_a_clear_message(string $class, array $data, string $message): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage($message);
        $this->hydrator->hydrate($class, $data);
    }

    // Accepted shapes

    public function test_numbers_are_accepted_for_strings(): void
    {
        $this->assertSame('42', $this->hydrator->hydrate(Person::class, ['name' => 42])->name);
        $this->assertSame('1.5', $this->hydrator->hydrate(Person::class, ['name' => 1.5])->name);
    }

    public function test_null_is_accepted_for_nullable_fields(): void
    {
        $this->assertNull($this->hydrator->hydrate(MaybeOrder::class, ['address' => null])->address);
        $this->assertNull($this->hydrator->hydrate(MaybePerson::class, ['nickname' => null])->nickname);
    }

    // Mistakes in the DTO class: LogicException, not a client error

    public function test_dto_without_constructor_is_a_developer_error(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must have a constructor');
        $this->hydrator->hydrate(NoConstructor::class, []);
    }

    public function test_class_field_without_dto_attribute_is_a_developer_error(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('NotADto is not marked with #[Dto]');
        $this->hydrator->hydrate(HasNonDto::class, ['thing' => ['x' => 'y']]);
    }
}

#[Dto]
final class Flags
{
    public function __construct(public bool $active) {}
}

#[Dto]
final class Person
{
    public function __construct(public string $name) {}
}

#[Dto]
final class MaybePerson
{
    public function __construct(public ?string $nickname) {}
}

#[Dto]
final class Counter
{
    public function __construct(public int $count) {}
}

#[Dto]
final class Price
{
    public function __construct(public float $amount) {}
}

#[Dto]
final class Tagged
{
    public function __construct(public array $tags) {}
}

#[Dto]
final class Address
{
    public function __construct(public string $street) {}
}

#[Dto]
final class Order
{
    public function __construct(public Address $address) {}
}

#[Dto]
final class MaybeOrder
{
    public function __construct(public ?Address $address) {}
}

#[Dto]
final class NoConstructor {}

final class NotADto
{
    public function __construct(public string $x) {}
}

#[Dto]
final class HasNonDto
{
    public function __construct(public NotADto $thing) {}
}
