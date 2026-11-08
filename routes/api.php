<?php

// -----------------------------------------------------------
//  [*] api — the API routes
//
//  The routes of the api middleware group, under /api:
//  stateless, and throttled to 60 requests a minute. The
//  group has no routes: the site has no API and no
//  accounts, so every path under /api is Laravel's 404.
//
//  Used by:
//    - RouteServiceProvider::mapApiRoutes — loads it under
//      /api in the api group
// -----------------------------------------------------------
