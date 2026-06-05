<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$request = Illuminate\Http\Request::create('/calving/1', 'DELETE');
// mock a user
$user = App\Models\User::find(3); // created_by for ID 1 is 3
auth()->login($user);
// disable CSRF for this request by removing the middleware or catching it.
// Instead, let's just make the request using the router.
$app->instance('middleware.disable', true);
$response = $app->handle($request);
echo $response->getStatusCode() . "\n";
echo $response->getContent();
