<?php

declare(strict_types=1);
use Waaseyaa\Foundation\Kernel\HttpKernel;

require __DIR__.'/../vendor/autoload.php';

$kernel = new HttpKernel(dirname(__DIR__));
$kernel->handle();
