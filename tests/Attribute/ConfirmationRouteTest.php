<?php declare(strict_types=1);

namespace App\Test\Attribute;

use App\Attribute\ConfirmationRoute;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ConfirmationRouteTest extends TestCase
{
    #[ConfirmationRoute('/confirm', name: 'shopware_app.confirm', methods: ['POST'])]
    public function testRegistrationRouteAttribute(): void
    {
        $reflectionClass = new ReflectionClass($this);
        $reflectionMethod = $reflectionClass->getMethod('testRegistrationRouteAttribute');

        $reflectionAttribute = $reflectionMethod->getAttributes(ConfirmationRoute::class);

        static::assertEquals($reflectionAttribute[0]->getArguments(), [
            '0' => '/confirm',
            'name' => 'shopware_app.confirm',
            'methods' => [
                'POST'
            ]
        ]);
    }
}