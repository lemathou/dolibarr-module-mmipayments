<?php

require_once 'main_load.inc.php';

llxHeader("", $langs->trans("MMIPaymentsArea"));

$sql = 'SELECT DISTINCT p.*,
    c.code as paiement_code, c.libelle as paiement_libelle,
    ba.rowid as bid, ba.ref as bref, ba.label as blabel, ba.number, ba.account_number as account_number, ba.fk_accountancy_journal as accountancy_journal,
    s.rowid as socid, s.nom as name, s.email,
    po.objecttype, po.fk_object,
    pocs.rowid as o_socid, pocs.nom as o_name, pocs.email o_email,
    pocs2.rowid as o2_socid, pocs2.nom as o2_name, pocs2.email o2_email
    FROM llx_paiement as p
    LEFT JOIN llx_c_paiement as c ON p.fk_paiement = c.id
    LEFT JOIN llx_bank as b ON p.fk_bank = b.rowid
    LEFT JOIN llx_bank_account as ba ON b.fk_account = ba.rowid
    LEFT JOIN llx_paiement_facture as pf ON p.rowid = pf.fk_paiement
    LEFT JOIN llx_facture as f ON pf.fk_facture = f.rowid
    LEFT JOIN llx_societe as s ON f.fk_soc = s.rowid
    LEFT JOIN llx_paiement_object as po ON po.fk_paiement = p.rowid
    LEFT JOIN llx_commande as poc ON poc.rowid = po.fk_object AND po.objecttype="Commande"
    LEFT JOIN llx_societe as pocs ON pocs.rowid = poc.fk_soc
    LEFT JOIN llx_propal as poc2 ON poc2.rowid = po.fk_object AND po.objecttype="Propal"
    LEFT JOIN llx_societe as pocs2 ON pocs2.rowid = poc2.fk_soc
    WHERE p.entity IN (1)
    ORDER BY p.ref DESC';

?>
<style type="text/css">
    table#payments td {
        text-align: right;
    }
</style>
<table border="1" id="payments">
<caption>Réglements</caption>
<thead>
    <th>Réf</th>
    <th>Date</th>
    <th>Tiers Nom</th>
    <th>Tiers Email</th>
    <th>Type</th>
    <th>Note</th>
    <th>Montant</th>
    <th>Objet</th>
</thead>
<tbody>
<?php
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        echo '<tr>';
        echo '<td>'.$obj->ref.'</td>';
        echo '<td>'.$obj->datec.'</td>';
        echo '<td>'.($obj->name ?$obj->name :($obj->o_name ?$obj->o_name :$obj->o2_name)).'</td>';
        echo '<td>'.($obj->email ?$obj->email :($obj->o_email ?$obj->o_email :$obj->o2_email)).'</td>';
        echo '<td>'.$obj->paiement_libelle.'</td>';
        echo '<td>'.$obj->note.'</td>';
        echo '<td>'.number_format(round($obj->amount, 2), 2, '.', '').'</td>';
        echo '<td>'.$obj->objecttype.' '.$obj->fk_object.'</td>';
        echo '</tr>';
    }
}
?>
</tbody>
</table>
