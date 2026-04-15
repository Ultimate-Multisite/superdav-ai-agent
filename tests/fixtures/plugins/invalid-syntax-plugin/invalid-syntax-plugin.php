<?php
/*
 * Plugin Name: Invalid Syntax Plugin
 * Description: Fixture with a deliberate PHP syntax error for sandbox layer-1 tests.
 * Version:     1.0.0
 */

// Intentional syntax error: unclosed string literal.
$foo = "this string is never closed
echo $foo;
