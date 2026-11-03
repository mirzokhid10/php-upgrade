<?php

namespace Tests\Unit;

use app\models\User;
use Codeception\Test\Unit;
use Tests\Support\CreatesUsers;
use Tests\Support\UnitTester;
use Yii;
use yii\behaviors\TimestampBehavior;
use yii\web\IdentityInterface;

class UserTest extends Unit
{
    use CreatesUsers;

    protected UnitTester $tester;

    /** Thin wrapper over the shared CreatesUsers trait; unique token per call. */
    private function makeUser(array $attrs = []): string
    {
        static $n = 0;
        $n++;
        return (string) $this->createUser($this->tester, array_merge([
            'username'     => '99890000' . str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'access_token' => 'user-test-token-' . $n,
            'email'        => 'user-test-' . $n . '@test.local',
            'auth_key'     => 'authkey' . $n,
        ], $attrs));
    }

    // ---- metadata -------------------------------------------------------

    public function testImplementsIdentityInterface(): void
    {
        $this->assertInstanceOf(IdentityInterface::class, new User());
    }

    public function testTableName(): void
    {
        $this->assertSame('{{%user}}', User::tableName());
    }

    public function testConstantsAreDistinct(): void
    {
        $roles = [User::ROLE_ADMIN, User::ROLE_AGENT, User::ROLE_COURIER, User::ROLE_SUPPLIER];
        $this->assertCount(4, array_unique($roles));
        $this->assertSame(10, User::STATUS_ACTIVE);
        $this->assertSame(9, User::STATUS_INACTIVE);
        $this->assertSame(0, User::STATUS_DELETED);
    }

    public function testTimestampBehaviorIsAttached(): void
    {
        $behaviors = (new User())->behaviors();
        $this->assertContains(TimestampBehavior::class, $behaviors);
    }

    // ---- rules ----------------------------------------------------------

    public function testStatusDefaultsToInactive(): void
    {
        $user = new User();
        $user->validate(['status']);
        $this->assertSame(User::STATUS_INACTIVE, $user->status);
    }

    public function testStatusMustBeInRange(): void
    {
        $user = new User(['status' => 5]);
        $this->assertFalse($user->validate(['status']));
        foreach ([User::STATUS_ACTIVE, User::STATUS_INACTIVE, User::STATUS_DELETED] as $ok) {
            $user = new User(['status' => $ok]);
            $this->assertTrue($user->validate(['status']), "status $ok should be valid");
        }
    }

    public function testLocationIdsMustBeIntegers(): void
    {
        $user = new User(['branch_id' => 'abc', 'region_id' => '1', 'city_id' => '2']);
        $user->validate(['branch_id', 'region_id', 'city_id']);
        $this->assertTrue($user->hasErrors('branch_id'));
        $this->assertFalse($user->hasErrors('region_id'));
        $this->assertFalse($user->hasErrors('city_id'));
    }

    public function testSensitiveAttributesAreNotMassAssignable(): void
    {
        $safe = (new User())->safeAttributes();
        foreach (['password_hash', 'access_token', 'auth_key', 'sms_code', 'username', 'role_id'] as $attr) {
            $this->assertNotContains($attr, $safe, "$attr must not be safe");
        }
    }

    // ---- finders --------------------------------------------------------

    public function testFindIdentityReturnsActiveUserOnly(): void
    {
        $active   = $this->makeUser();
        $inactive = $this->makeUser(['status' => User::STATUS_INACTIVE]);
        $deleted  = $this->makeUser(['status' => User::STATUS_DELETED]);

        $this->assertInstanceOf(User::class, User::findIdentity($active));
        $this->assertNull(User::findIdentity($inactive));
        $this->assertNull(User::findIdentity($deleted));
        $this->assertNull(User::findIdentity(999999999));
    }

    public function testFindIdentityByAccessToken(): void
    {
        $this->makeUser(['access_token' => 'good-token']);
        $this->makeUser(['access_token' => 'inactive-token', 'status' => User::STATUS_INACTIVE]);

        $this->assertInstanceOf(User::class, User::findIdentityByAccessToken('good-token'));
        $this->assertNull(User::findIdentityByAccessToken('inactive-token'));
        $this->assertNull(User::findIdentityByAccessToken('unknown-token'));
    }

    public function testFindIdentityByAccessTokenIgnoresType(): void
    {
        $this->makeUser(['access_token' => 'typed-token']);
        $this->assertNotNull(User::findIdentityByAccessToken('typed-token', 'yii\filters\auth\HttpBearerAuth'));
    }

    public function testEmptyAccessTokenMatchesNobody(): void
    {
        $this->makeUser(['access_token' => null]);
        $this->assertNull(User::findIdentityByAccessToken(''));
        $this->assertNull(User::findIdentityByAccessToken(null));
    }

    public function testFindByUsername(): void
    {
        $this->makeUser(['username' => '998905550000']);
        $this->makeUser(['username' => '998905550001', 'status' => User::STATUS_INACTIVE]);

        $this->assertSame('998905550000', User::findByUsername('998905550000')->username);
        $this->assertNull(User::findByUsername('998905550001'));
        $this->assertNull(User::findByUsername('998905559999'));
    }

    // ---- identity and auth key ------------------------------------------

    public function testGetIdReturnsPrimaryKey(): void
    {
        $id = $this->makeUser();
        $user = User::findIdentity($id);
        $this->assertEquals($id, $user->getId());
    }

    public function testAuthKey(): void
    {
        $user = new User(['auth_key' => 'abc']);
        $this->assertSame('abc', $user->getAuthKey());
        $this->assertTrue($user->validateAuthKey('abc'));
        $this->assertFalse($user->validateAuthKey('abd'));
        $this->assertFalse($user->validateAuthKey(''));
    }

    // ---- passwords ------------------------------------------------------

    public function testSetAndValidatePassword(): void
    {
        $user = new User();
        $user->setPassword('correct horse');
        $this->assertNotSame('correct horse', $user->password_hash);
        $this->assertTrue($user->validatePassword('correct horse'));
        $this->assertFalse($user->validatePassword('wrong'));
        $this->assertFalse($user->validatePassword(''));
    }

    public function testPasswordHashesAreSalted(): void
    {
        $a = new User();
        $b = new User();
        $a->setPassword('same');
        $b->setPassword('same');
        $this->assertNotSame($a->password_hash, $b->password_hash);
    }

    // ---- token generators -----------------------------------------------

    public function testGenerateAuthKey(): void
    {
        $user = new User();
        $user->generateAuthKey();
        $this->assertSame(32, strlen($user->auth_key));
        $old = $user->auth_key;
        $user->generateAuthKey();
        $this->assertNotSame($old, $user->auth_key);
    }

    public function testGenerateAccessToken(): void
    {
        $user = new User();
        $user->generateAccessToken();
        $this->assertSame(64, strlen($user->access_token));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $user->access_token);
        $old = $user->access_token;
        $user->generateAccessToken();
        $this->assertNotSame($old, $user->access_token);
    }

    public function testPasswordResetTokenHasTimestampSuffix(): void
    {
        $before = time();
        $user = new User();
        $user->generatePasswordResetToken();
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{32}_\d+$/', $user->password_reset_token);
        $ts = (int)substr($user->password_reset_token, strrpos($user->password_reset_token, '_') + 1);
        $this->assertGreaterThanOrEqual($before, $ts);
        $this->assertLessThanOrEqual(time(), $ts);
    }

    public function testRemovePasswordResetToken(): void
    {
        $user = new User();
        $user->generatePasswordResetToken();
        $user->removePasswordResetToken();
        $this->assertNull($user->password_reset_token);
    }

    public function testGenerateEmailVerificationToken(): void
    {
        if (!(new User())->hasAttribute('verification_token')) {
            $this->markTestSkipped('The user table has no verification_token column, so this method is dead code.');
        }
        $user = new User();
        $user->generateEmailVerificationToken();
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{32}_\d+$/', $user->verification_token);
    }
}