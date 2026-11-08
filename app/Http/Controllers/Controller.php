<?php

// -----------------------------------------------------------
//  [*] Controller — the base of the app's controllers
//
//  Laravel's base controller with the scaffold's three
//  traits: authorizing against policies, dispatching jobs and
//  validating requests. The app's controllers use none of
//  them yet.
//
//  Used by:
//    - BlocksController, HomeController, StatisticController
//      (this folder) — they extend it
// -----------------------------------------------------------


namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;








// -----------------------------------------------------------
// Controller
// -----------------------------------------------------------
//
// Abstract in spirit: no route names it, every controller
// of the app inherits from it.
//
// Used by:
//   - BlocksController, HomeController, StatisticController
//     (this folder)
// -----------------------------------------------------------

class Controller extends BaseController
{

    // authorize(), dispatch() and validate() for the
    // controllers below
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
}
