<?php

declare(strict_types=1);

namespace Claudriel\CLI;

use Claudriel\Entity\Operation;
use Claudriel\Entity\Workspace;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

#[AsCommand(name: 'claudriel:workspace:ops', description: 'List recent workspace operations')]
final class WorkspaceOpsCommand extends Command
{
    public function __construct(
        private readonly EntityRepositoryInterface $workspaceRepository,
        private readonly EntityRepositoryInterface $operationRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('workspace_uuid', InputArgument::REQUIRED, 'Workspace UUID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspaceUuid = (string) $input->getArgument('workspace_uuid');
        $results = $this->workspaceRepository->findBy(['uuid' => $workspaceUuid]);
        $workspace = $results[0] ?? null;

        if (! $workspace instanceof Workspace) {
            $output->writeln('Workspace not found.');

            return Command::FAILURE;
        }

        $operations = $this->operationRepository->findBy(['workspace_id' => $workspace->get('wid')]);
        $operations = array_values(array_filter($operations, static fn (mixed $operation): bool => $operation instanceof Operation));
        usort($operations, static function (Operation $left, Operation $right): int {
            return strcmp((string) ($right->get('created_at') ?? ''), (string) ($left->get('created_at') ?? ''));
        });

        foreach (array_slice($operations, 0, 20) as $operation) {
            $output->writeln(sprintf(
                '%s | %s | %s | %s',
                (string) ($operation->get('uuid') ?? ''),
                (string) ($operation->get('status') ?? ''),
                (string) ($operation->get('commit_hash') ?? ''),
                (string) ($operation->get('created_at') ?? ''),
            ));
        }

        return Command::SUCCESS;
    }
}
