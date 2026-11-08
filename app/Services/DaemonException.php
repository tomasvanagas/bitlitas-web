<?php

// -----------------------------------------------------------
//  [*] DaemonException — the full node failed a call
//
//  Thrown when the node cannot be reached, refuses a call or
//  answers something unexpected. Its message names the call
//  and the reason, and for a connection that failed, the
//  node's address — fine for a log or the command line,
//  which is why BlocksController shows it to a visitor only
//  in debug mode.
//
//  Used by:
//    - DaemonClient, NetworkSync (this folder) — throw it
//    - BlocksController, routes/console.php — catch it
// -----------------------------------------------------------


namespace App\Services;








// -----------------------------------------------------------
// DaemonException
// -----------------------------------------------------------
//
// A RuntimeException of its own type, so the callers can
// tell a node failure from a database error, which they let
// through.
//
// Used by:
//   - DaemonClient, NetworkSync (this folder)
//   - BlocksController, routes/console.php
// -----------------------------------------------------------

class DaemonException extends \RuntimeException
{
}
