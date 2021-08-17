<?php declare(strict_types=1);

namespace App\Test\Shop;

use App\Shop\ShopRepository;
use App\Test\DatabaseTransactionBehaviour;
use Shopware\AppBundle\Shop\ShopRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ShopRepositoryTest extends KernelTestCase
{
    use DatabaseTransactionBehaviour;

    private ShopRepositoryInterface $shopRepository;

    public function setUp(): void
    {
        $this->shopRepository = $this->getContainer()->get(ShopRepositoryInterface::class);
    }

    public function testItUsesMySqlRepository(): void
    {
        static::assertInstanceOf(ShopRepository::class, $this->shopRepository);
    }
}