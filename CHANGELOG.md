# Changelog — MMIPayments

Toutes les évolutions notables, avec dates (ISO).

## [1.5] — 2026-10-28

### Modifié
- Migration Dolibarr 20.0 (RM3565).
- Documentation : README complet (fonctionnalités, prérequis, configuration) et CHANGELOG daté (RM3569).

## Historique daté

- 2026-10-28 — Merge branch '3580-security' into '20.0'
- 2026-10-28 — RM3580 mmipayments : loadobject retourne null si l'objet n'existe pas
- 2026-10-28 — Merge branch '3579-home-mmipayments' into '20.0'
- 2026-10-28 — RM3579 mmipayments : vraie page d'accueil + entree de menu
- 2026-10-28 — Merge branch '3567-lang-mmipayments' into '20.0'
- 2026-10-28 — RM3567 mmipayments : cles de langue deplacees depuis le coeur
- 2026-10-28 — Merge branch '3581-tests-mmipayments' into '20.0'
- 2026-10-28 — RM3581 mmipayments : extraction de mode_label en classe pure + tests unitaires
- 2026-10-28 — Merge branch '3567-cleanup-mmipayments' into '20.0'
- 2026-10-28 — RM3567 mmipayments : retrait du debug et du bloc mort if(false)
- 2026-10-28 — Merge 19.0 into 20.0
- 2026-10-13 — Merge branch '3459-menu-url-custom' into '19.0'
- 2026-10-13 — RM3459 : l'entrée de menu « Règlements » menait à un 404
- 2026-08-25 — Merge branch '2817-liste-globale-des-paiements-sous-onglet' into '19.0'
- 2026-08-25 — RM2817 — Vue « Tentatives » dans la liste globale + pagination réparée
- 2026-08-24 — Merge branch '2803-cycle-devis-commande-facture' into '19.0'
- 2026-08-24 — RM2803 — L'onglet Paiements couvre tout le cycle devis / commande / facture
- 2026-08-24 — Merge branch '2805-mmipayments-entree-de-menu-facturation-r' into '19.0'
- 2026-08-24 — Merge branch '2803-mmipayments-onglet-paiements-generique-s' into '19.0'
- 2026-08-24 — RM2805 — Le mode « tentatives » passe explicitement le filtre missing_payment
- 2026-08-24 — RM2805 — Entrée de menu Facturation → Règlements + liste globale et vue « à recréer »
- 2026-08-24 — RM2803 — Le bloc de l'onglet Client renvoie vers l'onglet Paiements
- 2026-08-24 — RM2803 — Onglet « Paiements » générique + point d'extension pour les modules de paiement
- 2026-08-24 — Merge branch '1810-paiements-tiers' into '19.0'
- 2026-08-24 — RM1810 — mmi_payments::add() : datepaye en timestamp (fatal PHP 8) + libellé « Objet lié »
