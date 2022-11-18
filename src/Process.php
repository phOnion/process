<?php

declare(strict_types=1);

namespace Onion\Framework\Process;

use Onion\Framework\Loop\Descriptor;
use Onion\Framework\Loop\Interfaces\ResourceInterface;

class Process
{
    private readonly mixed $resource;

    private readonly int $pid;
    private readonly int $code;

    private readonly ResourceInterface $input;
    private readonly ResourceInterface $output;
    private readonly ResourceInterface $error;

    public function __construct(
        private readonly string | array $command,
        private readonly ?string $cwd = null,
        private readonly ?array $env = null,
    ) {
    }

    public function pid(): ?int
    {
        if (!isset($this->pid)) {
            $this->status();
        }

        return $this->pid ?? null;
    }

    public function code(): ?int
    {
        if (!isset($this->code)) {
            $this->status();
        }

        return $this->code ?? null;
    }

    public function running(): bool
    {
        return $this->status()['running'];
    }

    public function start(
        ?string $cwd = null,
        ?array $env = null,
    ): void {
        $pipes = [];
        $this->resource = proc_open(
            $this->command,
            [
                ['pipe', 'r'],
                ['pipe', 'w'],
                ['pipe', 'w'],
            ],
            $pipes,
            $cwd ?? $this->cwd ?? null,
            $env ?? $this->env ?? null,
            [
                'suppress_errors' => true,
                'bypass_shell' => true,
                'blocking_pipes' => false,
                'create_process_group' => true,
                'create_new_console' => false,
            ]
        );

        $this->input = new Descriptor($pipes[0]);
        $this->output = new Descriptor($pipes[1]);
        $this->error = new Descriptor($pipes[2]);
    }

    public function input(): ResourceInterface
    {
        $this->input->unblock();
        return $this->input;
    }

    public function output(): ResourceInterface
    {
        $this->output->unblock();
        return $this->output;
    }

    public function error(): ResourceInterface
    {
        $this->error->unblock();
        return $this->error;
    }

    public function stop(int $signal = 15): bool
    {
        $status = true;
        if ($this->running()) {
            $status = proc_terminate($this->resource, $signal);
        }

        return $status;
    }

    public function status(): ?array
    {
        $status = proc_get_status($this->resource);

        if (!isset($this->pid)) {
            $this->pid = $status['pid'];
        }

        if ($status['running'] === false && !isset($this->code)) {
            $this->code = $status['exitcode'];
        }

        return $status;
    }

    public function __destruct()
    {
        if ($this->running()) {
            proc_close($this->resource);
        }
    }
}
