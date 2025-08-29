<?php

namespace ProcessWire;

use RockDaemon\Daemon;

function rockdaemon(): Daemon
{
  return wire()->modules->get('RockDaemon');
}

/**
 * @author Bernhard Baumrock, 06.08.2025
 * @license Licensed under MIT
 * @link https://www.baumrock.com
 */
class RockDaemon extends WireData implements Module, ConfigurableModule
{
  public function new(
    string $id,
    ?string $logName = null,
    ?array $logOptions = null,
  ): Daemon {
    require_once __DIR__ . '/Daemon.php';
    return new Daemon($id, $logName, $logOptions);
  }

  /**
   * Mark one or all daemons to be shutdown
   *
   * Usage:
   * $modules->get('RockDaemon')->forceShutdown();
   * $modules->get('RockDaemon')->forceShutdown('my-daemon');
   */
  public function forceShutdown(?string $id = null): void
  {
    if ($id) wire()->cache->delete("rockdaemon-running-$id");
    else wire()->cache->delete('rockdaemon-running-*');
  }

  public function getModuleConfigInputfields($inputfields)
  {
    $daemons = wire()->cache->get('rockdaemon-running-*');
    $shutdown = wire()->input->post->array('forceShutdown');
    foreach ($shutdown as $id) {
      $this->forceShutdown($id);
    }

    $f = new InputfieldCheckboxes();
    $f->name = 'forceShutdown';
    $f->label = 'Force shutdown';
    foreach ($daemons as $k => $daemon) {
      $daemon = str_replace('rockdaemon-running-', '', $k);
      $f->addOption($daemon, $daemon);
    }
    if (!count($daemons)) $f->notes = 'No daemons are currently running';
    $inputfields->add($f);

    return $inputfields;
  }
}
