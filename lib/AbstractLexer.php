<?php

namespace JLexPHP;

use Exception;

abstract class AbstractLexer {

  const YY_F = -1;
  const YY_NO_STATE = -1;
  const YY_NOT_ACCEPT = 0;
  const YY_START = 1;
  const YY_END = 2;
  const YY_NO_ANCHOR = 4;
  const YYEOF = -1;

  const YY_BOL = -1;
  const YY_EOF = -1;

  protected $yy_reader;
  protected $yy_buffer;
  protected $yy_buffer_read;
  protected $yy_buffer_index;
  protected $yy_buffer_start;
  protected $yy_buffer_end;
  protected $yychar = 0;
  protected $yycol = 0;
  protected $yyline = 0;
  protected $yy_at_bol;
  protected $yy_lexical_state;
  protected $yy_last_was_cr = false;
  protected $yy_count_lines = false;
  protected $yy_count_chars = false;
  protected $yyfilename = null;

  function __construct($stream) {
    $this->yy_reader = $stream;
    $meta = stream_get_meta_data($stream);
    if (!isset($meta['uri'])) {
      $this->yyfilename = '<<input>>';
    } else {
      $this->yyfilename = $meta['uri'];
    }

    $this->yy_buffer = "";
    $this->yy_buffer_read = 0;
    $this->yy_buffer_index = 0;
    $this->yy_buffer_start = 0;
    $this->yy_buffer_end = 0;
    $this->yychar = 0;
    $this->yyline = 1;
    $this->yy_at_bol = true;
  }

  protected function yybegin($state) {
    $this->yy_lexical_state = $state;
  }

  protected function yy_advance() {
    if ($this->yy_buffer_index < $this->yy_buffer_read) {
      if (!isset($this->yy_buffer[$this->yy_buffer_index])) {
        return $this::YY_EOF;
      }
      return ord($this->yy_buffer[$this->yy_buffer_index++]);
    }
    if ($this->yy_buffer_start != 0) {
      /* shunt */
      $j = $this->yy_buffer_read - $this->yy_buffer_start;
      $this->yy_buffer = substr($this->yy_buffer, $this->yy_buffer_start, $j);
      $this->yy_buffer_end -= $this->yy_buffer_start;
      $this->yy_buffer_start = 0;
      $this->yy_buffer_read = $j;
      $this->yy_buffer_index = $j;

      $data = fread($this->yy_reader, 8192);
      if ($data === false || !strlen($data)) return $this::YY_EOF;
      $this->yy_buffer .= $data;
      $this->yy_buffer_read += strlen($data);
    }

    while ($this->yy_buffer_index >= $this->yy_buffer_read) {
      $data = fread($this->yy_reader, 8192);
      if ($data === false || !strlen($data)) return $this::YY_EOF;
      $this->yy_buffer .= $data;
      $this->yy_buffer_read += strlen($data);
    }
    return ord($this->yy_buffer[$this->yy_buffer_index++]);
  }

  protected function yy_move_end() {
    if ($this->yy_buffer_end > $this->yy_buffer_start &&
        $this->yy_buffer[$this->yy_buffer_end-1] == "\n")
      $this->yy_buffer_end--;
    if ($this->yy_buffer_end > $this->yy_buffer_start &&
        $this->yy_buffer[$this->yy_buffer_end-1] == "\r")
      $this->yy_buffer_end--;
  }

  protected function yy_mark_start() {
    if ($this->yy_count_lines || $this->yy_count_chars) {
      if ($this->yy_count_lines) {
        for ($i = $this->yy_buffer_start; $i < $this->yy_buffer_index; ++$i) {
          if ("\n" == $this->yy_buffer[$i] && !$this->yy_last_was_cr) {
            ++$this->yyline;
            $this->yycol = 0;
          }
          if ("\r" == $this->yy_buffer[$i]) {
            ++$this->yyline;
            $this->yycol = 0;
            $this->yy_last_was_cr = true;
          } else {
            $this->yy_last_was_cr = false;
          }
        }
      }
      if ($this->yy_count_chars) {
        $this->yychar += $this->yy_buffer_index - $this->yy_buffer_start;
        $this->yycol += $this->yy_buffer_index - $this->yy_buffer_start;
      }
    }
    $this->yy_buffer_start = $this->yy_buffer_index;
  }

  protected function yy_mark_end() {
    $this->yy_buffer_end = $this->yy_buffer_index;
  }

  protected function yy_to_mark() {
    #echo "yy_to_mark: setting buffer index to ", $this->yy_buffer_end, "\n";
    $this->yy_buffer_index = $this->yy_buffer_end;
    $this->yy_at_bol = ($this->yy_buffer_end > $this->yy_buffer_start) &&
                ("\r" == $this->yy_buffer[$this->yy_buffer_end-1] ||
                 "\n" == $this->yy_buffer[$this->yy_buffer_end-1] ||
                 2028 /* unicode LS */ == $this->yy_buffer[$this->yy_buffer_end-1] ||
                 2029 /* unicode PS */ == $this->yy_buffer[$this->yy_buffer_end-1]);
  }

  protected function yytext() {
    return substr($this->yy_buffer, $this->yy_buffer_start, 
          $this->yy_buffer_end - $this->yy_buffer_start);
  }

  protected function yylength() {
    return $this->yy_buffer_end - $this->yy_buffer_start;
  }

  static $yy_error_string = [
    'INTERNAL' => "Error: internal error.\n",
    'MATCH' => "Error: Unmatched input.\n"
  ];

  protected function yy_error($code, $fatal) {
    print self::$yy_error_string[$code];
    flush();
    if ($fatal) throw new Exception("JLex fatal error " . self::$yy_error_string[$code]);
  }

  /* creates an annotated token */
  function createToken($type = null) {
    if ($type === null) $type = $this->yytext();
    $tok = new Token($type);
    $this->annotateToken($tok);
    return $tok;
  }

  /* annotates a token with a value and source positioning */
  function annotateToken(Token $tok) {
    $tok->value = $this->yytext();
    $tok->col = $this->yycol;
    $tok->line = $this->yyline;
    $tok->filename = $this->yyfilename;
  }
}