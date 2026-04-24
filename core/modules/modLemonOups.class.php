<?php
/* Copyright (C) 2026 Lemon <hello@hellolemon.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

/**
 *  \defgroup   lemonoups   Module LemonOups
 *  \brief      LemonOups : bouton "Émettre un avoir et solder" en un clic
 *  \file       htdocs/lemonoups/core/modules/modLemonOups.class.php
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 *  Descripteur du module LemonOups.
 *
 *  Ajoute un bouton "Émettre un avoir et solder" sur la fiche d'une facture client
 *  validée strictement sans aucun paiement, qui enchaîne en un clic :
 *    création de l'avoir avec les mêmes lignes → validation →
 *    conversion en remise disponible → imputation sur la facture d'origine →
 *    marquage de la facture d'origine comme payée.
 */
class modLemonOups extends DolibarrModules
{
	/**
	 *  Constructeur.
	 *
	 *  @param  DoliDB  $db  Handler BDD
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->numero = 500260;
		$this->rights_class = 'lemonoups';
		$this->family = "financial";
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Bouton 'Émettre un avoir et solder' en un clic sur les factures client";
		$this->descriptionlong = "Ajoute un bouton sur la fiche facture qui enchaîne automatiquement : création de l'avoir avec les mêmes lignes, validation, conversion en remise disponible, imputation sur la facture d'origine, marquage comme payée. Uniquement disponible pour les factures validées strictement sans paiement.";
		$this->editor_name = 'Lemon';
		$this->editor_url = 'https://hellolemon.fr';
		$this->version = '0.3.1';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'bill';

		$this->module_parts = array(
			'hooks' => array('invoicecard'),
		);

		$this->dirs = array();
		$this->config_page_url = array("setup.php@lemonoups");
		$this->hidden = false;
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array("lemonoups@lemonoups");
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(22, 0, 0);

		$this->tables = array();

		$this->const = array();

		$this->rights = array();

		$this->menu = array();
	}

	/**
	 *  Activation du module.
	 *
	 *  @param  string  $options  Options
	 *  @return int
	 */
	public function init($options = '')
	{
		$sql = array();
		return $this->_init($sql, $options);
	}

	/**
	 *  Désactivation du module.
	 *
	 *  @param  string  $options  Options
	 *  @return int
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
