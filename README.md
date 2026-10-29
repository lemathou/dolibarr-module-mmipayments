# MMIPAYMENTS FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## Features

Description of the module...

## Onglet « Paiements » et point d'extension

Le module pose un onglet **Paiements** sur le tiers, le devis, la commande et la
facture (`payments.php`). Il y liste les règlements rattachés à l'objet, tous
modes confondus — factures via `paiement_facture`, devis / commandes / tiers via
`paiement_object`.

Ce que le socle **ne peut pas** connaître, ce sont les **tentatives** : la table
`llx_paiement` ne contient que les paiements aboutis. Un paiement CB refusé, une
échéance d'un 3x, un motif de refus n'existent que dans les tables du module de
paiement concerné. D'où le point d'extension ci-dessous : chaque module de
paiement complète la vue avec ce que lui seul sait.

### Contribuer des lignes depuis un module de paiement

1. Déclarer le contexte `mmipaymentstab` dans les `hooks` du descripteur du module.
2. Implémenter `addPaymentRows($parameters, &$object, &$action, $hookmanager)`
   dans la classe d'actions, et remplir `$this->results['payment_rows']`.

`$parameters` porte `objecttype` (`Client` | `Facture` | `Commande` | `Propal`),
`id` l'identifiant de l'objet affiché, et `cycle` — les documents du cycle de vente
auquel il appartient (devis, commandes, factures qui se répondent), résolus une fois
par le socle.

**Utilisez `cycle`, pas seulement `id`.** Un acompte encaissé sur le devis et le solde
réglé sur la facture concernent la même affaire : le socle remonte tout le cycle, et un
module qui filtrerait sur le seul document ouvert rendrait la vue incohérente — les
règlements de la facture liée s'afficheraient, mais pas les tentatives de cette même
facture. Pour un tiers (`Client`), `cycle` est vide : le périmètre est le tiers entier.

Chaque ligne retournée est un tableau associatif :

| Clé | Type | Rôle |
|---|---|---|
| `date` | int | timestamp — sert au tri chronologique (requis) |
| `amount` | float | montant |
| `ref` | string | HTML — référence, généralement un lien |
| `objects` | string | HTML — objets métier rattachés |
| `mode` | string | HTML — libellé du moyen de paiement |
| `status` | string | HTML — état (badge, motif de refus en infobulle…) |
| `actions` | string | HTML — actions proposées (formulaire POST) |
| `is_payment` | bool | `true` = encaissement réel (compte dans le total) ; `false` = tentative sans suite, affichée en retrait |
| `fk_paiement` | int | *optionnel* — voir « enrichissement » ci-dessous |

### Enrichir plutôt que dupliquer

Une transaction aboutie est connue **des deux côtés** : du socle comme règlement,
du module comme tentative réussie. Retourner une ligne pour elle la ferait
apparaître deux fois.

Dans ce cas, renseigner `fk_paiement` avec l'identifiant du règlement au lieu de
composer une ligne complète : les champs fournis **complètent** la ligne existante
— typiquement `status` et `actions`, que le module sait préciser — sans écraser
ceux qu'il ne fournit pas.

### Ordre d'affichage

Les lignes de tous les modules sont fusionnées puis triées par date décroissante :
la tentative refusée du 12 et l'encaissement du 14 se lisent sur la même
chronologie. Quand deux modules interviennent sur une même ligne, l'ordre
d'exécution se règle par la propriété `priority` de leur classe d'actions
(défaut Dolibarr : 50 ; plus grand = exécuté plus tard).

### Actions

Le socle ne porte **aucune** action : recréer un paiement supprimé, relancer une
échéance sont des gestes propres à un moyen de paiement. Le module rend son
formulaire dans `actions`, et traite le POST via le hook `doPaymentTabAction`
(même contexte). Retourner une valeur non nulle déclenche la redirection en GET
qui évite le re-POST.

## Liste globale et second point d'extension

`list.php` (menu *Facturation → Paiements*) montre les mêmes données à l'échelle du
parc, en deux modes :

- **Règlements** — les encaissements, paginés en SQL par le socle ;
- **Tentatives** — contribuées par les modules de paiement, filtrables par statut.
  Le bouton *Paiements à recréer* n'est pas un troisième mode : c'est celui-ci
  préfiltré sur `missing`.

### Contribuer des lignes à la liste globale

1. Déclarer le contexte `mmipaymentslist` dans les `hooks` du descripteur du module.
2. Implémenter `addGlobalPaymentRows($parameters, &$object, &$action, $hookmanager)`.

`$parameters['filters']` porte `mode` (ne répondre que sur `attempts`), `status`,
`socid`, `datefrom`, `dateto`, `limit` et `offset`.

Les lignes ont le **même format** que celles de l'onglet, avec une colonne de plus :
`thirdparty` (HTML) — la liste étant tous tiers confondus, la ligne doit dire de qui
elle parle. `fk_paiement` n'y a pas cours : il n'y a pas de ligne socle à enrichir.

### Statuts

Le vocabulaire est défini par le socle (`mmi_payments::attempt_statuses()`) ; chaque
module le traduit dans ses propres tables :

| Statut | Sens |
|---|---|
| `unsettled` | *(défaut)* tout ce qui ne s'est pas soldé par un règlement : refus **et** transactions abouties sans paiement Dolibarr |
| `ko` | refus |
| `missing` | abouties dont le paiement Dolibarr manque — les régularisations à faire |
| `ok` | abouties, paiement présent |
| `all` | tout |

Le défaut est `unsettled` parce que les tentatives **abouties** sont de loin les plus
nombreuses : les afficher par défaut noierait la vue et la ferait doublonner le mode
*Règlements*, qui les montre déjà.

### Pagination — la déléguer ou la subir

La vue globale porte plusieurs milliers de lignes. Un module qui **honore `limit` et
`offset`** renvoie, à côté de ses lignes, `payment_total` — le total **avant**
pagination :

```php
$this->results = array(
    'payment_rows'  => $rows,   // la page demandée, telle quelle
    'payment_total' => $total,  // total avant pagination
);
```

Le socle prend alors les lignes telles quelles et le compteur est exact. Rendre le
total **même quand la page demandée est vide** (au-delà de la fin) : l'appelant s'en
sert pour revenir à la première page.

Sans `payment_total`, le socle retombe sur un repli : il trie ce qu'il a reçu, le
plafonne à `limit` et **affiche** que la liste est tronquée. Correct, mais le module
aura chargé toutes ses lignes pour n'en montrer qu'une page.

### Une seule contribution par contexte

`HookManager` fusionne les retours des modules avec `array_merge` : deux modules
renvoyant `payment_rows` s'**écrasent** l'un l'autre, le dernier gagne. Un seul
module de paiement contribue aujourd'hui (`mbietransactions`), donc la question ne
se pose pas — mais elle se posera au deuxième. La correction sera de suffixer la clé
par module (`payment_rows_<module>`) et de collecter toutes les clés du préfixe ;
elle n'a pas été faite d'avance pour ne pas imposer une contrainte de version
couplée sans besoin réel. Cette limite vaut aussi pour `addPaymentRows`.


<!--
![Screenshot mmipayments](img/screenshot_mmipayments.png?raw=true "MMIPayments"){imgmd}
-->

Other external modules are available on [Dolistore.com](https://www.dolistore.com).

## Translations

Translations can be completed manually by editing files into directories *langs*.

<!--
This module contains also a sample configuration for Transifex, under the hidden directory [.tx](.tx), so it is possible to manage translation using this service.

For more informations, see the [translator's documentation](https://wiki.dolibarr.org/index.php/Translator_documentation).

There is a [Transifex project](https://transifex.com/projects/p/dolibarr-module-template) for this module.
-->

<!--

## Installation

### From the ZIP file and GUI interface

- If you get the module in a zip file (like when downloading it from the market place [Dolistore](https://www.dolistore.com)), go into
menu ```Home - Setup - Modules - Deploy external module``` and upload the zip file.

Note: If this screen tell you there is no custom directory, check your setup is correct:

- In your Dolibarr installation directory, edit the ```htdocs/conf/conf.php``` file and check that following lines are not commented:

    ```php
    //$dolibarr_main_url_root_alt ...
    //$dolibarr_main_document_root_alt ...
    ```

- Uncomment them if necessary (delete the leading ```//```) and assign a sensible value according to your Dolibarr installation

    For example :

    - UNIX:
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = '/var/www/Dolibarr/htdocs/custom';
        ```

    - Windows:
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = 'C:/My Web Sites/Dolibarr/htdocs/custom';
        ```

### From a GIT repository

- Clone the repository in ```$dolibarr_main_document_root_alt/mmipayments```

```sh
cd ....../custom
git clone git@github.com:gitlogin/mmipayments.git mmipayments
```

### <a name="final_steps"></a>Final steps

From your browser:

  - Log into Dolibarr as a super-administrator
  - Go to "Setup" -> "Modules"
  - You should now be able to find and enable the module

-->

## Licenses

### Main code

GPLv3 or (at your option) any later version. See file COPYING for more information.

### Documentation

All texts and readmes are licensed under GFDL.

## Prérequis

- Dolibarr 20.0 (branche `20.0`).
- Modules MMI : `mmicommon`. **`mmicommon` doit être activé en premier.**
- Modules cœur : `Facture`, `Commande`, `Propale`, `Societe`.

## Installation

1. Copier dans `htdocs/custom/mmipayments`.
2. Activer `mmicommon` puis le module.
3. Vérifier les droits.

## Licence

GPLv3+ (voir `COPYING`), documentation GFDL.

## Changelog

Voir [CHANGELOG.md](CHANGELOG.md).
