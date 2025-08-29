<?php

namespace ProcessWire;

$info = [
  'title' => 'RockDaemon',
  'version' => json_decode(file_get_contents(__DIR__ . "/package.json"))->version,
  'summary' => '',
  'autoload' => false,
  'singular' => true,
  'icon' => 'play-circle',
  'requires' => [
    'PHP>=8.1',
  ],
];
