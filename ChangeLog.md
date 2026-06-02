# CHANGELOG MMIPAYMENTS FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.1

- Bloc « Derniers règlements » du tiers (tous modes : CB eTransactions, virement, chèque, espèces…) sur l'onglet Client, via le hook `addMoreRecentObjects` (contexte `thirdpartycomm`). Comble l'absence de liste consolidée des règlements d'un tiers en natif Dolibarr.
- Page dédiée `societe_payments.php` : liste complète des règlements du tiers (factures + commandes/devis), avec total encaissé, atteinte via le lien « voir tout » du bloc.
- Nouvelle méthode DAO `mmi_payments::client_payments($socid, $limit)` : agrège et déduplique les règlements rattachés aux factures (`paiement_facture`) et aux commandes/devis (`paiement_object`) du tiers.

## 1.0

Initial version
