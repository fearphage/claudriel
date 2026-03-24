<?php

declare(strict_types=1);

namespace Claudriel\Command;

use Claudriel\Entity\FilteredProspect;
use Claudriel\Entity\PipelineConfig;
use Claudriel\Entity\Prospect;
use Claudriel\Entity\ProspectAttachment;
use Claudriel\Entity\ProspectAudit;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Waaseyaa\Entity\EntityTypeManagerInterface;

#[AsCommand(name: 'claudriel:pipeline:migrate', description: 'Migrate data from web-networks-pipeline SQLite DB')]
final class PipelineMigrateCommand extends Command
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('source', null, InputOption::VALUE_REQUIRED, 'Path to web-networks-pipeline SQLite DB');
        $this->addOption('workspace', 'w', InputOption::VALUE_REQUIRED, 'Target workspace UUID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sourcePath = $input->getOption('source');
        $workspaceUuid = $input->getOption('workspace');

        if (! is_string($sourcePath) || $sourcePath === '') {
            $output->writeln('<error>--source is required (path to pipeline.db)</error>');

            return Command::FAILURE;
        }

        if (! is_string($workspaceUuid) || $workspaceUuid === '') {
            $output->writeln('<error>--workspace is required</error>');

            return Command::FAILURE;
        }

        if (! file_exists($sourcePath)) {
            $output->writeln(sprintf('<error>Source database not found: %s</error>', $sourcePath));

            return Command::FAILURE;
        }

        $tenantId = $_ENV['CLAUDRIEL_DEFAULT_TENANT'] ?? getenv('CLAUDRIEL_DEFAULT_TENANT') ?: 'default';

        try {
            $db = new \PDO('sqlite:'.$sourcePath, null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (\PDOException $e) {
            $output->writeln(sprintf('<error>Cannot open source DB: %s</error>', $e->getMessage()));

            return Command::FAILURE;
        }

        $counts = ['prospects' => 0, 'attachments' => 0, 'audits' => 0, 'filtered' => 0, 'config' => 0];

        // Migrate user_profile -> PipelineConfig
        $counts['config'] = $this->migrateProfile($db, $workspaceUuid, $tenantId, $output);

        // Migrate prospects
        $counts['prospects'] = $this->migrateProspects($db, $workspaceUuid, $tenantId, $output);

        // Migrate lead_attachments
        $counts['attachments'] = $this->migrateAttachments($db, $workspaceUuid, $tenantId, $output);

        // Migrate lead_audit
        $counts['audits'] = $this->migrateAudits($db, $tenantId, $output);

        // Migrate filtered_leads
        $counts['filtered'] = $this->migrateFiltered($db, $workspaceUuid, $tenantId, $output);

        $output->writeln('');
        $output->writeln('Migration complete:');
        foreach ($counts as $type => $count) {
            $output->writeln(sprintf('  %s: %d', $type, $count));
        }

        return Command::SUCCESS;
    }

    private function migrateProfile(\PDO $db, string $workspaceUuid, string $tenantId, OutputInterface $output): int
    {
        $stmt = $db->query('SELECT * FROM user_profile LIMIT 1');
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (! is_array($row)) {
            $output->writeln('  No user_profile found, skipping.');

            return 0;
        }

        $storage = $this->entityTypeManager->getStorage('pipeline_config');
        $config = new PipelineConfig([
            'name' => 'Migrated Config',
            'workspace_uuid' => $workspaceUuid,
            'source_type' => 'northcloud',
            'company_profile' => json_encode([
                'name' => $row['name'] ?? '',
                'title' => $row['title'] ?? '',
                'company' => $row['company'] ?? '',
                'address' => $row['address'] ?? '',
                'postal_code' => $row['postal_code'] ?? '',
                'phone' => $row['phone'] ?? '',
                'email' => $row['email'] ?? '',
            ], JSON_THROW_ON_ERROR),
            'tenant_id' => $tenantId,
        ]);
        $storage->save($config);
        $output->writeln('  Migrated user_profile -> PipelineConfig');

        return 1;
    }

    private function migrateProspects(\PDO $db, string $workspaceUuid, string $tenantId, OutputInterface $output): int
    {
        $stmt = $db->query('SELECT * FROM prospects WHERE deleted_at IS NULL');
        $storage = $this->entityTypeManager->getStorage('prospect');
        $count = 0;

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $prospect = new Prospect([
                'name' => $row['name'] ?? '',
                'description' => $row['description'] ?? '',
                'stage' => $row['stage'] ?? 'lead',
                'value' => $row['value'] ?? '',
                'contact_name' => $row['contact_name'] ?? '',
                'contact_email' => $row['contact_email'] ?? '',
                'source_url' => $row['source_url'] ?? '',
                'closing_date' => $row['closing_date'] ?? '',
                'sector' => $row['sector'] ?? '',
                'qualify_rating' => isset($row['qualify_rating']) ? (int) $row['qualify_rating'] : null,
                'qualify_keywords' => $row['qualify_keywords'] ?? '',
                'qualify_confidence' => isset($row['qualify_confidence']) ? (float) $row['qualify_confidence'] : null,
                'qualify_notes' => $row['qualify_notes'] ?? '',
                'qualify_raw' => $row['qualify_raw'] ?? '',
                'draft_email_subject' => $row['draft_email_subject'] ?? '',
                'draft_email_body' => $row['draft_email_body'] ?? '',
                'draft_pdf_markdown' => $row['draft_pdf_markdown'] ?? '',
                'draft_pdf_latex' => $row['draft_pdf_latex'] ?? '',
                'external_id' => $row['id'] ?? '',
                'workspace_uuid' => $workspaceUuid,
                'tenant_id' => $tenantId,
            ]);
            $storage->save($prospect);
            $count++;
        }

        $output->writeln(sprintf('  Migrated %d prospects', $count));

        return $count;
    }

    private function migrateAttachments(\PDO $db, string $workspaceUuid, string $tenantId, OutputInterface $output): int
    {
        try {
            $stmt = $db->query('SELECT * FROM lead_attachments');
        } catch (\PDOException) {
            $output->writeln('  No lead_attachments table, skipping.');

            return 0;
        }

        $storage = $this->entityTypeManager->getStorage('prospect_attachment');
        $count = 0;

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $attachment = new ProspectAttachment([
                'prospect_uuid' => $row['lead_id'] ?? '',
                'filename' => $row['filename'] ?? '',
                'storage_path' => $row['storage_path'] ?? '',
                'content_type' => $row['content_type'] ?? 'application/pdf',
                'workspace_uuid' => $workspaceUuid,
                'tenant_id' => $tenantId,
            ]);
            $storage->save($attachment);
            $count++;
        }

        $output->writeln(sprintf('  Migrated %d attachments', $count));

        return $count;
    }

    private function migrateAudits(\PDO $db, string $tenantId, OutputInterface $output): int
    {
        try {
            $stmt = $db->query('SELECT * FROM lead_audit');
        } catch (\PDOException) {
            $output->writeln('  No lead_audit table, skipping.');

            return 0;
        }

        $storage = $this->entityTypeManager->getStorage('prospect_audit');
        $count = 0;

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $audit = new ProspectAudit([
                'prospect_uuid' => $row['lead_id'] ?? '',
                'action' => $row['action'] ?? 'unknown',
                'payload' => $row['payload'] ?? '',
                'confirmed_at' => $row['confirmed_at'] ?? null,
                'tenant_id' => $tenantId,
            ]);
            $storage->save($audit);
            $count++;
        }

        $output->writeln(sprintf('  Migrated %d audit entries', $count));

        return $count;
    }

    private function migrateFiltered(\PDO $db, string $workspaceUuid, string $tenantId, OutputInterface $output): int
    {
        try {
            $stmt = $db->query('SELECT * FROM filtered_leads');
        } catch (\PDOException) {
            $output->writeln('  No filtered_leads table, skipping.');

            return 0;
        }

        $storage = $this->entityTypeManager->getStorage('filtered_prospect');
        $count = 0;

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $filtered = new FilteredProspect([
                'external_id' => $row['external_id'] ?? '',
                'title' => $row['title'] ?? '',
                'description' => $row['description'] ?? '',
                'reject_reason' => $row['reject_reason'] ?? '',
                'import_batch' => $row['import_batch'] ?? '',
                'workspace_uuid' => $workspaceUuid,
                'tenant_id' => $tenantId,
            ]);
            $storage->save($filtered);
            $count++;
        }

        $output->writeln(sprintf('  Migrated %d filtered leads', $count));

        return $count;
    }
}
