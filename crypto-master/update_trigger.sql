-- Supprimer l'ancien trigger et fonction
DROP TRIGGER IF EXISTS trg_maj_solde_utilisateur ON historique_utilisateur;
DROP FUNCTION IF EXISTS maj_solde_utilisateur();

-- Créer la nouvelle fonction
CREATE OR REPLACE FUNCTION maj_solde_utilisateur() 
RETURNS TRIGGER AS $$
BEGIN
    -- Ne mettre à jour le solde que si le statut est 'approuve'
    IF NEW.statut = 'approuve' THEN
        -- Dépôt (id_action = 1)
        IF NEW.id_action = 1 THEN
            UPDATE portefeuille 
            SET solde_utilisateur = solde_utilisateur + NEW.somme
            WHERE id = NEW.id;
        
        -- Retrait (id_action = 2)
        ELSIF NEW.id_action = 2 THEN
            -- Vérifier si le solde est suffisant
            IF (SELECT solde_utilisateur FROM portefeuille WHERE id = NEW.id) >= NEW.somme THEN
                UPDATE portefeuille
                SET solde_utilisateur = solde_utilisateur - NEW.somme
                WHERE id = NEW.id;
            ELSE
                RAISE EXCEPTION 'Solde insuffisant';
            END IF;
        END IF;
    END IF;
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Créer le nouveau trigger
CREATE TRIGGER trg_maj_solde_utilisateur
AFTER INSERT OR UPDATE ON historique_utilisateur
FOR EACH ROW
EXECUTE FUNCTION maj_solde_utilisateur();
