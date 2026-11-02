<?php

namespace Tests\Feature;

use App\Models\User;
use App\VocabularyImportAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class VocabularyImportAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('admin.emails', '');
    }

    public function test_guest_and_unverified_user_cannot_import_in_either_scope(): void
    {
        $access = new VocabularyImportAccess;
        $guestRequest = $this->importRequest(null, 'system');

        $this->assertFalse($access->isAdmin($guestRequest));
        $this->assertForbidden(fn () => $access->authorize($guestRequest));
        $this->assertForbidden(fn () => $access->authorize($this->importRequest(null, 'private')));

        config()->set('admin.emails', 'admin@example.com');
        $unverifiedUser = $this->user('admin@example.com', false);
        $unverifiedRequest = $this->importRequest($unverifiedUser, 'system');

        $this->assertFalse($access->isAdmin($unverifiedRequest));
        $this->assertForbidden(fn () => $access->authorize($unverifiedRequest));
        $this->assertForbidden(fn () => $access->authorize($this->importRequest($unverifiedUser, 'private')));
    }

    public function test_verified_ordinary_user_can_only_import_to_own_private_topics(): void
    {
        config()->set('admin.emails', 'admin@example.com');
        $access = new VocabularyImportAccess;
        $user = $this->user('member@example.com');
        $privateRequest = $this->importRequest($user, 'private', ['user_id' => 999, 'is_admin' => true]);

        $this->assertFalse($access->isAdmin($privateRequest));
        $this->assertSame(42, $access->authorize($privateRequest));
        $this->assertSame(42, $access->authorize($this->importRequest($user, null, ['user_id' => 999])));
        $this->assertForbidden(fn () => $access->authorize($this->importRequest($user, 'system', ['is_admin' => true, 'email' => 'admin@example.com'])));
        $this->assertForbidden(fn () => $access->authorize($this->importRequest($user, 'other')));
        $this->assertForbidden(fn () => $access->authorize($this->importRequest($user, ['system'])));
    }

    public function test_verified_admin_can_choose_system_or_own_private_scope(): void
    {
        config()->set('admin.emails', ' first@example.com , ADMIN@EXAMPLE.COM ');
        $access = new VocabularyImportAccess;
        $user = $this->user(' Admin@Example.com ');

        $this->assertTrue($access->isAdmin($this->importRequest($user, 'private')));
        $this->assertSame(42, $access->authorize($this->importRequest($user, null, ['user_id' => 999])));
        $this->assertSame(42, $access->authorize($this->importRequest($user, 'private', ['user_id' => 999])));
        $this->assertNull($access->authorize($this->importRequest($user, 'system', ['user_id' => 999])));
    }

    public function test_empty_or_malformed_admin_configuration_grants_no_system_access(): void
    {
        $access = new VocabularyImportAccess;
        $user = $this->user('admin@example.com');

        foreach (['', ' ', 'admin@example.com,', 'invalid-address', ['admin@example.com'], null] as $configuredEmails) {
            config()->set('admin.emails', $configuredEmails);
            $request = $this->importRequest($user, 'system');

            $this->assertFalse($access->isAdmin($request));
            $this->assertForbidden(fn () => $access->authorize($request));
            $this->assertSame(42, $access->authorize($this->importRequest($user, 'private')));
        }
    }

    public function test_system_permission_is_checked_again_on_confirmation(): void
    {
        $access = new VocabularyImportAccess;
        $user = $this->user('admin@example.com');
        config()->set('admin.emails', 'admin@example.com');

        $this->assertNull($access->authorize($this->importRequest($user, 'system')));

        config()->set('admin.emails', '');

        $this->assertForbidden(fn () => $access->authorize($this->importRequest($user, 'system')));
    }

    private function user(string $email, bool $verified = true): User
    {
        $user = new User;
        $user->id = 42;
        $user->email = $email;
        $user->email_verified_at = $verified ? now() : null;

        return $user;
    }

    /** @param array<string, mixed> $extra */
    private function importRequest(?User $user, string|array|null $scope, array $extra = []): Request
    {
        $parameters = $scope === null ? $extra : ['scope' => $scope] + $extra;
        $request = Request::create('/vocabulary/import', 'POST', $parameters);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function assertForbidden(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected a 403 response.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }
}
