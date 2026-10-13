<?php

namespace App\Datasets;

use App\Models\Record;

/**
 * Contacts (people and their company details), stored in the `records` table.
 * Unique per company by email address.
 */
class ContactsDataset extends Dataset
{
    public function key(): string
    {
        return 'contacts';
    }

    public function name(): string
    {
        return 'Contacts';
    }

    public function description(): string
    {
        return 'People with an email address, phone and company details. One contact per email address.';
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function fields(): array
    {
        return [
            new Field('name', 'Name', required: true, aliases: ['full name', 'contact name', 'customer name', 'naam']),
            new Field('email', 'Email', Field::TYPE_EMAIL, required: true, aliases: ['email address', 'e-mail', 'mail', 'email id']),
            new Field('phone', 'Phone', maxLength: 30, aliases: ['phone number', 'mobile', 'mobile number', 'cell', 'telephone', 'contact number']),
            new Field('company', 'Company', aliases: ['company name', 'organization', 'organisation', 'business']),
            new Field('city', 'City', aliases: ['town']),
            new Field('country', 'Country', aliases: ['nation']),
        ];
    }

    public function uniqueField(): string
    {
        return 'email';
    }

    public function modelClass(): string
    {
        return Record::class;
    }

    public function recordLabel(): string
    {
        return 'contact';
    }

    public function searchableFields(): array
    {
        return ['name', 'email', 'company', 'city', 'country'];
    }

    public function filterableFields(): array
    {
        return ['country', 'city'];
    }

    public function exampleRows(): array
    {
        return [
            ['name' => 'Ayesha Khan', 'email' => 'ayesha.khan@example.com', 'phone' => '0300 1234567', 'company' => 'Khan Traders', 'city' => 'Lahore', 'country' => 'Pakistan'],
            ['name' => 'John Smith', 'email' => 'john.smith@example.com', 'phone' => '', 'company' => 'Acme Ltd', 'city' => 'London', 'country' => 'United Kingdom'],
        ];
    }
}
