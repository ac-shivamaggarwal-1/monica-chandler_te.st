<?php

namespace Tests\Unit\Services\Contact\ManageContactAddress;

use Tests\TestCase;
use App\Models\User;
use App\Models\Vault;
use App\Models\Account;
use App\Models\Address;
use App\Models\Contact;
use App\Models\AddressType;
use App\Jobs\CreateAuditLog;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use App\Exceptions\NotEnoughPermissionException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\Contact\ManageContactAddress\UpdateContactAddress;

class UpdateContactAddressTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_updates_a_contact_address(): void
    {
        $regis = $this->createUser();
        $vault = $this->createVault($regis->account);
        $vault = $this->setPermissionInVault($regis, Vault::PERMISSION_EDIT, $vault);
        $contact = Contact::factory()->create(['vault_id' => $vault->id]);
        $type = AddressType::factory()->create(['account_id' => $regis->account_id]);
        $address = Address::factory()->create([
            'address_type_id' => $type->id,
            'contact_id' => $contact->id,
        ]);

        $this->executeService($regis, $regis->account, $vault, $contact, $type, $address);
    }

    /** @test */
    public function it_fails_if_wrong_parameters_are_given(): void
    {
        $request = [
            'title' => 'Ross',
        ];

        $this->expectException(ValidationException::class);
        (new UpdateContactAddress)->execute($request);
    }

    /** @test */
    public function it_fails_if_user_doesnt_belong_to_account(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $regis = $this->createUser();
        $account = Account::factory()->create();
        $vault = $this->createVault($regis->account);
        $vault = $this->setPermissionInVault($regis, Vault::PERMISSION_EDIT, $vault);
        $contact = Contact::factory()->create(['vault_id' => $vault->id]);
        $type = AddressType::factory()->create(['account_id' => $regis->account_id]);
        $address = Address::factory()->create([
            'address_type_id' => $type->id,
            'contact_id' => $contact->id,
        ]);

        $this->executeService($regis, $account, $vault, $contact, $type, $address);
    }

    /** @test */
    public function it_fails_if_contact_doesnt_belong_to_vault(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $regis = $this->createUser();
        $vault = $this->createVault($regis->account);
        $vault = $this->setPermissionInVault($regis, Vault::PERMISSION_EDIT, $vault);
        $contact = Contact::factory()->create();
        $type = AddressType::factory()->create(['account_id' => $regis->account_id]);
        $address = Address::factory()->create([
            'address_type_id' => $type->id,
            'contact_id' => $contact->id,
        ]);

        $this->executeService($regis, $regis->account, $vault, $contact, $type, $address);
    }

    /** @test */
    public function it_fails_if_user_doesnt_have_right_permission_in_initial_vault(): void
    {
        $this->expectException(NotEnoughPermissionException::class);

        $regis = $this->createUser();
        $vault = $this->createVault($regis->account);
        $vault = $this->setPermissionInVault($regis, Vault::PERMISSION_VIEW, $vault);
        $contact = Contact::factory()->create(['vault_id' => $vault->id]);
        $type = AddressType::factory()->create(['account_id' => $regis->account_id]);
        $address = Address::factory()->create([
            'address_type_id' => $type->id,
            'contact_id' => $contact->id,
        ]);

        $this->executeService($regis, $regis->account, $vault, $contact, $type, $address);
    }

    /** @test */
    public function it_fails_if_type_is_not_in_the_account(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $regis = $this->createUser();
        $vault = $this->createVault($regis->account);
        $vault = $this->setPermissionInVault($regis, Vault::PERMISSION_EDIT, $vault);
        $contact = Contact::factory()->create(['vault_id' => $vault->id]);
        $type = AddressType::factory()->create();
        $address = Address::factory()->create([
            'address_type_id' => $type->id,
            'contact_id' => $contact->id,
        ]);

        $this->executeService($regis, $regis->account, $vault, $contact, $type, $address);
    }

    /** @test */
    public function it_fails_if_address_does_not_exist(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $regis = $this->createUser();
        $vault = $this->createVault($regis->account);
        $vault = $this->setPermissionInVault($regis, Vault::PERMISSION_EDIT, $vault);
        $contact = Contact::factory()->create(['vault_id' => $vault->id]);
        $type = AddressType::factory()->create(['account_id' => $regis->account_id]);
        $address = Address::factory()->create();

        $this->executeService($regis, $regis->account, $vault, $contact, $type, $address);
    }

    private function executeService(User $author, Account $account, Vault $vault, Contact $contact, AddressType $type, Address $address): void
    {
        Queue::fake();

        $request = [
            'account_id' => $account->id,
            'vault_id' => $vault->id,
            'author_id' => $author->id,
            'contact_id' => $contact->id,
            'address_type_id' => $type->id,
            'address_id' => $address->id,
            'street' => '123 rue',
            'city' => 'paris',
            'province' => '67',
            'postal_code' => '12344',
            'country' => 'FRA',
            'latitude' => 12345,
            'longitude' => 12345,
        ];

        $address = (new UpdateContactAddress)->execute($request);

        $this->assertDatabaseHas('addresses', [
            'contact_id' => $contact->id,
            'address_type_id' => $type->id,
            'street' => '123 rue',
            'city' => 'paris',
            'province' => '67',
            'postal_code' => '12344',
            'country' => 'FRA',
            'latitude' => 12345,
            'longitude' => 12345,
        ]);

        $this->assertInstanceOf(
            Address::class,
            $address
        );

        Queue::assertPushed(CreateAuditLog::class, function ($job) {
            return $job->auditLog['action_name'] === 'contact_address_updated';
        });
    }
}
