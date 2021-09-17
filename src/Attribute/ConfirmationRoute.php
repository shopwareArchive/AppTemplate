<?php declare(strict_types=1);

namespace App\Attribute;

use Attribute;
use Symfony\Component\Routing\Annotation\Route;

#[Attribute(Attribute::TARGET_METHOD)]
class ConfirmationRoute extends Route
{
}