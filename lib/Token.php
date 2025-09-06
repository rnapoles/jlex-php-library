<?php

namespace JLexPHP;

class Token {

  public $line;
  public $col;
  public $value;
  public $type;
  public $filename;

  function __construct($type, $value = null, $line = null, $col = null) {
    $this->line = $line;
    $this->col = $col;
    $this->value = $value;
    $this->type = $type;
  }
}