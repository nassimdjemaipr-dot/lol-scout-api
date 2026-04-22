<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260422153212 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE club (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, description LONGTEXT DEFAULT NULL, logo_url VARCHAR(255) DEFAULT NULL, website VARCHAR(255) DEFAULT NULL, is_verified TINYINT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_B8EE3872A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE offer (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(150) NOT NULL, description LONGTEXT NOT NULL, wanted_role VARCHAR(255) NOT NULL, minimum_rank VARCHAR(50) NOT NULL, published_at DATETIME NOT NULL, expires_at DATE DEFAULT NULL, is_active TINYINT NOT NULL, club_id INT NOT NULL, INDEX IDX_29D6873E61190A32 (club_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE played_champion (id INT AUTO_INCREMENT NOT NULL, champion_name VARCHAR(50) NOT NULL, games_played INT NOT NULL, winrate NUMERIC(5, 2) NOT NULL, kda NUMERIC(4, 2) NOT NULL, player_stats_id INT NOT NULL, INDEX IDX_6924CEFB767D7562 (player_stats_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE player (id INT AUTO_INCREMENT NOT NULL, pseudo VARCHAR(100) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, game_role VARCHAR(255) NOT NULL, is_available TINYINT NOT NULL, bio LONGTEXT DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_98197A65A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE player_stats (id INT AUTO_INCREMENT NOT NULL, tier VARCHAR(50) NOT NULL, winrate NUMERIC(5, 2) NOT NULL, average_kda NUMERIC(4, 2) NOT NULL, cs_per_minute NUMERIC(4, 2) NOT NULL, vision_score NUMERIC(5, 2) NOT NULL, ranked_games_count INT NOT NULL, riot_account_id INT NOT NULL, UNIQUE INDEX UNIQ_E8351CECC6EAB37D (riot_account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE riot_account (id INT AUTO_INCREMENT NOT NULL, summoner_name VARCHAR(100) NOT NULL, puuid VARCHAR(78) NOT NULL, region VARCHAR(10) NOT NULL, last_sync_at DATETIME DEFAULT NULL, player_id INT NOT NULL, UNIQUE INDEX UNIQ_79C2D42DCFCB9868 (puuid), INDEX IDX_79C2D42D99E6F5DF (player_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE club ADD CONSTRAINT FK_B8EE3872A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE offer ADD CONSTRAINT FK_29D6873E61190A32 FOREIGN KEY (club_id) REFERENCES club (id)');
        $this->addSql('ALTER TABLE played_champion ADD CONSTRAINT FK_6924CEFB767D7562 FOREIGN KEY (player_stats_id) REFERENCES player_stats (id)');
        $this->addSql('ALTER TABLE player ADD CONSTRAINT FK_98197A65A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE player_stats ADD CONSTRAINT FK_E8351CECC6EAB37D FOREIGN KEY (riot_account_id) REFERENCES riot_account (id)');
        $this->addSql('ALTER TABLE riot_account ADD CONSTRAINT FK_79C2D42D99E6F5DF FOREIGN KEY (player_id) REFERENCES player (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE club DROP FOREIGN KEY FK_B8EE3872A76ED395');
        $this->addSql('ALTER TABLE offer DROP FOREIGN KEY FK_29D6873E61190A32');
        $this->addSql('ALTER TABLE played_champion DROP FOREIGN KEY FK_6924CEFB767D7562');
        $this->addSql('ALTER TABLE player DROP FOREIGN KEY FK_98197A65A76ED395');
        $this->addSql('ALTER TABLE player_stats DROP FOREIGN KEY FK_E8351CECC6EAB37D');
        $this->addSql('ALTER TABLE riot_account DROP FOREIGN KEY FK_79C2D42D99E6F5DF');
        $this->addSql('DROP TABLE club');
        $this->addSql('DROP TABLE offer');
        $this->addSql('DROP TABLE played_champion');
        $this->addSql('DROP TABLE player');
        $this->addSql('DROP TABLE player_stats');
        $this->addSql('DROP TABLE riot_account');
    }
}
