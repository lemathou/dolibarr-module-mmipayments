# CHANGELOG MMIPAYMENTS FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.1

- Bloc « Derniers règlements » du tiers (tous modes : CB eTransactions, virement, chèque, espèces…) sur l'onglet Client, via le hook `addMoreRecentObjects` (contexte `thirdpartycomm`). Comble l'absence de liste consolidée des règlements d'un tiers en natif Dolibarr.
- Page dédiée `societe_payments.php` : liste complète des règlements du tiers (factures + commandes/devis), avec total encaissé, atteinte via le lien « voir tout » du bloc.
- Nouvelle méthode DAO `mmi_payments::client_payments($socid, $limit)` : agrège et déduplique les règlements rattachés aux factures (`paiement_facture`) et aux commandes/devis (`paiement_object`) du tiers.
- Correctif `mmi_payments::add()` — `Paiement::datepaye` est désormais normalisé en **timestamp**. Le code construisait une chaîne (concaténation de `' 00:00:00'` sur la valeur reçue), que Dolibarr transmettait telle quelle à `date()` (`create()` → `getNextNumRef()` → `mod_payment_ant` → `get_next_value()`) : conversion implicite en PHP 7, **TypeError fatale en PHP 8**, et date de paiement au 01/01/1970 quand l'appelant fournissait une date au format chaîne. Tous les formats reçus des appelants sont couverts : timestamp, `JJ/MM/AAAA` (saisie UI), ISO 8601 (API Younited), `AAAA-MM-JJ [HH:MM:SS]` ; une date illisible retombe sur `dol_now()` au lieu de 1970.
- Chargement du fichier de langue `other` dans le hook : sans lui, l'en-tête de colonne « Objet lié » (`LinkedObject`, défini seulement dans `other.lang`) s'affichait brut.

## 1.0

Initial version
