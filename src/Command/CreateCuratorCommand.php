<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Crée un compte de curateur.
 *
 * Il n'y a pas d'inscription libre : les comptes sont créés par l'exploitant,
 * en ligne de commande.
 */
#[AsCommand(name: 'app:curator:create', description: 'Crée un compte de curateur')]
final class CreateCuratorCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse de connexion')
            ->addArgument('displayName', InputArgument::REQUIRED, 'Nom affiché dans les attributions')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Mot de passe (demandé si absent)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = (string) $input->getArgument('email');
        $displayName = (string) $input->getArgument('displayName');

        if (null !== $this->users->findOneByEmail($email)) {
            $io->error(\sprintf('Un curateur utilise déjà l\'adresse « %s ».', $email));

            return Command::FAILURE;
        }

        $password = $input->getOption('password');
        if (null === $password) {
            // Saisie masquée : le mot de passe ne doit pas rester dans
            // l'historique du shell.
            $question = (new Question('Mot de passe : '))->setHidden(true)->setHiddenFallback(false);
            $password = $io->askQuestion($question);
        }

        if (!\is_string($password) || '' === trim($password)) {
            $io->error('Le mot de passe ne peut pas être vide.');

            return Command::FAILURE;
        }

        $curator = new User($email, $displayName);
        $curator->setRoles(['ROLE_ADMIN'])
            ->setPassword($this->hasher->hashPassword($curator, $password));

        $this->em->persist($curator);
        $this->em->flush();

        $io->success(\sprintf('Curateur « %s » créé (%s).', $displayName, $email));

        return Command::SUCCESS;
    }
}
