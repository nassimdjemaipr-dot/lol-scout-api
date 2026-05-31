<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260531111934 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE riot_account DROP INDEX IDX_79C2D42D99E6F5DF, ADD UNIQUE INDEX UNIQ_79C2D42D99E6F5DF (player_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE riot_account DROP INDEX UNIQ_79C2D42D99E6F5DF, ADD INDEX IDX_79C2D42D99E6F5DF (player_id)');
    }
}
