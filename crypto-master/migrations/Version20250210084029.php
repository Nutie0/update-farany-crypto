<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250210084029 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE action_portefeuille (id_action SERIAL NOT NULL, type_action VARCHAR(50) NOT NULL, PRIMARY KEY(id_action))');
        $this->addSql('CREATE TABLE commission (id SERIAL NOT NULL, taux_achat NUMERIC(5, 2) NOT NULL, taux_vente NUMERIC(5, 2) NOT NULL, date_modification TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE crypto (id_crypto SERIAL NOT NULL, nom_crypto VARCHAR(50) NOT NULL, quantite_crypto INT NOT NULL, prix_initiale_crypto NUMERIC(15, 2) DEFAULT NULL, date_injection TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id_crypto))');
        $this->addSql('CREATE TABLE historique_transactions (id SERIAL NOT NULL, id_portefeuille INT NOT NULL, id_crypto INT NOT NULL, nom_crypto VARCHAR(50) NOT NULL, type_action VARCHAR(50) NOT NULL, nbrcrypto NUMERIC(15, 8) NOT NULL, prix NUMERIC(15, 2) NOT NULL, prixtotal NUMERIC(15, 2) NOT NULL, taux_commission NUMERIC(4, 2) DEFAULT NULL, montant_commission NUMERIC(15, 2) DEFAULT NULL, prix_total_avec_commission NUMERIC(15, 2) DEFAULT NULL, date_action TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE historique_utilisateur (id_historique_utilisateur SERIAL NOT NULL, id INT NOT NULL, id_action INT NOT NULL, somme NUMERIC(15, 2) NOT NULL, date_historique TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, statut VARCHAR(20) NOT NULL, PRIMARY KEY(id_historique_utilisateur))');
        $this->addSql('CREATE INDEX IDX_C4B26DD5BF396750 ON historique_utilisateur (id)');
        $this->addSql('CREATE INDEX IDX_C4B26DD561FB397F ON historique_utilisateur (id_action)');
        $this->addSql('CREATE TABLE portefeuille (id SERIAL NOT NULL, id_utilisateur INT NOT NULL, solde_utilisateur NUMERIC(15, 2) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2955FFFE50EAE44 ON portefeuille (id_utilisateur)');
        $this->addSql('CREATE TABLE portefeuille_fille (id_portefeuille_fille SERIAL NOT NULL, id_crypto INT NOT NULL, id_portefeuille INT NOT NULL, id_action INT NOT NULL, date_action TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, nbr_crypto INT NOT NULL, prix_total_crypto VARCHAR(50) DEFAULT NULL, prix_achat DOUBLE PRECISION NOT NULL, montant_commission NUMERIC(10, 2) DEFAULT NULL, taux_commission NUMERIC(5, 2) DEFAULT NULL, prix_total_avec_commission NUMERIC(20, 2) DEFAULT NULL, PRIMARY KEY(id_portefeuille_fille))');
        $this->addSql('CREATE INDEX IDX_87A89FC74E1F9D68 ON portefeuille_fille (id_crypto)');
        $this->addSql('CREATE INDEX IDX_87A89FC7A948A8C ON portefeuille_fille (id_portefeuille)');
        $this->addSql('CREATE INDEX IDX_87A89FC761FB397F ON portefeuille_fille (id_action)');
        $this->addSql('CREATE TABLE "user" (id SERIAL NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, name VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
        $this->addSql('CREATE TABLE utilisateur (id_utilisateur SERIAL NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, token VARCHAR(1000) DEFAULT NULL, nom VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id_utilisateur))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1D1C63B3E7927C74 ON utilisateur (email)');
        $this->addSql('CREATE TABLE variationcrypto (id_variation SERIAL NOT NULL, id_crypto INT NOT NULL, pourcentagevariation NUMERIC(5, 2) NOT NULL, prixevoluer NUMERIC(15, 2) DEFAULT NULL, date_variation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id_variation))');
        $this->addSql('CREATE INDEX IDX_1864ABDC4E1F9D68 ON variationcrypto (id_crypto)');
        $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE OR REPLACE FUNCTION notify_messenger_messages() RETURNS TRIGGER AS $$
            BEGIN
                PERFORM pg_notify(\'messenger_messages\', NEW.queue_name::text);
                RETURN NEW;
            END;
        $$ LANGUAGE plpgsql;');
        $this->addSql('DROP TRIGGER IF EXISTS notify_trigger ON messenger_messages;');
        $this->addSql('CREATE TRIGGER notify_trigger AFTER INSERT OR UPDATE ON messenger_messages FOR EACH ROW EXECUTE PROCEDURE notify_messenger_messages();');
        $this->addSql('ALTER TABLE historique_utilisateur ADD CONSTRAINT FK_C4B26DD5BF396750 FOREIGN KEY (id) REFERENCES portefeuille (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE historique_utilisateur ADD CONSTRAINT FK_C4B26DD561FB397F FOREIGN KEY (id_action) REFERENCES action_portefeuille (id_action) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE portefeuille ADD CONSTRAINT FK_2955FFFE50EAE44 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id_utilisateur) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE portefeuille_fille ADD CONSTRAINT FK_87A89FC74E1F9D68 FOREIGN KEY (id_crypto) REFERENCES crypto (id_crypto) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE portefeuille_fille ADD CONSTRAINT FK_87A89FC7A948A8C FOREIGN KEY (id_portefeuille) REFERENCES portefeuille (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE portefeuille_fille ADD CONSTRAINT FK_87A89FC761FB397F FOREIGN KEY (id_action) REFERENCES action_portefeuille (id_action) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE variationcrypto ADD CONSTRAINT FK_1864ABDC4E1F9D68 FOREIGN KEY (id_crypto) REFERENCES crypto (id_crypto) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE historique_utilisateur DROP CONSTRAINT FK_C4B26DD5BF396750');
        $this->addSql('ALTER TABLE historique_utilisateur DROP CONSTRAINT FK_C4B26DD561FB397F');
        $this->addSql('ALTER TABLE portefeuille DROP CONSTRAINT FK_2955FFFE50EAE44');
        $this->addSql('ALTER TABLE portefeuille_fille DROP CONSTRAINT FK_87A89FC74E1F9D68');
        $this->addSql('ALTER TABLE portefeuille_fille DROP CONSTRAINT FK_87A89FC7A948A8C');
        $this->addSql('ALTER TABLE portefeuille_fille DROP CONSTRAINT FK_87A89FC761FB397F');
        $this->addSql('ALTER TABLE variationcrypto DROP CONSTRAINT FK_1864ABDC4E1F9D68');
        $this->addSql('DROP TABLE action_portefeuille');
        $this->addSql('DROP TABLE commission');
        $this->addSql('DROP TABLE crypto');
        $this->addSql('DROP TABLE historique_transactions');
        $this->addSql('DROP TABLE historique_utilisateur');
        $this->addSql('DROP TABLE portefeuille');
        $this->addSql('DROP TABLE portefeuille_fille');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('DROP TABLE utilisateur');
        $this->addSql('DROP TABLE variationcrypto');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
