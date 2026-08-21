<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Auth\Entity\Permission;
use App\Domain\Auth\Entity\Role;
use App\Domain\Auth\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed', description: 'Seed initial data: roles, permissions and an admin user')]
class DataInitializer extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Re-seed even if data already exists');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');

        $io->title('Identity Service — Database Seeder');

        $io->section('Roles & Permissions');

        $permDefs = [
            'users:read' => 'Read users',
            'users:write' => 'Write users',
            'users:delete' => 'Delete users',
            'roles:read' => 'Read roles',
            'roles:write' => 'Manage roles',
        ];

        $perms = [];
        foreach ($permDefs as $name => $desc) {
            $perm = $this->em->getRepository(Permission::class)->findOneBy(['name' => $name]);
            if (null === $perm) {
                $perm = new Permission();
                $perm->setName($name);
                $perm->setDescription($desc);
                $this->em->persist($perm);
                $io->writeln("  <fg=green>+</> Permission: $name");
            }
            $perms[$name] = $perm;
        }

        $this->em->flush();

        $adminRole = $this->em->getRepository(Role::class)->findOneBy(['name' => 'ADMIN']);
        if (null === $adminRole) {
            $adminRole = new Role();
            $adminRole->setName('ADMIN');
            $adminRole->setDescription('Administrator role with full access');
            $adminRole->setPermissions(new ArrayCollection(array_values($perms)));
            $this->em->persist($adminRole);
            $io->writeln('  <fg=green>+</> Role: ADMIN');
        }

        $userRole = $this->em->getRepository(Role::class)->findOneBy(['name' => 'USER']);
        if (null === $userRole) {
            $userRole = new Role();
            $userRole->setName('USER');
            $userRole->setDescription('Standard user role');
            $userRole->setPermissions(new ArrayCollection([$perms['users:read']]));
            $this->em->persist($userRole);
            $io->writeln('  <fg=green>+</> Role: USER');
        }

        $this->em->flush();

        $io->section('Admin User');

        $adminEmail = 'admin@admin.com';
        $existingAdmin = $this->em->getRepository(User::class)->findOneBy(['email' => $adminEmail]);
        if (null === $existingAdmin || $force) {
            if (null === $existingAdmin) {
                $admin = new User();
                $admin->setEmail($adminEmail);
                $admin->setPasswordHash($this->hasher->hashPassword(new User(), 'admin123'));
                $admin->setFirstName('Admin');
                $admin->setLastName('User');
                $admin->setRoles(new ArrayCollection([$adminRole]));
                $this->em->persist($admin);
                $io->writeln("  <fg=green>+</> $adminEmail / admin123");
            } else {
                $io->writeln('  <fg=yellow>~</> Admin already exists (use --force to reset password)');
            }
        }

        $this->em->flush();

        $io->success([
            'Seed complete.',
            'Admin: admin@admin.com / admin123',
            'Roles: ADMIN (full), USER (read-only)',
            'Permissions: users:read, users:write, users:delete, roles:read, roles:write',
        ]);

        return Command::SUCCESS;
    }
}
