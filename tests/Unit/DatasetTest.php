<?php

namespace Tests\Unit;

use App\Datasets\ContactsDataset;
use App\Datasets\Dataset;
use App\Datasets\Field;
use PHPUnit\Framework\TestCase;

class DatasetTest extends TestCase
{
    public function test_headers_are_normalized_for_matching(): void
    {
        $this->assertSame('emailaddress', Dataset::normalizeHeader(' E-mail Address '));
        $this->assertSame('fullname', Dataset::normalizeHeader('Full_Name'));
    }

    public function test_mapping_is_suggested_from_differently_written_headers(): void
    {
        $mapping = (new ContactsDataset())->suggestMapping(['Mobile', 'Full Name', 'E-mail Address', 'Notes', 'Town']);

        $this->assertSame([
            'name' => 1,
            'email' => 2,
            'phone' => 0,
            'company' => null,
            'city' => 4,
            'country' => null,
        ], $mapping);
    }

    public function test_one_column_is_never_suggested_for_two_fields(): void
    {
        $mapping = (new ContactsDataset())->suggestMapping(['name', 'name']);

        $this->assertSame(0, $mapping['name']);
        $this->assertNull($mapping['company']);
    }

    public function test_field_normalizes_and_validates_values(): void
    {
        $email = new Field('email', 'Email', Field::TYPE_EMAIL, required: true);

        $this->assertSame('jane@example.test', $email->normalize('  Jane@Example.TEST '));
        $this->assertNull($email->normalize('   '));
        $this->assertSame(['email_required', 'Email is required.'], $email->validate(null));
        $this->assertSame('email_invalid', $email->validate('not-an-email')[0]);
        $this->assertNull($email->validate('jane@example.test'));

        $phone = new Field('phone', 'Phone', maxLength: 5);
        $this->assertNull($phone->validate(null));
        $this->assertSame('phone_too_long', $phone->validate('123456')[0]);
    }
}
