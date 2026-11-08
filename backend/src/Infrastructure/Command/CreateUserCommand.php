<?php

declare(strict_types=1);

namespace App\Infrastructure\Command;

use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Domain\Shared\Uuid\UuidGenerator;
use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\Domain\User\UserRole;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Crea un nuevo usuario con contraseña hasheada',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UuidGenerator $uuidGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email del usuario')
            ->addArgument('password', InputArgument::REQUIRED, 'Contraseña del usuario')
            ->addOption('admin', 'a', InputOption::VALUE_NONE, 'Crear usuario con rol de administrador')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'UUID del usuario (opcional)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $email = new Email($input->getArgument('email'));
            $password = $input->getArgument('password');
            $isAdmin = $input->getOption('admin');
            $idString = $input->getOption('id');

            // Determinar roles
            $domainRoles = $isAdmin ? [UserRole::Admin, UserRole::User] : [UserRole::User];

            // Generar o usar UUID proporcionado
            $id = $idString ? new Uuid($idString) : $this->uuidGenerator->generate();

            // Crear un usuario temporal para hashear la contraseña
            $tempUser = new \App\Application\Security\User(
                id: $id,
                email: $email,
                roles: $domainRoles,
            );

            // Hashear la contraseña
            $passwordHash = $this->passwordHasher->hashPassword($tempUser, $password);

            // Crear el usuario del dominio
            $user = new User(
                id: $id,
                email: $email,
                roles: $domainRoles,
                passwordHash: $passwordHash,
                createdAt: new \DateTimeImmutable(),
            );

            // Guardar el usuario
            $this->userRepository->put($user);

            $io->success([
                'Usuario creado exitosamente!',
                \sprintf('ID: %s', $id->value()),
                \sprintf('Email: %s', $email->value()),
            ]);

            return Command::SUCCESS;
        } catch (InvalidEmailException $e) {
            $io->error('Email inválido: ' . $e->getMessage());

            return Command::FAILURE;
        } catch (InvalidUuidException $e) {
            $io->error('UUID inválido: ' . $e->getMessage());

            return Command::FAILURE;
        } catch (\Exception $e) {
            $io->error('Error al crear usuario: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
