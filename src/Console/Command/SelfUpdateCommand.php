<?php

declare(strict_types=1);

namespace Sputnik\Console\Command;

use Sputnik\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'self-update',
    description: 'Update sputnik.phar to the latest version',
    aliases: ['selfupdate'],
)]
final class SelfUpdateCommand extends Command
{
    private const ENV_TOKEN = 'SPUTNIK_UPDATE_TOKEN';

    private const ENV_REPO = 'SPUTNIK_UPDATE_REPO';

    private const MANIFEST_FILE = 'manifest.json';

    protected function configure(): void
    {
        $this
            ->addOption('check', null, InputOption::VALUE_NONE, 'Only check for updates, do not install')
            ->addOption('rollback', null, InputOption::VALUE_NONE, 'Rollback to the previous version')
            ->setHelp(<<<'HELP'
                Requires environment variables:
                  SPUTNIK_UPDATE_TOKEN  Bitbucket Repository Access Token (read)
                  SPUTNIK_UPDATE_REPO   Bitbucket repo as workspace/repo-slug
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pharPath = \Phar::running(false);

        if ($pharPath === '') {
            $output->writeln('<error>Self-update is only available when running as phar.</error>');

            return self::FAILURE;
        }

        if ($input->getOption('rollback') === true) {
            return $this->rollback($pharPath, $output);
        }

        $token = $this->getToken();

        if ($token === null) {
            $output->writeln(\sprintf('<error>Set %s env var with a Bitbucket Repository Access Token.</error>', self::ENV_TOKEN));

            return self::FAILURE;
        }

        $repo = $this->getRepo();

        if ($repo === null) {
            $output->writeln(\sprintf('<error>Set %s env var (e.g. "workspace/repo-slug").</error>', self::ENV_REPO));

            return self::FAILURE;
        }

        $baseUrl = \sprintf('https://api.bitbucket.org/2.0/repositories/%s/downloads', $repo);

        $manifest = $this->fetchManifest($baseUrl, $token);

        if ($manifest === null) {
            $output->writeln('<error>Could not fetch update manifest.</error>');

            return self::FAILURE;
        }

        $currentVersion = Application::VERSION;

        if (version_compare($currentVersion, $manifest['version'], '>=')) {
            $output->writeln(\sprintf('Already up to date (%s).', $currentVersion));

            return self::SUCCESS;
        }

        $output->writeln(\sprintf('Update available: <info>%s</info> → <info>%s</info>', $currentVersion, $manifest['version']));

        if ($input->getOption('check') === true) {
            return self::SUCCESS;
        }

        $pharUrl = $manifest['url'] ?? \sprintf('%s/sputnik.phar', $baseUrl);

        $tempFile = $this->download($pharUrl, $token);

        if ($tempFile === null) {
            $output->writeln('<error>Download failed.</error>');

            return self::FAILURE;
        }

        if (isset($manifest['sha256'])) {
            $hash = hash_file('sha256', $tempFile);

            if ($hash !== $manifest['sha256']) {
                unlink($tempFile);
                $output->writeln('<error>SHA-256 checksum mismatch. Update aborted.</error>');

                return self::FAILURE;
            }
        }

        $backupPath = $pharPath . '.bak';
        copy($pharPath, $backupPath);

        if (!rename($tempFile, $pharPath)) {
            rename($backupPath, $pharPath);
            $output->writeln('<error>Could not replace phar file. Check permissions.</error>');

            return self::FAILURE;
        }

        chmod($pharPath, 0755);

        $output->writeln(\sprintf('Updated successfully: <info>%s</info> → <info>%s</info>', $currentVersion, $manifest['version']));
        $output->writeln(\sprintf('<comment>Backup saved as %s</comment>', $backupPath));

        return self::SUCCESS;
    }

    private function rollback(string $pharPath, OutputInterface $output): int
    {
        $backupPath = $pharPath . '.bak';

        if (!file_exists($backupPath)) {
            $output->writeln('<error>No backup found. Cannot rollback.</error>');

            return self::FAILURE;
        }

        if (!rename($backupPath, $pharPath)) {
            $output->writeln('<error>Rollback failed. Check permissions.</error>');

            return self::FAILURE;
        }

        chmod($pharPath, 0755);
        $output->writeln('Rollback successful.');

        return self::SUCCESS;
    }

    /**
     * @return array{version: string, sha256?: string, url?: string}|null
     */
    private function fetchManifest(string $baseUrl, string $token): ?array
    {
        $url = \sprintf('%s/%s', $baseUrl, self::MANIFEST_FILE);
        $content = $this->httpGet($url, $token);

        if ($content === null) {
            return null;
        }

        $data = json_decode($content, true);

        if (!\is_array($data) || !isset($data['version']) || !\is_string($data['version'])) {
            return null;
        }

        return $data;
    }

    private function download(string $url, string $token): ?string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'sputnik_');

        if ($tempFile === false) {
            return null;
        }

        $content = $this->httpGet($url, $token);

        if ($content === null) {
            unlink($tempFile);

            return null;
        }

        file_put_contents($tempFile, $content);

        return $tempFile;
    }

    private function httpGet(string $url, string $token): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => \sprintf("Authorization: Bearer %s\r\nUser-Agent: Sputnik/%s\r\n", $token, Application::VERSION),
                'timeout' => 30,
            ],
        ]);

        $content = @file_get_contents($url, false, $context);

        if ($content === false) {
            return null;
        }

        return $content;
    }

    private function getToken(): ?string
    {
        $value = getenv(self::ENV_TOKEN);

        return \is_string($value) && $value !== '' ? $value : null;
    }

    private function getRepo(): ?string
    {
        $value = getenv(self::ENV_REPO);

        return \is_string($value) && $value !== '' ? $value : null;
    }
}
