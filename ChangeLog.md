# CHANGELOG MMIPAYMENTS FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.3

- **Entrée de menu** sous Facturation → Règlements : la liste globale devient accessible. Jusqu'ici le module n'avait aucune entrée de menu ni onglet — tout passait par la fiche d'un tiers.
- Réécriture de `list.php`, qui était un brouillon jamais exposé (aucun contrôle de droits, entité et préfixe de table en dur, pas de pagination ni de filtres) : droit `facture/lire`, pagination, filtres tiers et période, liens vers les paiements et les objets métier, total.
- **Vue « Paiements à recréer »** : les transactions abouties côté banque dont le paiement Dolibarr n'existe pas (ou plus), sur l'ensemble du parc. Ces lignes ne peuvent pas venir du socle — une transaction sans paiement n'est par définition pas dans `llx_paiement` : elles sont contribuées par les modules de paiement via le hook `addGlobalPaymentRows` (contexte `mmipaymentslist`). C'est la réponse à la question qui n'en avait aucune : où sont les régularisations à faire ?
- `mmi_payments::global_payment_rows()` : règlements paginés en SQL pour le mode « Règlements », agrégation des contributions des modules pour le mode « à recréer ». Le plafonnement du second est **affiché** et jamais silencieux.

> Déploiement : contrairement aux hooks et aux onglets (une const), une entrée de menu se matérialise par une ligne dans `llx_menu`. Il faut donc appeler `insert_menus()` du descripteur (ou réactiver le module) après la mise à jour du code.

## 1.2

- Onglet **Paiements** sur le tiers, le devis, la commande et la facture (`payments.php`) : règlements rattachés à l'objet, tous modes confondus.
- Nouveau DAO `mmi_payments::object_payments($objecttype, $id, $limit)`, généralisation de `client_payments()` (conservé comme raccourci). Le filtre porte sur l'identifiant du règlement plutôt que sur les jointures : tous les objets rattachés à un règlement retenu sont collectés, et pas seulement celui par lequel on l'a trouvé.
- **Point d'extension `addPaymentRows`** (contexte `mmipaymentstab`) : un module de paiement contribue ses propres lignes — tentatives échouées, échéanciers, actions de relance —, que le socle fusionne avec les règlements et trie chronologiquement. `llx_paiement` ne contenant que les paiements aboutis, seul le module de paiement connaît les tentatives. Contrat documenté dans le README.
- Mécanisme d'**enrichissement** (`fk_paiement`) : une transaction connue des deux côtés complète la ligne du règlement au lieu de la dupliquer.
- Hook `doPaymentTabAction` : le socle ne porte aucune action, chaque module traite les siennes.
- Suppression de `societe_payments.php`, remplacée par l'onglet ; le lien « voir tous les règlements » du bloc de l'onglet Client y renvoie.

## 1.1

- Bloc « Derniers règlements » du tiers (tous modes : CB eTransactions, virement, chèque, espèces…) sur l'onglet Client, via le hook `addMoreRecentObjects` (contexte `thirdpartycomm`). Comble l'absence de liste consolidée des règlements d'un tiers en natif Dolibarr.
- Page dédiée `societe_payments.php` : liste complète des règlements du tiers (factures + commandes/devis), avec total encaissé, atteinte via le lien « voir tout » du bloc.
- Nouvelle méthode DAO `mmi_payments::client_payments($socid, $limit)` : agrège et déduplique les règlements rattachés aux factures (`paiement_facture`) et aux commandes/devis (`paiement_object`) du tiers.
- Correctif `mmi_payments::add()` — `Paiement::datepaye` est désormais normalisé en **timestamp**. Le code construisait une chaîne (concaténation de `' 00:00:00'` sur la valeur reçue), que Dolibarr transmettait telle quelle à `date()` (`create()` → `getNextNumRef()` → `mod_payment_ant` → `get_next_value()`) : conversion implicite en PHP 7, **TypeError fatale en PHP 8**, et date de paiement au 01/01/1970 quand l'appelant fournissait une date au format chaîne. Tous les formats reçus des appelants sont couverts : timestamp, `JJ/MM/AAAA` (saisie UI), ISO 8601 (API Younited), `AAAA-MM-JJ [HH:MM:SS]` ; une date illisible retombe sur `dol_now()` au lieu de 1970.
- Chargement du fichier de langue `other` dans le hook : sans lui, l'en-tête de colonne « Objet lié » (`LinkedObject`, défini seulement dans `other.lang`) s'affichait brut.

## 1.0

Initial version
