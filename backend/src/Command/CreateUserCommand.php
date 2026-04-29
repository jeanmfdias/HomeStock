<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'app:create-user', description: 'Create a HomeStock user (interactive).')]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email address')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Display name')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Plaintext password (min 8 chars)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        $email = $input->getOption('email') ?? $helper->ask($input, $output, new Question('Email: '));
        $name = $input->getOption('name') ?? $helper->ask($input, $output, new Question('Name: '));

        $password = $input->getOption('password');
        if ($password === null) {
            $question = new Question('Password (min 8 chars): ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = $helper->ask($input, $output, $question);
        }

        if (!is_string($email) || !is_string($name) || !is_string($password)) {
            $io->error('email, name and password are required.');
            return Command::FAILURE;
        }

        if (strlen($password) < 8) {
            $io->error('Password must be at least 8 characters.');
            return Command::FAILURE;
        }

        if ($this->users->findByEmail($email) !== null) {
            $io->error(sprintf('A user with email "%s" already exists.', $email));
            return Command::FAILURE;
        }

        $user = new User($email, $name);
        $user->setPassword($this->hasher->hashPassword($user, $password));

        $errors = $this->validator->validate($user);
        if (\count($errors) > 0) {
            foreach ($errors as $error) {
                $io->error($error->getPropertyPath() . ': ' . $error->getMessage());
            }
            return Command::FAILURE;
        }

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('Created user #%d (%s).', $user->getId(), $user->getEmail()));

        return Command::SUCCESS;
    }
}
