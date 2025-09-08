<?php

use Illuminate\Support\Facades\DB;

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->get('db-test', function () {
    try {
        \DB::connection()->getPdo();
        return response()->json(['status' => 'OK', 'driver' => \DB::getDriverName()]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

$router->group(['prefix' => 'api'], function () use ($router) {
    $router->post('register', ['middleware' => 'throttle.register', 'uses' => 'AuthController@register']);
    $router->post('login',    'AuthController@login');

    $router->group(['middleware' => 'auth.jwt'], function () use ($router) {
        $router->get('me', 'AuthController@me');

        //stub home
        $router->get('progress/summary',            'StubApiController@progressSummary');
        $router->get('progress/daily',              'StubApiController@progressDaily');
        $router->get('progress/language-distribution','StubApiController@languageDistribution');
        $router->get('tests/recommended',           'StubApiController@testsRecommended');
        
        //tests
        $router->get('tests/{id}', 'TestController@getTest');
        $router->post('tests/{id}/evaluate',   'TestController@evaluateCode');
        $router->post('tests/{id}/submit', 'TestController@submitTest');
        $router->get('tests', 'TestController@index');

        //admin
        $router->post('admin/tests', ['middleware' => 'role:admin', 'uses' => 'AdminTestsController@store']);
    });
});