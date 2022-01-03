<?php declare(strict_types=1);

namespace App\Shop;

use Doctrine\DBAL\Connection;
use Shopware\AppBundle\Shop\ShopEntity;
use Shopware\AppBundle\Shop\ShopInterface;
use Shopware\AppBundle\Shop\ShopRepositoryInterface;

class ShopRepository implements ShopRepositoryInterface
{
    public function __construct(
        private Connection $connection
    ) {
    }

    public function createShop(ShopInterface $shop): void
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->insert('shop')
            ->setValue('shop_id', ':shop_id')
            ->setValue('shop_url', ':shop_url')
            ->setValue('shop_secret', ':shop_secret')
            ->setValue('api_key', ':api_key')
            ->setValue('secret_key', ':secret_key')
            ->setParameter('shop_id', $shop->getId())
            ->setParameter('shop_url', $shop->getUrl())
            ->setParameter('shop_secret', $shop->getShopSecret())
            ->setParameter('api_key', $shop->getApiKey())
            ->setParameter('secret_key', $shop->getSecretKey());

        $queryBuilder->execute();
    }

    public function getShopFromId(string $shopId): ShopInterface
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('shop_id', 'shop_url', 'shop_secret', 'api_key', 'secret_key')
            ->from('shop')
            ->where('shop_id = :shop_id')
            ->setParameter('shop_id', $shopId);

        $shop = $queryBuilder->execute()->fetchAssociative();

        return new ShopEntity(
            $shop['shop_id'],
            $shop['shop_url'],
            $shop['shop_secret'],
            $shop['api_key'],
            $shop['secret_key'],
        );
    }

    public function updateShop(ShopInterface $shop): void
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->update('shop')
            ->set('shop_url', ':shop_url')
            ->set('shop_secret', ':shop_secret')
            ->set('api_key', ':api_key')
            ->set('secret_key', ':secret_key')
            ->where('shop_id = :shop_id')
            ->setParameter('shop_id', $shop->getId())
            ->setParameter('shop_url', $shop->getUrl())
            ->setParameter('shop_secret', $shop->getShopSecret())
            ->setParameter('api_key', $shop->getApiKey())
            ->setParameter('secret_key', $shop->getSecretKey());

        $queryBuilder->execute();
    }

    public function deleteShop(ShopInterface $shop): void
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->delete('shop')
            ->where('shop_id = :shop_id')
            ->setParameter('shop_id', $shop->getId());

        $queryBuilder->execute();
    }
}