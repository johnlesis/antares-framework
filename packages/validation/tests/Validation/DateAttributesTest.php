<?php

declare(strict_types=1);

namespace Antares\Tests\Validation\Dates;

use Antares\Validation\Attributes\DateTimeImmutable;
use Antares\Validation\Attributes\Dto;
use Antares\Validation\Exceptions\ValidationException;
use Antares\Validation\Validator;
use PHPUnit\Framework\TestCase;

class DateAttributesTest extends TestCase
{
    public function test_datetime_immutable_attribute_autoloads(): void
    {
        $this->assertTrue(class_exists(DateTimeImmutable::class));
    }

    public function test_datetime_immutable_accepts_matching_format(): void
    {
        (new Validator())->validate(new Meeting('2026-11-21 18:30:00', '21/11/2026'));
        $this->assertTrue(true);
    }

    public function test_datetime_immutable_rejects_other_formats(): void
    {
        try {
            (new Validator())->validate(new Meeting('2026-11-21', '2026-11-21'));
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame([
                'startsAt' => ['The value must be a valid datetime in format Y-m-d H:i:s.'],
                'day'      => ['The value must be a valid datetime in format d/m/Y.'],
            ], $e->getErrors());
        }
    }
}

#[Dto]
final class Meeting
{
    public function __construct(
        #[DateTimeImmutable]
        public string $startsAt,
        #[DateTimeImmutable('d/m/Y')]
        public string $day,
    ) {}
}
