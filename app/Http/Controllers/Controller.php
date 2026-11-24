<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    protected function roleViewPath(Request $request, string $view): string
    {
        $prefix = match (true) {
            $request->user()?->isConsumer() => 'consumer',
            $request->user()?->isMeterReader() => 'meter-reading',
            default => 'admin',
        };

        return $prefix.'.'.$view;
    }

    protected function adminViewPath(string $view): string
    {
        return 'admin.'.$view;
    }
}
