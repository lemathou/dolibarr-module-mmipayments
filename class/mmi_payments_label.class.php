<?php
/* Copyright (C) 2026 MMI Mathieu Moulin <contact@iprospective.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    mmipayments/class/mmi_payments_label.class.php
 * \brief   Libellé d'un mode de paiement (logique pure, sans dépendance Dolibarr).
 */

class mmi_payments_label
{
	/**
	 * Résout le libellé d'un mode de paiement.
	 *
	 * @param string|null $mode_code  Code du mode (ex. "3")
	 * @param string|null $mode_label Libellé de secours
	 * @param callable    $trans      Traducteur (clé => libellé traduit)
	 * @return string
	 */
	public static function resolve($mode_code, $mode_label, callable $trans)
	{
		if (empty($mode_code)) {
			return $mode_label ? $mode_label : '';
		}

		$key = 'PaymentType'.$mode_code;
		$label = $trans($key);
		if ($label != $key) {
			return $label;
		}

		return $mode_label ? $mode_label : $mode_code;
	}
}
