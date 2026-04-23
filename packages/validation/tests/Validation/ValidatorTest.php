<?php

namespace Antares\Tests\Validation;

use Antares\Validation\Validator;
use Antares\Validation\Exceptions\ValidationException;
use Antares\Validation\Attributes\Email;
use Antares\Validation\Attributes\Min;
use Antares\Validation\Attributes\Max;
use Antares\Validation\Attributes\MinLength;
use Antares\Validation\Attributes\MaxLength;
use Antares\Validation\Attributes\NotBlank;
use Antares\Validation\Attributes\NotNull;
use Antares\Validation\Attributes\Pattern;
use Antares\Validation\Attributes\Url;
use Antares\Validation\Attributes\Uuid;
use Antares\Validation\Attributes\Phone;
use Antares\Validation\Attributes\Ip;
use Antares\Validation\Attributes\Date;
use Antares\Validation\Attributes\DateTime;
use Antares\Validation\Attributes\Positive;
use Antares\Validation\Attributes\Negative;
use Antares\Validation\Attributes\Between;
use Antares\Validation\Attributes\Size;
use Antares\Validation\Attributes\In;
use Antares\Validation\Attributes\InEnum;
use Antares\Validation\Attributes\Alpha;
use Antares\Validation\Attributes\AlphaNumeric;
use Antares\Validation\Attributes\Numeric;
use Antares\Validation\Attributes\HexColor;
use Antares\Validation\Attributes\Json;
use Antares\Validation\Attributes\ArrayOf;
use Antares\Validation\Attributes\Dto;
use Antares\Validation\Attributes\Strict;
use Attribute;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertTrue;

class ValidatorTest extends TestCase
{
    function test_valid_dto_pass(){
        $dto = new FullDto(
            email: 'john@example.com',
            username: 'john',
            bio: 'A short bio',
            title: 'My Title',
            reference: 'something',
            age: 25,
            score: 95,
            quantity: 5,
            debt: -10,
            rating: 7,
            code: 'AB',
            website: 'https://example.com',
            id: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            phone: '+1234567890',
            ip: '192.168.1.1',
            birthdate: '1990-01-15',
            createdAt: '2024-01-15 10:30:00',
            countryCode: 'ALB',
            firstName: 'John',
            slug: 'john123',
            zip: '12345',
            color: '#ff5733',
            metadata: '{"key":"value"}',
            role: 'admin',
            status: 'active',
            tags: ['php', 'api'],
        );

        $validator = new Validator();

        try {
            $validator->validate($dto);
            $this->assertTrue(true);
        } catch (ValidationException $e) {
            $this->fail(json_encode($e->getErrors()));
        }
    }

    public function test_invalid_dto_fails(): void
    {
        $dto = new FullDto(
            email: 'not-an-email',
            username: 'ab',
            bio: str_repeat('a', 21),
            title: '',
            reference: null,
            age: 10,
            score: 101,
            quantity: -1,
            debt: 5,
            rating: 11,
            code: 'A',
            website: 'not-a-url',
            id: 'not-a-uuid',
            phone: 'abc',
            ip: '999.999.999.999',
            birthdate: '15-01-1990',
            createdAt: 'yesterday',
            countryCode: 'ab',
            firstName: 'John123',
            slug: 'john 123!',
            zip: 'abc',
            color: 'red',
            metadata: '{invalid}',
            role: 'superadmin',
            status: 'banned',
            tags: [1, 2],
        );

        $validator = new Validator();

        try {
            $validator->validate($dto);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('email', $errors);
            $this->assertArrayHasKey('username', $errors);
            $this->assertArrayHasKey('bio', $errors);
            $this->assertArrayHasKey('title', $errors);
            $this->assertArrayHasKey('reference', $errors);
            $this->assertArrayHasKey('age', $errors);
            $this->assertArrayHasKey('score', $errors);
            $this->assertArrayHasKey('quantity', $errors);
            $this->assertArrayHasKey('debt', $errors);
            $this->assertArrayHasKey('rating', $errors);
            $this->assertArrayHasKey('code', $errors);
            $this->assertArrayHasKey('website', $errors);
            $this->assertArrayHasKey('id', $errors);
            $this->assertArrayHasKey('phone', $errors);
            $this->assertArrayHasKey('ip', $errors);
            $this->assertArrayHasKey('birthdate', $errors);
            $this->assertArrayHasKey('createdAt', $errors);
            $this->assertArrayHasKey('countryCode', $errors);
            $this->assertArrayHasKey('firstName', $errors);
            $this->assertArrayHasKey('slug', $errors);
            $this->assertArrayHasKey('zip', $errors);
            $this->assertArrayHasKey('color', $errors);
            $this->assertArrayHasKey('metadata', $errors);
            $this->assertArrayHasKey('role', $errors);
            $this->assertArrayHasKey('status', $errors);
            $this->assertArrayHasKey('tags', $errors);
        }
    }

    public function test_between_boundary_values(): void
    {
        $dto = new class(1) {
            public function __construct(
                #[Between(1, 10)]
                public int $rating
            ) {}
        };

        $validator = new Validator();

        try {
            $validator->validate($dto);
            $this->assertTrue(true);
        } catch (ValidationException $e) {
            $this->fail(json_encode($e->getErrors()));
        }

        $dto2 = new class(10) {
            public function __construct(
                #[Between(1, 10)]
                public int $rating
            ) {}
        };

        try {
            $validator->validate($dto2);
            $this->assertTrue(true);
        } catch (ValidationException $e) {
            $this->fail(json_encode($e->getErrors()));
        }
    }

    public function test_size_boundary_values(): void
    {
        $validator = new Validator();

        $dto = new class('AB') {
            public function __construct(
                #[Size(2, 5)]
                public string $code
            ) {}
        };

        try {
            $validator->validate($dto);
            $this->assertTrue(true);
        } catch (ValidationException $e) {
            $this->fail(json_encode($e->getErrors()));
        }

        $dto2 = new class('ABCDE') {
            public function __construct(
                #[Size(2, 5)]
                public string $code
            ) {}
        };

        try {
            $validator->validate($dto2);
            $this->assertTrue(true);
        } catch (ValidationException $e) {
            $this->fail(json_encode($e->getErrors()));
        }
    }

    public function test_min_exact_boundary(): void
    {
        $validator = new Validator();

        $dto = new class(18) {
            public function __construct(
                #[Min(18)]
                public int $age
            ) {}
        };

        try {
            $validator->validate($dto);
            $this->assertTrue(true);
        } catch (ValidationException $e) {
            $this->fail(json_encode($e->getErrors()));
        }
    }

    public function test_max_exact_boundary(): void
    {
        $validator = new Validator();

        $dto = new class(100) {
            public function __construct(
                #[Max(100)]
                public int $score
            ) {}
        };

        try {
            $validator->validate($dto);
            $this->assertTrue(true);
        } catch (ValidationException $e) {
            $this->fail(json_encode($e->getErrors()));
        }
    }

    public function test_not_blank_rejects_whitespace_only(): void
    {
        $validator = new Validator();

        $dto = new class('   ') {
            public function __construct(
                #[NotBlank]
                public string $title
            ) {}
        };

        try {
            $validator->validate($dto);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('title', $e->getErrors());
        }
    }

    public function test_array_of_passes_empty_array(): void
    {
        $validator = new Validator();

        $dto = new class([]) {
            public function __construct(
                #[ArrayOf('string')]
                public array $tags
            ) {}
        };

        try {
            $validator->validate($dto);
            $this->assertTrue(true);
        } catch (ValidationException $e) {
            $this->fail(json_encode($e->getErrors()));
        }
    }
}

#[Dto]
readonly class FullDto
{
    public function __construct(
        #[Email]
        public string $email,

        #[MinLength(3)]
        public string $username,

        #[MaxLength(20)]
        public string $bio,

        #[NotBlank]
        public string $title,

        #[NotNull]
        public mixed $reference,

        #[Min(18)]
        public int $age,

        #[Max(100)]
        public int $score,

        #[Positive]
        public int $quantity,

        #[Negative]
        public int $debt,

        #[Between(1, 10)]
        public int $rating,

        #[Size(2, 5)]
        public string $code,

        #[Url]
        public string $website,

        #[Uuid]
        public string $id,

        #[Phone]
        public string $phone,

        #[Ip]
        public string $ip,

        #[Date]
        public string $birthdate,

        #[DateTime]
        public string $createdAt,

        #[Pattern('/^[A-Z]{3}$/')]
        public string $countryCode,

        #[Alpha]
        public string $firstName,

        #[AlphaNumeric]
        public string $slug,

        #[Numeric]
        public string $zip,

        #[HexColor]
        public string $color,

        #[Json]
        public string $metadata,

        #[In(['admin', 'user', 'guest'])]
        public string $role,

        #[InEnum(StatusEnum::class)]
        public string $status,

        #[ArrayOf('string')]
        public array $tags,
    ) {}
}

enum StatusEnum: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

#[Dto]
#[Strict]
readonly class StrictDto
{
    public function __construct(
        public string $name,
    ) {}
}

