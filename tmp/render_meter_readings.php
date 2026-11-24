<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::where('role', 'admin')->first();
Auth::login($user);

$request = Illuminate\Http\Request::create('/meter-readings', 'GET');
$request->setUserResolver(fn () => $user);
app()->instance('request', $request);
View::share('errors', new Illuminate\Support\ViewErrorBag);

$controller = app(App\Http\Controllers\MeterReadingController::class);
$view = $controller->index($request, app(App\Services\MeterReadingQueueService::class));
$html = $view->render();

file_put_contents('C:/Users/bongh/AppData/Local/Temp/opencode/meter-readings-render.html', $html);

echo 'rendered '.strlen($html).PHP_EOL;
