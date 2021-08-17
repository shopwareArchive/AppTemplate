<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20210816131509 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create table \'shop\'';
    }

    public function up(Schema $schema) : void
    {
        $sql = <<<'SQL'
        CREATE TABLE shop (
            shop_id VARCHAR(255) NOT NULL,
            shop_url VARCHAR(255) NOT NULL,
            shop_secret VARCHAR(255) NOT NULL,
            api_key VARCHAR(255),
            secret_key VARCHAR(255),
            PRIMARY KEY(shop_id)
        )
        DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL;

        $this->addSql($sql);
    }

    public function down(Schema $schema) : void
    {
        $this->addSql('DROP TABLE shop');
    }
}
