<?php

use App\Kernel;
use Stripe\Stripe;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    Stripe::setApiKey($_SERVER['STRIPE_SECRET_KEY']);
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
