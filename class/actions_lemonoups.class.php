<?php
/* Copyright (C) 2026 Lemon <hello@hellolemon.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *  \file       htdocs/lemonoups/class/actions_lemonoups.class.php
 *  \ingroup    lemonoups
 *  \brief      Hook invoicecard : bouton "Émettre un avoir et solder" et handler associé.
 */

/**
 *  Hooks du module LemonOups.
 *
 *  Fournit deux points d'accrochage sur la fiche facture :
 *    addMoreActionsButtons : affiche le bouton "Émettre un avoir et solder"
 *                            (actif ou grisé selon l'état de la facture).
 *    doActions             : traite le clic sur le bouton via action=lemon_annuler.
 */
class ActionsLemonoups
{
	/** @var DoliDB */
	public $db;

	/** @var string|null */
	public $error;

	/** @var array */
	public $errors = array();

	/** @var array */
	public $results = array();

	/** @var string */
	public $resprints = '';

	/**
	 *  Constructeur.
	 *
	 *  @param  DoliDB  $db  Handler BDD
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 *  Détermine si le bouton doit être actif ou grisé, et pour quelle raison.
	 *
	 *  @param  Facture  $object  Facture courante
	 *  @return string|null       null si le bouton est actif, sinon clé de traduction de la raison du grisage
	 */
	private function getDisabledReason($object)
	{
		if ($object->type != Facture::TYPE_STANDARD) {
			return 'LemonOupsReasonNotStandard';
		}
		if ($object->statut == Facture::STATUS_DRAFT) {
			return 'LemonOupsReasonDraft';
		}
		if ($object->statut != Facture::STATUS_VALIDATED) {
			return 'LemonOupsReasonNotValidated';
		}
		if (method_exists($object, 'is_erasable') && $object->is_erasable() > 0) {
			return 'LemonOupsReasonErasable';
		}
		if ($object->paye != 0) {
			return 'LemonOupsReasonAlreadyPaid';
		}
		if ($object->getSommePaiement() != 0) {
			return 'LemonOupsReasonPartialPayment';
		}
		if ($object->getSumDepositsUsed() != 0) {
			return 'LemonOupsReasonDepositUsed';
		}
		if ($object->getSumCreditNotesUsed() != 0) {
			return 'LemonOupsReasonCreditNoteUsed';
		}
		return null;
	}

	/**
	 *  Hook : ajoute le bouton "Émettre un avoir et solder" en bas de la fiche facture.
	 *
	 *  @param  array            $parameters   Contextes et paramètres du hook
	 *  @param  CommonObject     $object       Objet courant de la page (Facture)
	 *  @param  string           $action       Action en cours
	 *  @param  HookManager      $hookmanager  Instance du gestionnaire de hooks
	 *  @return int                            0 : continuer le traitement normal
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;

		if (!isModEnabled('lemonoups')) {
			return 0;
		}
		$contexts = explode(':', (string) ($parameters['context'] ?? ''));
		if (!in_array('invoicecard', $contexts)) {
			return 0;
		}
		if (empty($object) || !($object instanceof Facture) || empty($object->id)) {
			return 0;
		}

		if ($this->getDisabledReason($object) !== null) {
			return 0;
		}

		$langs->load('lemonoups@lemonoups');

		// L'action mute la DB : on force un POST plutôt qu'un simple lien GET,
		// pour éviter les rejeux via image tag ou préchargement navigateur.
		// On ne peut pas émettre un <form> ici (la fiche facture contient déjà un
		// <form> parent, les formulaires imbriqués sont invalides en HTML et ignorés
		// par le navigateur). Solution : un lien classique stylé butActionDelete,
		// intercepté par un onclick qui crée un form POST dynamique et le soumet.
		$confirm = $langs->trans('LemonOupsConfirmAction', $object->ref);
		$tooltip = $langs->trans('LemonOupsBtnTooltipActive');
		$label = $langs->trans('LemonOupsBtnLabel');
		$token = newToken();
		$action = $_SERVER["PHP_SELF"];

		$onclick =
			"event.preventDefault();".
			"if(!confirm('".dol_escape_js($confirm)."'))return false;".
			"var f=document.createElement('form');".
			"f.method='POST';f.action='".dol_escape_js($action)."';".
			"var d={id:'".((int) $object->id)."',action:'lemon_annuler',token:'".dol_escape_js($token)."'};".
			"for(var k in d){var i=document.createElement('input');i.type='hidden';i.name=k;i.value=d[k];f.appendChild(i);}".
			"document.body.appendChild(f);f.submit();".
			"return false;";

		print dolGetButtonAction(
			$tooltip,
			$label,
			'delete',
			'#',
			'',
			true,
			array('attr' => array('onclick' => $onclick))
		);

		return 0;
	}

	/**
	 *  Hook : traite l'action lemon_annuler.
	 *
	 *  Enchaîne en transaction atomique :
	 *    1. Création de l'avoir (type CREDIT_NOTE) avec les mêmes lignes que la facture
	 *    2. Validation de l'avoir
	 *    3. Création d'une DiscountAbsolute par taux de TVA (= conversion en crédit)
	 *    4. Marquage de l'avoir comme payé (son crédit est consommé)
	 *    5. Imputation des discounts sur la facture d'origine via insert_discount()
	 *    6. Marquage de la facture d'origine comme payée
	 *
	 *  @param  array            $parameters   Contextes et paramètres
	 *  @param  CommonObject     $object       Facture courante
	 *  @param  string           $action       Action en cours
	 *  @param  HookManager      $hookmanager  Gestionnaire de hooks
	 *  @return int                            0 : OK ; <0 : erreur
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $db, $user, $langs;

		if (!isModEnabled('lemonoups')) {
			return 0;
		}
		$contexts = explode(':', (string) ($parameters['context'] ?? ''));
		if (!in_array('invoicecard', $contexts)) {
			return 0;
		}
		if ($action !== 'lemon_annuler') {
			return 0;
		}
		if (empty($object) || !($object instanceof Facture)) {
			return 0;
		}

		$langs->load('lemonoups@lemonoups');

		// POST-only : le 3e paramètre de GETPOST force la lecture depuis $_POST uniquement.
		if (GETPOST('token', 'alpha', 2) !== newToken()) {
			setEventMessages($langs->trans('ErrorBadToken'), null, 'errors');
			$action = '';
			return 0;
		}

		if (!$user->hasRight('facture', 'creer')) {
			accessforbidden();
		}

		if ($this->getDisabledReason($object) !== null) {
			setEventMessages($langs->trans('LemonOupsErrorConditions'), null, 'errors');
			$action = '';
			return 0;
		}

		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
		require_once DOL_DOCUMENT_ROOT.'/core/class/discount.class.php';

		$db->begin();
		$error = 0;

		$avoir = $this->createCreditNote($object, $user);
		if ($avoir === null) {
			$error++;
		}

		if (!$error) {
			$result = $avoir->validate($user);
			if ($result <= 0) {
				$this->logError('validate', $avoir);
				$error++;
			}
		}

		if (!$error) {
			$result = $this->convertToDiscounts($avoir, $user);
			if ($result < 0) {
				$error++;
			}
		}

		if (!$error) {
			$result = $avoir->setPaid($user);
			if ($result < 0) {
				$this->logError('setPaid avoir', $avoir);
				$error++;
			}
		}

		if (!$error) {
			$result = $this->applyDiscountsToInvoice($object, $avoir, $user);
			if ($result < 0) {
				$error++;
			}
		}

		if (!$error) {
			$object->fetch($object->id);
			if ($object->getRemainToPay(0) <= 0) {
				$result = $object->setPaid($user);
				if ($result < 0) {
					$this->logError('setPaid facture', $object);
					$error++;
				}
			}
		}

		if ($error) {
			$db->rollback();
			setEventMessages($langs->trans('LemonOupsErrorAction'), null, 'errors');
			$action = '';
			return -1;
		}

		$db->commit();
		setEventMessages(
			$langs->trans('LemonOupsActionSuccess', $object->ref, $avoir->ref),
			null,
			'mesgs'
		);

		header('Location: '.$_SERVER["PHP_SELF"].'?id='.((int) $object->id));
		exit;
	}

	/**
	 *  Crée l'avoir brouillon avec les mêmes lignes que la facture d'origine,
	 *  signes inversés (reproduit la logique native de card.php invoiceAvoirWithLines=1).
	 *
	 *  @param  Facture  $source  Facture d'origine
	 *  @param  User     $user    Utilisateur courant
	 *  @return Facture|null      Avoir créé (brouillon) ou null si erreur
	 */
	private function createCreditNote($source, $user)
	{
		$avoir = new Facture($this->db);
		$avoir->socid = $source->socid;
		$avoir->date = dol_now();
		$avoir->cond_reglement_id = 0;
		$avoir->mode_reglement_id = $source->mode_reglement_id;
		$avoir->fk_project = $source->fk_project;
		$avoir->model_pdf = $source->model_pdf;
		$avoir->fk_facture_source = $source->id;
		$avoir->type = Facture::TYPE_CREDIT_NOTE;
		$avoir->entity = $source->entity;

		$id = $avoir->create($user);
		if ($id <= 0) {
			$this->logError('create avoir', $avoir);
			return null;
		}

		if (!empty($source->lines)) {
			$negatedFields = array(
				'subprice', 'total_ht', 'total_tva', 'total_ttc',
				'total_localtax1', 'total_localtax2',
				'multicurrency_subprice', 'multicurrency_total_ht',
				'multicurrency_total_tva', 'multicurrency_total_ttc',
			);
			foreach ($source->lines as $line) {
				// Lignes spéciales (titres, sous-totaux — product_type >= 9) : ignorées.
				// Elles ne seraient pas converties en discount par la suite et créeraient
				// une incohérence entre total avoir et somme des discounts.
				if ($line->product_type >= 9) {
					continue;
				}
				if (method_exists($line, 'fetch_optionals')) {
					$line->fetch_optionals();
				}
				// Clone pour ne pas muter les lignes de la facture source en mémoire
				// (un hook tiers lisant $source->lines après coup verrait sinon des valeurs négatives).
				$newline = clone $line;
				$newline->fk_facture = $avoir->id;
				$newline->fk_parent_line = 0;
				foreach ($negatedFields as $field) {
					$newline->$field = -$newline->$field;
				}
				$newline->context['createcreditnotefrominvoice'] = 1;
				$result = $newline->insert(0, 1);
				if ($result < 0) {
					$this->logError('insert line', $newline);
					return null;
				}
				$avoir->lines[] = $newline;
			}
			$avoir->update_price(1);
		}

		return $avoir;
	}

	/**
	 *  Convertit l'avoir validé en DiscountAbsolute : une entrée par taux de TVA
	 *  (reproduit la logique native de card.php confirm_converttoreduc).
	 *
	 *  @param  Facture  $avoir  Avoir validé
	 *  @param  User     $user   Utilisateur courant
	 *  @return int              >=0 OK, <0 erreur
	 */
	private function convertToDiscounts($avoir, $user)
	{
		// Une entrée par taux de TVA (clé = "taux" ou "taux (code_source)")
		$sums = array();
		foreach ($avoir->lines as $line) {
			if ($line->product_type >= 9 || $line->total_ht == 0) {
				continue;
			}
			$key = $line->tva_tx.($line->vat_src_code ? ' ('.$line->vat_src_code.')' : '');
			if (!isset($sums[$key])) {
				$sums[$key] = array('ht' => 0, 'tva' => 0, 'ttc' => 0, 'mc_ht' => 0, 'mc_tva' => 0, 'mc_ttc' => 0);
			}
			$sums[$key]['ht']     += $line->total_ht;
			$sums[$key]['tva']    += $line->total_tva;
			$sums[$key]['ttc']    += $line->total_ttc;
			$sums[$key]['mc_ht']  += $line->multicurrency_total_ht;
			$sums[$key]['mc_tva'] += $line->multicurrency_total_tva;
			$sums[$key]['mc_ttc'] += $line->multicurrency_total_ttc;
		}

		foreach ($sums as $key => $s) {
			$discount = new DiscountAbsolute($this->db);
			$discount->description = '(CREDIT_NOTE)';
			$discount->fk_soc = $avoir->socid;
			$discount->socid = $avoir->socid;
			$discount->fk_facture_source = $avoir->id;

			// Dolibarr natif écrit amount_* ET total_* avec les mêmes valeurs : on reproduit.
			$discount->amount_ht  = $discount->total_ht  = -((float) $s['ht']);
			$discount->amount_tva = $discount->total_tva = -((float) $s['tva']);
			$discount->amount_ttc = $discount->total_ttc = -((float) $s['ttc']);
			$discount->multicurrency_amount_ht  = $discount->multicurrency_total_ht  = -((float) $s['mc_ht']);
			$discount->multicurrency_amount_tva = $discount->multicurrency_total_tva = -((float) $s['mc_tva']);
			$discount->multicurrency_amount_ttc = $discount->multicurrency_total_ttc = -((float) $s['mc_ttc']);

			$vat_src_code = '';
			$tva_tx = $key;
			if (preg_match('/\((.*)\)/', $key, $reg)) {
				$vat_src_code = $reg[1];
				$tva_tx = preg_replace('/\s*\(.*\)/', '', $key);
			}
			$discount->tva_tx = abs((float) $tva_tx);
			$discount->vat_src_code = $vat_src_code;

			if ($discount->create($user) < 0) {
				$this->logError('create discount', $discount);
				return -1;
			}
		}

		return 1;
	}

	/**
	 *  Récupère les DiscountAbsolute issus EXCLUSIVEMENT de l'avoir qu'on vient de créer
	 *  et les consomme sur la facture d'origine via insert_discount().
	 *
	 *  Règle stricte : aucun autre crédit disponible du client n'est touché, même si
	 *  le client en a d'autres (anciens avoirs, remises globales). On filtre par
	 *  fk_facture_source = id de notre avoir.
	 *
	 *  @param  Facture  $facture  Facture d'origine à solder
	 *  @param  Facture  $avoir    Avoir qu'on vient de créer
	 *  @param  User     $user     Utilisateur courant
	 *  @return int                >=0 OK, <0 erreur
	 */
	private function applyDiscountsToInvoice($facture, $avoir, $user)
	{
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe_remise_except";
		$sql .= " WHERE fk_facture_source = ".((int) $avoir->id);
		$sql .= " AND (fk_facture IS NULL OR fk_facture = 0)";
		$sql .= " AND (fk_facture_line IS NULL OR fk_facture_line = 0)";
		$sql .= " AND entity IN (".getEntity('invoice').")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(get_class($this)."::applyDiscountsToInvoice SQL error: ".$this->db->lasterror(), LOG_ERR);
			return -1;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$result = $facture->insert_discount((int) $obj->rowid);
			if ($result < 0) {
				$this->logError('insert_discount', $facture);
				$this->db->free($resql);
				return -1;
			}
		}
		$this->db->free($resql);

		return 1;
	}

	/**
	 *  Log une erreur survenue sur un objet Dolibarr (avec error/errors).
	 *
	 *  @param  string       $step    Étape en cours (pour le message)
	 *  @param  object       $object  Objet ayant produit l'erreur
	 *  @return void
	 */
	private function logError($step, $object)
	{
		$msg = 'LemonOups: échec '.$step;
		if (!empty($object->error)) {
			$msg .= ': '.$object->error;
		}
		if (!empty($object->errors) && is_array($object->errors)) {
			$msg .= ' ['.implode(' | ', $object->errors).']';
		}
		dol_syslog($msg, LOG_ERR);
	}
}
