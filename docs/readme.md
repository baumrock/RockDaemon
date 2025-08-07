# RockDaemon

A ProcessWire module for managing long-running background tasks (daemons) with automatic lifecycle management and monitoring.

## Why RockDaemon?

Running long-running tasks in PHP can be challenging:
- Preventing multiple instances from running simultaneously
- Manual restart capabilities
- Automatic restart after deployments
- Signal handling and graceful shutdown
- Debug output control
- Command-line argument parsing
- Hosting environment compatibility

RockDaemon solves these problems with a simple, cron-based approach.

## Quick Start

### 1. Create a Daemon Script

```php
<?php
// pdf-daemon.php
namespace ProcessWire;

use RockDaemon\Daemon;

require_once __DIR__ . '/public/index.php';

$rockdaemon = wire()->modules->get('RockDaemon');
$daemon = $rockdaemon->new('pdf-daemon');

$daemon
  ->run(function (Daemon $daemon) {
    // get a newspaper page that has the "createPdf" checkbox enabled
    $p = wire()->pages->get([
      'template' => 'newspaper',
      'createPdf' => 1,
    ]);
    if (!$p->id) return $daemon->echo('Nothing to do');

    $timer = Debug::startTimer();
    $p->createPdf();
    $ms = Debug::stopTimer($timer) * 1000;

    $daemon->log(
      message: "created PDF for $p in {$ms}ms",
      logname: "pdf-create",
      pruneDays: 30,
    );

    // proceed instantly without sleeping
    $daemon->run();
  });
```

**Note:** The final `$daemon->run()` call is optional. By default, RockDaemon sleeps for the specified duration (1 second) between iterations. However, you can skip this delay when your task has work to do, which significantly improves performance for batch processing.

**Example:** Processing 100 items would take at least 100 seconds with the default sleep behavior, but with immediate re-execution, it could complete in just a few seconds—depending on the processing time per item.

**When to use `$daemon->run()`:**
- When processing multiple items in sequence
- When you want to continue immediately after completing work
- For high-throughput batch operations

**When to omit `$daemon->run()`:**
- When you want to respect the sleep interval
- For periodic monitoring tasks
- When you need to reduce CPU usage

### 2. Set Up Cron Job

Refer to your hosting provider how to setup cronjobs. Set your cronjob to run every minute.

## API Reference

### Configuration Methods

| Method | Description | Default |
|--------|-------------|---------|
| `setPruneDays(int $days)` | Set log retention period | 1 day |
| `setLogname(string $name, ?int $pruneDays)` | Set custom log name | Uses daemon ID |
| `setSleep(int $seconds)` | Sleep duration between iterations | 1 second |
| `shutdownAfter(int $seconds)` | Auto-shutdown after duration | 1 hour - 10 seconds |
| `debug(bool $debug)` | Enable/disable debug output | false |

### Running the Daemon

```php
$daemon->run(callable $callback);
```

The callback receives the daemon instance as parameter and runs in an infinite loop until shutdown.

### Logging

```php
$daemon->log(
  string $message,
  ?string $logname = null,
  ?int $pruneDays = null,
  ?array $options = null
);
```

### Debug Output

```php
$daemon->echo(string $message);
```

The echo method will write the message directly to your console when debug mode is enabled and you started your script via CLI.

## Command Line Usage

### Debug Mode

Run with `-d` flag to enable debug output:

```bash
php pdf-daemon.php -d
```

### Force Shutdown

When using automated deployments you might want to force a restart of your daemons after a successful deployment:

```php
$rockdaemon = wire()->modules->get('RockDaemon');

// Shutdown specific daemon
$rockdaemon->forceShutdown('pdf-daemon');

// Shutdown all daemons
$rockdaemon->forceShutdown();
```

## Module Configuration

The module provides a configuration screen showing all running daemons with the ability to force shutdown individual instances.

<img src=https://i.imgur.com/wba7qIB.png class=blur>

## Features

- **Single Instance**: Prevents multiple instances of the same daemon
- **Auto-shutdown**: Configurable maximum runtime (default: 1 hour - 10 seconds)
- **Signal Handling**: Graceful shutdown on SIGINT/SIGTERM
- **Logging**: Built-in logging with automatic pruning
- **Debug Mode**: Controlled output with `-d` flag
- **Monitoring**: Web interface to view and manage running daemons
- **Cron-friendly**: Designed to work with standard cron jobs

## Requirements

- PHP >= 8.1
- ProcessWire 3.x
- Cron access (for production use)
