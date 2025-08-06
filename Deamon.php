<?php

namespace RockDeamon;

use ProcessWire\WireData;
use ProcessWire\Module;
use ProcessWire\ConfigurableModule;

use function ProcessWire\wire;

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

  private string $id;
  private string $cacheKey;
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

  public function __construct(
    string $id,
    ?string $logName = null,
    ?array $logOptions = null
  ) {
    parent::__construct();

    // debug mode?
    $argv = $_SERVER['argv'];
    if (in_array('-d', $argv)) $this->debug(true);

    $this->id = $id;
    $this->cacheKey = "rockdeamon-running-$id";

    $this->setLogname($logName ?? $id);
    $this->logOptions = $logOptions ?? [
      'showURL' => false,
      'showUser' => false,
    ];
    $this->checkRunning();

    $this->log("id=$id");
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
    if (!$this->started) $this->start();
    else {
      $duration = $this->getSecondsRunning();
      $this->echo("running for $duration seconds");
    }

    // running flag removed? then shutdown
    if (!wire()->cache->get($this->cacheKey)) {
      $this->log("running flag removed");
      $this->shutdown();
    }

    // max duration reached?
    if ($duration >= $this->shutdownAfter) {
      $this->log("max duration reached ({$this->shutdownAfter} seconds)");
      $this->shutdown();
    }

    // Process any pending signals before calling the next iteration
    if (function_exists('pcntl_signal_dispatch')) {
      pcntl_signal_dispatch();
    }

    // uncache all pages to avoid stale data
    wire()->pages->uncacheAll();

    // run the callback provided by the user
    if ($callback) $this->callback = $callback;
    if ($this->callback) call_user_func($this->callback, $this);

    // sleep for the configured amount of seconds and run next iteration
    if ($this->sleep > 1) $this->echo("sleeping for {$this->sleep} seconds");
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

  private function checkRunning(): void
  {
    $running = wire()->cache->get($this->cacheKey);
    if ($running) {
      $this->log("deamon {$this->id} is already running");
      exit;
    }
    wire()->cache->save($this->cacheKey, true);
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
    ?string $logname = null,
    ?int $pruneDays = null,
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
    wire()->cache->delete($this->cacheKey);
    $this->log('shutdown');
    exit;
  }

  private function start(): void
  {
    $this->echo("----------");
    $this->log("started");
    $this->started = time();
  }
}
