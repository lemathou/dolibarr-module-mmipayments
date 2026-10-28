# CHANGELOG MMIPAYMENTS FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.5

- **Vue « Tentatives » dans la liste globale** (menu Facturation → Paiements) : les tentatives de paiement, tous tiers confondus, filtrables par statut — refus, régularisations à faire, abouties, ou tout. Jusqu'ici un refus CB ne se voyait qu'en ouvrant la fiche du document concerné ; rien ne les remontait à l'échelle du parc.
- Le bouton « Paiements à recréer » n'est plus un mode distinct : c'est la vue « Tentatives » préfiltrée sur `missing`. Une vue de travail, pas une nature de donnée de plus.
- **Pagination réparée**, mode « Règlements » compris : `print_barre_liste()` n'affiche la navigation que si le nombre de lignes reçues dépasse la limite (convention du coeur : demander `limit + 1`). La liste s'arrêtait donc à la première page **sans le moindre lien vers la suivante** — 12 750 règlements dont 200 atteignables.
- Les filtres tiers et période survivent au changement de page et au changement de mode : sans eux, la page 2 d'une recherche filtrée renvoyait la page 2 de tout, sans rien signaler.
- Demander une page au-delà de la fin (après un filtrage qui réduit le résultat) ramène à la première page au lieu d'afficher une liste vide, comme les listes du coeur.
- Le point d'extension `addGlobalPaymentRows` accepte désormais la **pagination déléguée** : un module qui honore `limit`/`offset` renvoie `payment_total` et ses lignes sont prises telles quelles. Sans ce champ, le socle retombe sur l'ancien comportement (tri et plafonnement annoncés) — les deux sens de compatibilité sont préservés.
- Le titre de la page et l'entrée de menu s'appelaient « Règlements du tiers », libellé hérité du bloc de la fiche tiers et trompeur sur une liste qui est justement globale.

## 1.4

- L'onglet « Paiements » d'un devis, d'une commande ou d'une facture montre désormais les règlements de **tout le cycle** devis / commande / facture, et plus du seul document ouvert. Un acompte encaissé sur le devis et le solde réglé sur la facture concernent la même affaire : ne montrer que le document ouvert répondait faux à « ce client a-t-il payé ? ». Même périmètre que le bloc paiements de la fiche devis (`mmi_payments::paiements()`) — vérifié identique sur 12 devis.
- `mmi_payments::cycle_objects()` : résolution du cycle via `llx_element_element`, dans les **deux sens** (selon la façon dont les documents ont été créés, la facture est tantôt la cible du devis, tantôt sa source) et de façon **transitive bornée** — depuis une facture on retrouve ainsi le devis dont elle découle ET ses factures sœurs. Sans cela la vue était asymétrique : le devis voyait tout, la facture presque rien.
- Un règlement remonté par le cycle mais **non rattaché au document ouvert** est marqué « autre document » (le détail des documents porteurs reste dans la colonne « Objet lié », le marqueur ne le répète pas).
- Le cycle résolu est transmis aux modules de paiement dans les paramètres du hook `addPaymentRows` : sans lui, ils filtreraient sur le seul document ouvert et l'onglet deviendrait incohérent — les règlements de la facture liée s'afficheraient, mais pas les tentatives CB de cette même facture.

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
