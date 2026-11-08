<?php

declare(strict_types=1);

namespace App\Tests\Domain\Security;

use App\Domain\Security\Exceptions\RefreshTokenNotFoundException;
use App\Domain\Security\RefreshTokenCollection;
use App\Domain\Security\RefreshTokenCriteria;
use App\Domain\Security\RefreshTokenRepository;
use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Tests\Mother\Domain\Security\RefreshTokenMother;
use PHPUnit\Framework\TestCase;
use Random\RandomException;

abstract class RefreshTokenRepositoryTestCase extends TestCase
{
    abstract protected function getRepository(): RefreshTokenRepository;

    /**
     * @throws InfrastructureException
     * @throws RandomException
     * @throws RefreshTokenNotFoundException
     */
    public function testPutAndFindOneBy(): void
    {
        $repository = $this->getRepository();
        $token = RefreshTokenMother::create();

        $repository->put($token);

        $this->assertEquals(
            $token,
            $repository->findOneBy(
                RefreshTokenCriteria::createEmpty()->filterByToken((string) $token->getRefreshToken())
            )
        );
    }

    /**
     * @throws InfrastructureException
     * @throws RandomException
     * @throws RefreshTokenNotFoundException
     */
    public function testFindOneByThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(RefreshTokenNotFoundException::class);

        $repository = $this->getRepository();
        $token = RefreshTokenMother::create();

        $repository->findOneBy(
            RefreshTokenCriteria::createEmpty()->filterByToken((string) $token->getRefreshToken())
        );
    }

    /**
     * @throws InfrastructureException
     * @throws RandomException
     * @throws CollectionException
     */
    public function testFindBy(): void
    {
        $token1 = RefreshTokenMother::create(
            username: 'user1@example.com',
        );
        $token2 = RefreshTokenMother::create(
            username: 'user2@example.com',
        );

        $repository = $this->getRepository();
        $repository->put($token1);
        $repository->put($token2);

        $this->assertCount(
            2,
            $repository->findBy(
                RefreshTokenCriteria::createEmpty()
            )->all()
        );

        $this->assertEquals(
            new RefreshTokenCollection([$token1]),
            $repository->findBy(
                RefreshTokenCriteria::createEmpty()
                    ->filterByUsername('user1@example.com')
            )
        );

        $this->assertEquals(
            new RefreshTokenCollection([$token2]),
            $repository->findBy(
                RefreshTokenCriteria::createEmpty()
                    ->filterByToken((string) $token2->getRefreshToken())
            )
        );
    }

    /**
     * @throws InfrastructureException
     * @throws RandomException
     */
    public function testFindByUsername(): void
    {
        $username = 'specific@example.com';
        $token = RefreshTokenMother::create(username: $username);

        $repository = $this->getRepository();
        $repository->put($token);
        $repository->put(RefreshTokenMother::create());

        $this->assertCount(
            1,
            $repository->findBy(
                RefreshTokenCriteria::createEmpty()
                    ->filterByUsername($username)
            )->all()
        );
    }

    /**
     * @throws InfrastructureException
     * @throws RandomException
     * @throws RefreshTokenNotFoundException
     */
    public function testDelete(): void
    {
        $repository = $this->getRepository();
        $token = RefreshTokenMother::create();

        $repository->put($token);

        $this->assertEquals(
            $token,
            $repository->findOneBy(
                RefreshTokenCriteria::createEmpty()->filterByToken((string) $token->getRefreshToken())
            )
        );

        $repository->delete($token);

        $this->expectException(RefreshTokenNotFoundException::class);
        $repository->findOneBy(
            RefreshTokenCriteria::createEmpty()->filterByToken((string) $token->getRefreshToken())
        );
    }
}
