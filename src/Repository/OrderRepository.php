<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;

class OrderRepository
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function fetchOrder(string $transactionId): ?array
    {
        $value = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('`order`')
            ->where('transaction_id = :id')
            ->setParameter('id', $transactionId)
            ->execute()
            ->fetchAssociative();

        return $value !== false ? $value : null;
    }

    public function fetchOrdersByOrderId(string $orderId): array
    {
        return $this->connection->createQueryBuilder()
            ->select('*')
            ->from('`order`')
            ->where('order_id = :id')
            ->setParameter('id', $orderId)
            ->execute()
            ->fetchAllAssociative();
    }

    public function fetchColumn(string $column, string $transactionId): ?string
    {
        $value = $this->connection->createQueryBuilder()
            ->select(sprintf('`%s`', $column))
            ->from('`order`')
            ->where('transaction_id = :id')
            ->setParameter('id', $transactionId)
            ->execute()
            ->fetchColumn();

        return $value !== false ? $value : null;
    }

    public function insertNewOrder(array $data): void
    {
        $this->connection->insert('`order`', $data);
    }

    public function updateOrderStatus(string $status, string $transactionId): void
    {
        $this->connection->update(
            '`order`',
            ['status' => $status],
            ['transaction_id' => $transactionId]
        );
    }
}