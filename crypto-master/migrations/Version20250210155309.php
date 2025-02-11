<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250210155309 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_historique_transactions_crypto');
        $this->addSql('DROP INDEX idx_historique_transactions_portefeuille');
        $this->addSql('DROP INDEX idx_historique_transactions_date');
        $this->addSql('DROP INDEX idx_portefeuille_fille_crypto');
        $this->addSql('DROP INDEX idx_portefeuille_fille_portefeuille');
        $this->addSql('DROP INDEX idx_portefeuille_fille_date');
        $this->addSql('ALTER TABLE utilisateur ADD email_verified BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('DROP INDEX idx_variationcrypto_crypto');
        $this->addSql('DROP INDEX idx_variationcrypto_date');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE INDEX idx_portefeuille_fille_crypto ON portefeuille_fille (id_crypto)');
        $this->addSql('CREATE INDEX idx_portefeuille_fille_portefeuille ON portefeuille_fille (id_portefeuille)');
        $this->addSql('CREATE INDEX idx_portefeuille_fille_date ON portefeuille_fille (date_action)');
        $this->addSql('CREATE INDEX idx_variationcrypto_crypto ON variationcrypto (id_crypto)');
        $this->addSql('CREATE INDEX idx_variationcrypto_date ON variationcrypto (date_variation)');
        $this->addSql('CREATE INDEX idx_historique_transactions_crypto ON historique_transactions (id_crypto)');
        $this->addSql('CREATE INDEX idx_historique_transactions_portefeuille ON historique_transactions (id_portefeuille)');
        $this->addSql('CREATE INDEX idx_historique_transactions_date ON historique_transactions (date_action)');
        $this->addSql('ALTER TABLE utilisateur DROP email_verified');
    }
}
