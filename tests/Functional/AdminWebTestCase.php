<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUser;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Webmozart\Assert\Assert;

/**
 * Boots the test kernel, creates a persisted admin user, and logs them in via the `admin`
 * firewall so subclasses can hit any /admin/* endpoint without going through the login form.
 *
 * Each test runs inside a dama/doctrine-test-bundle transaction that is rolled back at
 * teardown — the admin user, channels, and QR codes persisted here do not leak between tests.
 */
abstract class AdminWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = self::createClient();

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        Assert::isInstanceOf($entityManager, EntityManagerInterface::class);
        $this->entityManager = $entityManager;

        // Sylius's AdminUser implements Sylius\Component\User\Model\UserInterface, not Symfony's
        // Security\UserInterface. The SyliusLabs polyfill bundle bridges the two at runtime, but
        // PHPStan doesn't model that adaptation.
        /** @phpstan-ignore argument.type */
        $this->client->loginUser($this->createAdmin(), 'admin');
    }

    private function createAdmin(): AdminUser
    {
        $admin = new AdminUser();
        $admin->setEmail('admin@example.com');
        $admin->setUsername('admin');
        $admin->setPlainPassword('admin-pass');
        $admin->setPassword('admin-pass');
        $admin->setLocaleCode('en_US');
        $admin->setEnabled(true);

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        return $admin;
    }
}
