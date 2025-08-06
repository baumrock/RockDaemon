<?php

namespace RockDeamon;

use ProcessWire\WireData;
use ProcessWire\Module;
use ProcessWire\ConfigurableModule;

/**
 * @author Bernhard Baumrock, 06.08.2025
 * @license Licensed under MIT
 * @link https://www.baumrock.com
 */
class Deamon extends WireData implements Module, ConfigurableModule
{
  const ONESECOND = 1;
  const ONEMINUTE = 60 * self::ONESECOND;
  const ONEHOUR = 60 * self::ONEMINUTE;
  const ONEDAY = 24 * self::ONEHOUR;

  private ?string $logname = null;
  private int $pruneDays = 1;
  private array $logOptions = [];
  private bool $debug = false;
  private int $started = 0;
  private bool $shutdown = false;
  private $callback = null;

  /**
   * How long to sleep between runs
   * @var int
   */
  private int $sleep = 1;

  /**
   * How long to run before shutting down
   * By default we shutdown after 1 hour - 10 seconds
   * so that we only have to wait 10 seconds for the next cron to start up
   * @var int
   */
  private int $shutdownAfter = 0;

  public function __construct($logName = null, ?array $logOptions = null)
  {
    parent::__construct();
    $this->setLogname($logName);
    $this->logOptions = $logOptions ?? [
      'showURL' => false,
      'showUser' => false,
    ];
    $argv = $_SERVER['argv'];
    if (in_array('-d', $argv)) $this->debug(true);
    $this->shutdownAfter(self::ONEHOUR - 10);
    $this->addShutdownHandler();
  }

  /** --- public chainable api --- */

  public function debug(bool $debug = true): self
  {
    $this->debug = $debug;
    $this->echo("debug=" . ($debug ? 'TRUE' : 'FALSE'));
    return $this;
  }

  public function echo(string $message): self
  {
    if (!$this->debug) return $this;
    echo $message . PHP_EOL;
    return $this;
  }

  public function run(?callable $callback = null): void
  {
    $duration = 0;
    if (!$this->started) {
      $this->echo("----------");
      $this->log("started");
      $this->started = time();
    } else {
      $duration = $this->getSecondsRunning();
      $this->echo("running for $duration seconds");
    }

    if ($duration >= $this->shutdownAfter) {
      $this->log("shutdown after {$this->shutdownAfter} seconds");
      $this->shutdown();
    }

    // Process any pending signals before calling the next iteration
    if (function_exists('pcntl_signal_dispatch')) {
      pcntl_signal_dispatch();
    }

    // run the callback provided by the user
    if ($callback) $this->callback = $callback;
    if ($this->callback) call_user_func($this->callback, $this);

    // sleep for the configured amount of seconds and run next iteration
    $this->echo("sleeping for {$this->sleep} seconds");
    sleep($this->sleep);
    $this->run($callback);
  }

  public function setPruneDays(int $days): self
  {
    $this->pruneDays = $days;
    $this->log("pruneDays=$days");
    return $this;
  }

  public function setLogname(string $logname, ?int $pruneDays = null): self
  {
    $this->logname = $logname;
    $this->echo("logname=$logname");
    if ($pruneDays) {
      $this->pruneDays = $pruneDays;
      $this->log("pruneDays=$pruneDays");
    }
    return $this;
  }

  public function setSleep(int $sleep): self
  {
    $this->sleep = $sleep;
    $this->log("sleep=$sleep");
    return $this;
  }

  public function shutdownAfter(int $seconds): self
  {
    $this->shutdownAfter = $seconds;
    $this->log("shutdownAfter=$seconds");
    return $this;
  }

  /** --- other methods --- */

  private function addShutdownHandler(): void
  {
    register_shutdown_function(function () {
      $this->shutdown();
    });

    // Handle CTRL-C (SIGINT) and other signals
    if (function_exists('pcntl_signal')) {
      pcntl_signal(SIGINT, function ($signal) {
        $this->log('Received SIGINT (CTRL-C), shutting down gracefully');
        $this->shutdown();
      });

      pcntl_signal(SIGTERM, function ($signal) {
        $this->log('Received SIGTERM, shutting down gracefully');
        $this->shutdown();
      });
    }
  }

  /**
   * Config inputfields
   * @param InputfieldWrapper $inputfields
   */
  public function getModuleConfigInputfields($inputfields)
  {
    return $inputfields;
  }

  public function getSecondsRunning(): int
  {
    return time() - $this->started;
  }

  public function log(
    string $message,
    ?int $pruneDays = null,
    ?string $logname = null,
    ?array $options = null,
  ): void {
    $logname = $logname ?? $this->logname;
    if (!$logname) return;

    $pruneDays = $pruneDays ?? $this->pruneDays;
    $options = $options ?? $this->logOptions;

    $this->echo($message);
    $this->wire()->log->save($logname, $message, $options);
    if ($pruneDays) $this->wire()->log->prune($logname, $pruneDays);
  }

  private function shutdown(): void
  {
    if ($this->shutdown) return;
    $this->shutdown = true;
    $this->log('shutdown');
    exit;
  }
}
