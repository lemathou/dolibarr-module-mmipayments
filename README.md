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

`$parameters` porte `objecttype` (`Client` | `Facture` | `Commande` | `Propal`)
et `id`, l'identifiant de l'objet affiché.

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
