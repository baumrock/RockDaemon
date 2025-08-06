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
    ?string $logName = null,
    ?array $logOptions = null,
  ): Deamon {
    require_once __DIR__ . '/Deamon.php';
    return new Deamon($logName, $logOptions);
  }

  public function getModuleConfigInputfields($inputfields)
  {
    return $inputfields;
  }
}
