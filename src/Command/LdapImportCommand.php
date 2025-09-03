<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Ldap\Adapter\ExtLdap\Adapter;
use Symfony\Component\Ldap\Ldap;

class LdapImportCommand extends Command {
    public function __construct(private readonly EntityManagerInterface $entityManager) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('app:ldapimport')
            ->setDescription('Imports users from LDAP')
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'LDAP host')
            ->addOption('port', null, InputOption::VALUE_OPTIONAL, 'LDAP port', 636)
            ->addOption('ssl', null, InputOption::VALUE_OPTIONAL, 'Encryption method (none, ssl, tls)', 'ssl')
            ->addOption('bindDn', null, InputOption::VALUE_REQUIRED, 'bindDN (username)')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'bind password')
            ->addOption('baseDn', null, InputOption::VALUE_REQUIRED, 'LDAP BaseDN')
            ->addOption('query', null, InputOption::VALUE_OPTIONAL, 'LDAP search filter', false)
            ->addOption('userField', null, InputOption::VALUE_OPTIONAL, 'LDAP username field', 'uid')
            ->addOption('emailField', null, InputOption::VALUE_OPTIONAL, 'LDAP email field', false)
            ->addOption('update', null, InputOption::VALUE_OPTIONAL, 'Update mail address if user already exists', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $ldapAdapter = new Adapter([
            'host' => $input->getOption('host'),
            'port' => (int) $input->getOption('port'),
            'encryption' => $input->getOption('ssl'),
        ]);

        $ldap = new Ldap($ldapAdapter);
        $ldap->bind($input->getOption('bindDn'), $input->getOption('password'));

        $update = $input->getOption('update');
        $userField = $input->getOption('userField');
        $fields = [$userField];

        $emailField = $input->getOption('emailField');
        if ($emailField) {
            $fields[] = $emailField;
        }

        $query = $input->getOption('query');
        if (!$query) {
            $query = \sprintf('(%s=*)', $userField);
        }

        $ldapQuery = $ldap->query($input->getOption('baseDn'), $query, [
            'filter' => $fields,
        ]);

        foreach ($ldapQuery->execute()->toArray() as $result) {
            $uid = $result->getAttribute($userField);

            if (!$uid) {
                $output->writeln(\sprintf("Username is missing for DN '%s'", $result->getDn()));

                continue;
            }

            $existingUser = $this->entityManager->getRepository(User::class)->findByName($uid[0]);
            if ($existingUser) {
                if ($update) {
                    $user = $existingUser;
                } else {
                    $output->writeln(\sprintf("Skipping user '%s'. Already exists.", $uid[0]));

                    continue;
                }
            } else {
                $user = new User();
                $user->setName($uid[0]);
            }

            if ($emailField) {
                $email = $result->getAttribute($emailField);
                if ($email) {
                    $user->setEmail($email[0]);
                }
            }

            if ($existingUser && $update) {
                // Don't show message if nothing has changed
                if ($existingUser->getEmail() === $user->getEmail()) {
                    continue;
                }

                $output->writeln(\sprintf("Updated user '%s'", $user->getName()));
            } else {
                $output->writeln(\sprintf("Imported user '%s'", $user->getName()));
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        $output->writeln('Done!');

        return Command::SUCCESS;
    }
}
