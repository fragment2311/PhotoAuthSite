<?php

namespace App\Services;

class UpdateRuntime
{
    public function mode(): string
    {
        $configured = strtolower((string) config('update.runtime', 'auto'));
        if (in_array($configured, ['docker', 'host'], true)) {
            return $configured;
        }

        if (is_file('/.dockerenv')) {
            return 'docker';
        }

        if (is_readable('/proc/1/cgroup')) {
            $cgroup = (string) @file_get_contents('/proc/1/cgroup');
            if (preg_match('~(?:docker|containerd|kubepods|podman)~i', $cgroup)) {
                return 'docker';
            }
        }

        return 'host';
    }

    public function platform(): string
    {
        return PHP_OS_FAMILY === 'Windows' ? 'windows' : 'linux';
    }

    public function agentUrl(): string
    {
        $configured = trim((string) config('update.agent_url', ''));
        $configuredHost = $configured !== '' ? strtolower((string) parse_url($configured, PHP_URL_HOST)) : '';
        $mode = $this->mode();

        // These are the built-in address for the opposite runtime, not custom endpoints.
        if ($mode === 'host' && $configuredHost === 'updater') {
            $configured = '';
        } elseif ($mode === 'docker' && in_array($configuredHost, ['127.0.0.1', 'localhost'], true)) {
            $configured = '';
        }

        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        return $mode === 'docker'
            ? 'http://updater:19100'
            : 'http://127.0.0.1:19100';
    }
}
