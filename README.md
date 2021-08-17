# Script
## Step 1
Clone this repo into you shopware docker code dir, into the folder `appdaysdemo`
```shell
git clone git@github.com:shopware/AppTemplate.git appdaysdemo
```
folder name needs to match to use the manifest file provided here, otherwise the urls won't match
Notice: the folder name should be lowercase, as otherwise it leads to networking problems

start containers by
```shell
swdc up
```

install composer dependencies
```shell
swdc pshell appdaysdemo
composer install
```

adjust .env file to following content
```dotenv
APP_ENV=dev
APP_NAME=AppDaysDemo
APP_SECRET=myAppSecret
APP_URL=http://appdaysdemo.dev.localhost
DATABASE_URL=mysql://root:root@mysql:3306/appdaysdemo
```

create `appdaysdemo` DB manually over `http://db.localhost/` -> necessary because DB will be created by `swdc build` command automatically, which is only for shopware projects

run migrations:
```shell
swdc pshell appdaysdemo
bin/console doctrine:migrations:migrate
```

inside platform create `AppDaysDemo` folder under `custom/apps` and create following `manifest.xml` in that folder
```xml
<?xml version="1.0" encoding="UTF-8"?>
<manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/platform/master/src/Core/Framework/App/Manifest/Schema/manifest-1.0.xsd">
    <meta>
        <name>AppDaysDemo</name>
        <label>Demo App for Stripe Payments</label>
        <label lang="de-DE">Demo App für Stripe Payments</label>
        <description>Example App - Do not use in production</description>
        <description lang="de-DE">Beispiel App - Nicht im Produktivbetrieb verwenden</description>
        <author>shopware AG</author>
        <copyright>(c) by shopware AG</copyright>
        <version>1.0.0</version>
        <license>MIT</license>
    </meta>

    <setup>
        <registrationUrl>http://appdaysdemo.dev.localhost/register</registrationUrl> <!-- replace local url with real one -->
        <secret>myAppSecret</secret>
    </setup>
</manifest>
```

install and activate app
```shell
swdc pshell platform
bin/console app:install --activate AppDaysDemo
```

## Step 2

Generate new migration:
```shell
swdc pshell appdaysdemo
bin/console doctrine:migrations:generate
```

update migration with following content:
```php
    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
        CREATE TABLE `order` (
    		transaction_id char(64)  		NOT NULL PRIMARY KEY,
            order_id       char(64)  		NOT NULL,
			shop_id        varchar(255)  	NOT NULL,
			status         varchar(255)  	NULL,
			session_id     varchar(255)  	NULL,
			return_url     varchar(4096)	NULL,
			CONSTRAINT `fk.order.shop_id`
                    FOREIGN KEY (`shop_id`)
                    REFERENCES `shop` (`shop_id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
        )
        DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL;

        $this->addSql($sql);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE `order`');
    }
```

run migration
```shell
swdc pshell appdaysdemo
bin/console doctrine:migrations:migrate
```

create class `OrderRepository` in `src/Repository` with
```php
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
```