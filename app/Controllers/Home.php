<?php

namespace App\Controllers;

class Home extends BaseController
{
    /** Renderiza la página de bienvenida del framework. */
    public function index(): string
    {
        return view('welcome_message');
    }
}
