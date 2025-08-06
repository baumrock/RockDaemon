<?php

namespace ProcessWire;

use RockDeamon\Deamon;

function rockdeamon(): Deamon
{
  return wire()->modules->get('RockDeamon');
}

/**
 * @author Bernhard Baumrock, 06.08.2025
 * @license Licensed under MIT
 * @link https://www.baumrock.com
 */
class RockDeamon extends WireData implements Module, ConfigurableModule
{
  public function new(
    string $id,
    ?string $logName = null,
    ?array $logOptions = null,
  ): Deamon {
    require_once __DIR__ . '/Deamon.php';
    return new Deamon($id, $logName, $logOptions);
  }

  /**
   * Mark one or all deamons to be shutdown
   *
   * Usage:
   * $modules->get('RockDeamon')->forceShutdown();
   * $modules->get('RockDeamon')->forceShutdown('my-deamon');
   */
  public function forceShutdown(?string $id = null): void
  {
    if ($id) wire()->cache->delete("rockdeamon-running-$id");
    else wire()->cache->delete('rockdeamon-running-*');
  }

  public function getModuleConfigInputfields($inputfields)
  {
    $deamons = wire()->cache->get('rockdeamon-running-*');
    $shutdown = wire()->input->post->array('forceShutdown');
    foreach ($shutdown as $id) {
      $this->forceShutdown($id);
    }

    $f = new InputfieldCheckboxes();
    $f->name = 'forceShutdown';
    $f->label = 'Force shutdown';
    foreach ($deamons as $k => $deamon) {
      $deamon = str_replace('rockdeamon-running-', '', $k);
      $f->addOption($deamon, $deamon);
    }
    if (!count($deamons)) $f->notes = 'No deamons are currently running';
    $inputfields->add($f);

    return $inputfields;
  }
}
