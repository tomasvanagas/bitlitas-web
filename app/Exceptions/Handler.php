<?php

// -----------------------------------------------------------
//  [*] Handler — what happens to an uncaught exception
//
//  Laravel's handler as the scaffold left it: every exception
//  is written to storage/logs/laravel.log and turned into an
//  error page — the framework's plain one with debug mode
//  off, the code and the stack trace with it on. The app
//  catches the full node's failures itself (BlocksController,
//  bitlitas:sync), so what lands here is everything else.
//
//  Used by:
//    - bootstrap/app.php — bound as the exception handler
// -----------------------------------------------------------


namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;








// -----------------------------------------------------------
// Handler
// -----------------------------------------------------------
//
// Both methods hand straight to the framework; the class
// exists for its two lists.
//
//   report — logging an exception
//   render — turning it into a response
//
// Used by:
//   - bootstrap/app.php — the kernels call it for every
//     exception they catch
// -----------------------------------------------------------

class Handler extends ExceptionHandler
{

    // Exception types never logged: none beyond the
    // framework's own list
    protected $dontReport = [];

    // Inputs never kept in the session when a form fails
    // validation
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];






    // -----------------------------------------------------------
    // report
    // -----------------------------------------------------------
    //
    // Logs the exception through Laravel's logger, unless its
    // type is on a dont-report list.
    //
    // Used by:
    //   - the HTTP and console kernels — every exception they
    //     catch
    // -----------------------------------------------------------

    public function report(Exception $exception)
    {
        parent::report($exception);
    }






    // -----------------------------------------------------------
    // render
    // -----------------------------------------------------------
    //
    // The response for the exception: a 404 page, a 405, the
    // generic 500 page, or the debug page when debug mode is
    // on.
    //
    // Used by:
    //   - the HTTP kernel — every exception a request raises
    // -----------------------------------------------------------

    public function render($request, Exception $exception)
    {
        return parent::render($request, $exception);
    }
}
