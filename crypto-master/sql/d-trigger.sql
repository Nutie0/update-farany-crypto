CREATE TRIGGER update_solde_after_approval
AFTER INSERT ON portefeuille_fille
FOR EACH ROW
WHEN (NEW.statut = 'approuve')
EXECUTE FUNCTION update_solde_portefeuille();

CREATE TRIGGER update_solde_after_transaction
AFTER INSERT ON portefeuille_fille
FOR EACH ROW
EXECUTE FUNCTION update_portefeuille_solde();



CREATE OR REPLACE FUNCTION maj_solde_utilisateur()
RETURNS trigger AS $$
BEGIN
    -- Ne s'exécuter que pour les dépôts et retraits
    IF NEW.type_action IN ('depot', 'retrait') THEN
        -- Ne mettre à jour le solde que si le statut est 'approuve'
        IF NEW.statut = 'approuve' THEN
            -- Cas d'un dépôt
            IF NEW.type_action = 'depot' THEN
                UPDATE portefeuille
                SET solde_utilisateur = solde_utilisateur + NEW.montant
                WHERE id = NEW.id;

            -- Cas d'un retrait
            ELSIF NEW.type_action = 'retrait' THEN
                -- Vérifier si le solde est suffisant
                IF (SELECT solde_utilisateur FROM portefeuille WHERE id = NEW.id) >= NEW.montant THEN
                    UPDATE portefeuille
                    SET solde_utilisateur = solde_utilisateur - NEW.montant
                    WHERE id = NEW.id;
                ELSE
                    RAISE EXCEPTION 'Solde insuffisant';
                END IF;
            END IF;
        END IF;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;


-- Création du trigger associé à la fonction ci-dessus
-- Ici, nous utilisons la table 'transactions' comme table déclencheur

CREATE TRIGGER trg_maj_solde_utilisateur
BEFORE INSERT OR UPDATE ON historique_transactions
FOR EACH ROW
EXECUTE FUNCTION maj_solde_utilisateur();

