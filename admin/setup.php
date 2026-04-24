<?php
/* Copyright (C) 2026 Lemon <hello@hellolemon.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * Page d'accueil du module LemonOups (pas de configuration à régler,
 * affiche uniquement le bandeau de mise à jour et le bloc "À propos").
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once dol_buildpath('/lemonoups/core/lib/lemonoups.lib.php');

if (!$user->admin) {
	accessforbidden();
}

$langs->loadLangs(["admin", "lemonoups@lemonoups"]);

llxHeader('', $langs->trans("LemonOupsSetup"));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("LemonOupsSetup"), $linkback, 'title_setup');

// Bandeau "Nouvelle version disponible" si le check GitHub remonte une version > locale
require_once dirname(__DIR__).'/core/modules/modLemonOups.class.php';
$modDesc = new modLemonOups($db);
$updateInfo = lemonoups_check_latest_release($db, $modDesc->version);
if ($updateInfo !== null) {
	print '<div class="warning" style="margin:8px 0;padding:10px;border-left:4px solid #e67e22;background:#fff3e0;">';
	print '<strong>'.$langs->trans("LemonOupsUpdateAvailable").'</strong> : ';
	print $langs->trans("LemonOupsUpdateAvailableMsg", dol_escape_htmltag($updateInfo['version']), dol_escape_htmltag($modDesc->version));
	print ' <a href="'.dol_escape_htmltag($updateInfo['url']).'" target="_blank" rel="noopener">'.$langs->trans("LemonOupsUpdateSeeRelease").'</a>';
	print '</div>';
}

// Bloc d'information : aucune configuration à régler
print '<div style="margin:20px 0;padding:15px 20px;border:1px solid #cfe2ff;background:#f5faff;border-left:4px solid #4a90e2;border-radius:4px;">';
print '<p style="margin:0 0 8px 0;"><strong>'.$langs->trans("LemonOupsNoConfigTitle").'</strong></p>';
print '<p style="margin:0;color:#555;">'.$langs->trans("LemonOupsNoConfigDesc").'</p>';
print '</div>';

// Bloc "À propos de Lemon" — vitrine éditeur
print '<div style="margin:30px 0;padding:20px 25px;border:1px solid #e0e0e0;border-left:4px solid #FFD21F;border-radius:6px;background:linear-gradient(135deg,#fffef7 0%,#fafafa 100%);">';
print '<h3 style="margin:0 0 10px 0;color:#333;">'.$langs->trans("LemonOupsAboutTitle").'</h3>';
print '<p style="margin:0 0 12px 0;color:#555;">'.$langs->trans("LemonOupsAboutIntro").'</p>';
print '<ul style="margin:0 0 15px 20px;color:#555;">';
print '<li><strong>'.$langs->trans("LemonOupsAboutSvc1Title").'</strong> : '.$langs->trans("LemonOupsAboutSvc1Desc").'</li>';
print '<li><strong>'.$langs->trans("LemonOupsAboutSvc2Title").'</strong> : '.$langs->trans("LemonOupsAboutSvc2Desc").'</li>';
print '<li><strong>'.$langs->trans("LemonOupsAboutSvc3Title").'</strong> : '.$langs->trans("LemonOupsAboutSvc3Desc").'</li>';
print '<li><strong>'.$langs->trans("LemonOupsAboutSvc4Title").'</strong> : '.$langs->trans("LemonOupsAboutSvc4Desc").'</li>';
print '</ul>';
print '<p style="margin:0;">';
print '<a href="https://hellolemon.fr" target="_blank" rel="noopener" class="butAction" style="text-decoration:none;">'.$langs->trans("LemonOupsAboutCTA").'</a>';
print ' <span style="color:#999;margin-left:15px;">'.$langs->trans("LemonOupsAboutLocation").'</span>';
print '</p>';
print '</div>';

llxFooter();
$db->close();
