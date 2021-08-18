<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210818100052 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

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
}
