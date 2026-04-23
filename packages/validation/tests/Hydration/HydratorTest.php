<?php

namespace Antares\Tests\Hydration;

use Antares\Hydration\Exceptions\HydrationException;
use Antares\Hydration\Hydrator;
use Antares\Validation\Validator;
use Antares\Validation\Exceptions\ValidationException;
use Antares\Validation\Attributes\Dto;
use Antares\Validation\Attributes\Strict;
use Attribute;
use PHPUnit\Framework\TestCase;
use function PHPUnit\Framework\assertTrue;

class HydratorTest extends TestCase
{
    public function test_valid_data_hydration(){
        $validData = [
            'name'   => 'John',
            'age'    => 25,
            'score'  => 9.5,
            'active' => true,
        ];

        $hydrator = new Hydrator(new Validator());

        

        try {
            $hydrator->hydrate(SimpleDto::class, $validData);
            $this->assertTrue(true);
        } catch (ValidationException $e) {
            $this->fail(json_encode($e->getErrors()));
        }
    }

    public function test_valid_nested_dto_hydration(): void
    {
        $nestedData = [
            'title'  => 'My Post',
            'author' => [
                'name'   => 'John',
                'age'    => 25,
                'score'  => 9.5,
                'active' => true,
            ],
        ];

        $hydrator = new Hydrator(new Validator());

        try {
            $result = $hydrator->hydrate(NestedDto::class, $nestedData);
            $this->assertSame('My Post', $result->title);
            $this->assertInstanceOf(SimpleDto::class, $result->author);
            $this->assertSame('John', $result->author->name);
            $this->assertSame(25, $result->author->age);
            $this->assertSame(9.5, $result->author->score);
            $this->assertSame(true, $result->author->active);
        } catch (HydrationException $e) {
            $this->fail($e->getMessage());
        } catch (ValidationException $e) {
            $this->fail(json_encode($e->getErrors()));
        }
    }

    public function test_strict_dto_hydration_fail(): void
    {
        $strictExtraData = [
            'name'  => 'John',
            'extra' => 'not allowed',
        ];

        $hydrator = new Hydrator(new Validator());

        try {
            $result = $hydrator->hydrate(StrictHydrateDto::class, $strictExtraData);
            $this->fail('EXPECTED HYDRATION EXCEPTION');
        } catch (HydrationException $e) {
            $this->assertStringContainsString('extra', $e->getMessage());
        }
    }

    public function test_strict_dto_hydration_pass(): void
    {
        $strictExtraData = [
            'name'  => 'John',
        ];

        $hydrator = new Hydrator(new Validator());

        try {
            $result = $hydrator->hydrate(StrictHydrateDto::class, $strictExtraData);
            $this->assertTrue(true);
        } catch (HydrationException $e) {
            $this->fail($e->getMessage());
        }
    }

    public function test_missing_field_dto_hydration_fail(): void
    {
        $dataWithMissingField = [
            'name' => 'John',
        ];

        $hydrator = new Hydrator(new Validator());

        try {
            $hydrator->hydrate(MissingDto::class, $dataWithMissingField);
            $this->fail('EXPECTED HYDRATION EXCEPTION');
        } catch (HydrationException $e) {
            $this->assertStringContainsString('Missing', $e->getMessage());
        }
    }
}

#[Dto]
readonly class SimpleDto
{
    public function __construct(
        public string $name,
        public int $age,
        public float $score,
        public bool $active,
    ) {}
}

#[Dto]
readonly class NestedDto
{
    public function __construct(
        public string $title,
        public SimpleDto $author,
    ) {}
}

#[Dto]
#[Strict]
readonly class StrictHydrateDto
{
    public function __construct(
        public string $name,
    ) {}
}

#[Dto]
readonly class MissingDto
{
    public function __construct(
        public string $name,
        public int $age,
    ) {}
}

$validData = [
    'name'   => 'John',
    'age'    => 25,
    'score'  => 9.5,
    'active' => true,
];

$stringCastData = [
    'name'   => 'John',
    'age'    => '25',
    'score'  => '9.5',
    'active' => '1',
];

$nestedData = [
    'title'  => 'My Post',
    'author' => [
        'name'   => 'John',
        'age'    => 25,
        'score'  => 9.5,
        'active' => true,
    ],
];

$strictExtraData = [
    'name'  => 'John',
    'extra' => 'not allowed',
];

$missingData = [
    'name' => 'John',
];