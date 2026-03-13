<?php

declare(strict_types=1);

namespace Claudriel\CLI;

use Claudriel\Entity\Workspace;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

#[AsCommand(name: 'claudriel:workspace:link-repo', description: 'Link a local repository checkout to a workspace')]
final class WorkspaceLinkRepoCommand extends Command
{
    public function __construct(
        private readonly EntityRepositoryInterface $workspaceRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('workspace_uuid', InputArgument::REQUIRED, 'Workspace UUID');
        $this->addArgument('repo_path', InputArgument::REQUIRED, 'Local repository path');
        $this->addArgument('repo_url', InputArgument::OPTIONAL, 'Repository URL');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspaceUuid = (string) $input->getArgument('workspace_uuid');
        $repoPath = (string) $input->getArgument('repo_path');
        $repoUrl = $input->getArgument('repo_url');

        $results = $this->workspaceRepository->findBy(['uuid' => $workspaceUuid]);
        $workspace = $results[0] ?? null;

        if (! $workspace instanceof Workspace) {
            $output->writeln('Workspace not found.');

            return Command::FAILURE;
        }

        $workspace->set('repo_path', $repoPath);
        if (is_string($repoUrl) && $repoUrl !== '') {
            $workspace->set('repo_url', $repoUrl);
        }

        $this->workspaceRepository->save($workspace);

        $output->writeln('Repository linked to workspace.');

        return Command::SUCCESS;
    }
}
