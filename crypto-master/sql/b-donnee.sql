INSERT INTO public.action_portefeuille (id_action, type_action)
VALUES
    (1, 'depot'),
    (2, 'retrait'),
    (3, 'vente'),
    (4, 'achat');

INSERT INTO public.crypto (id_crypto, nom_crypto, quantite_crypto, prix_initiale_crypto, date_injection)
VALUES
    (1, 'Bitcoin', 500, 15.25, '2025-02-05 08:30:00'),
    (2, 'Ethereum', 1000, 8.75, '2025-02-05 09:15:00'),
    (3, 'Ripple', 1500, 0.75, '2025-02-05 10:00:00'),
    (4, 'Litecoin', 1200, 5.30, '2025-02-05 11:45:00'),
    (5, 'Cardano', 800, 2.10, '2025-02-05 14:00:00'),
    (6, 'test1', 400000, 20.00, '2025-02-06 11:17:34');

INSERT INTO public.commission (id, taux_achat, taux_vente, date_modification)
VALUES
    (1, 2.50, 1.50, '2025-02-08 08:35:20');


