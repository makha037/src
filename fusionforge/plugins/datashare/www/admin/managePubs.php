<?php

/**
 *
 * datashare plugin managePubs.php
 *
 * admin page for managing publications.
 *
 * Copyright 2005-2022, SimTK Team
 *
 * This file is part of the SimTK web portal originating from
 * Simbios, the NIH National Center for Physics-Based
 * Simulation of Biological Structures at Stanford University,
 * funded under the NIH Roadmap for Medical Research, grant
 * U54 GM072970, with continued maintenance and enhancement
 * funded under NIH grants R01 GM107340 & R01 GM104139, and
 * the U.S. Army Medical Research & Material Command award
 * W81XWH-15-1-0232R01.
 *
 * SimTK is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as
 * published by the Free Software Foundation, either version 3 of
 * the License, or (at your option) any later version.
 *
 * SimTK is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public
 * License along with SimTK. If not, see
 * <http://www.gnu.org/licenses/>.
 */

require_once '../../../env.inc.php';
require_once $gfcommon.'include/pre.php';
require_once '../datashare-utils.php';
require_once $gfplugins.'datashare/include/Datashare.class.php';
require_once $gfwww.'project/project_utils.php';

// Override and use the configuration parameters as specified in
// the file datashare.ini if the file is present and has the relevant parameters.
if (file_exists("/etc/gforge/config.ini.d/datashare.ini")) {
	// The file datashare.ini is present.
	$arrDatashareConfig = parse_ini_file("/etc/gforge/config.ini.d/datashare.ini");

	// Check for each parameter's presence.
	if (isset($arrDatashareConfig["datashare_server"])) {
		$datashareServer = $arrDatashareConfig["datashare_server"];
	}
}
if (!isset($datashareServer)) {
	exit_error("Cannot get datashare server");
}


$group_id = getIntFromRequest('group_id');
$study_id = getIntFromRequest('study_id');

if (!$group_id) {
	exit_no_group();
}
$group = group_get_object($group_id);
if (!$group || !is_object($group)) {
	exit_no_group();
}
elseif ($group->isError()) {
	exit_error($group->getErrorMessage(), 'datashare');
}

if (!session_loggedin()) {
	exit_not_logged_in();
}

if (!forge_check_perm('datashare', $group_id, 'write')) {
	exit_permission_denied('You cannot manage citations unless you are an admin on that project.', 'home');
}

// get Datashare object
$study = new Datashare($group_id);

if (!$study || !is_object($study)) {
	exit_error('Error','Could Not Create Study Object');
}
elseif ($study->isError()) {
	exit_error($study->getErrorMessage(), 'Datashare Error');
}

$study_results = $study->getStudy($study_id);
$studyTitle = $study_results[0]->title;

$func = htmlspecialchars(getStringFromRequest('func'));
if ($func == "delete_citation") {
	$citation_id = getIntFromRequest('citation_id');
	$sure = getIntFromRequest('sure');
	$really_sure = getIntFromRequest('really_sure');

	if (!$sure || !$really_sure) {
		$error_msg = 'Citation not deleted: you did not check "I am Sure" and "I am Really Sure"';
	}
	else {
		$res = db_query_params("SELECT citation_id FROM plugin_datashare_citation WHERE " .
			"citation_id=$1",
			array($citation_id)
		);
		if (!$res) {
			$error_msg = "Citation not deleted: cannot read table";
		}
		else if (db_numrows($res) < 1) {
			$error_msg = "Citation not deleted: the citation does not exist";
		}
		else {
			db_begin();
			$res = db_query_params("DELETE FROM plugin_datashare_citation " .
				"WHERE citation_id=$1", array($citation_id));
			if (!$res || db_affected_rows($res) < 1) {
				$error_msg = "Error deleting citation";
				db_rollback();
			}
			else {
				$feedback = "Citation Deleted";
				db_commit();
			}
		}
	}
}

datashare_header(array('title'=>'Datashare'),$group_id);
echo "<div><h4>" . $studyTitle . "<p/></h4></div>";

echo "<div class=\"project_overview_main\">";
echo "<div style=\"display: table; width: 100%;\">";
echo "<div class=\"main_col\">";

$study->displayCitations($group_id, $study_id, true);

echo "</div><!--main_col-->\n";

// Add side bar to show statistics and project leads.
constructSideBar($group);

echo "</div><!--display table-->\n</div><!--project_overview_main-->\n";

datashare_footer(array());


// Retrieve the error message and names of components to flag.
function retrieveErrorMessages($error_msg, &$arrErrors) {

        // Error messages are separated using "^" as delimiter.
        $arrErrors = explode("^", $error_msg);

        // The error message is the last token.
        // Note: "^" can be not present in the string; i.e. "".
        $error_msg = $arrErrors[count($arrErrors) - 1];

        return $error_msg;
}

// Construct side bar
function constructSideBar($groupObj) {

	if ($groupObj == null) {
		// Group object not available.
		return;
	}

	echo '<div class="side_bar">';

	// Statistics.
	displayStatsBlock($groupObj);

	// Get project leads.
	$project_admins = $groupObj->getLeads();
	displayCarouselProjectLeads($project_admins);

	echo '</div>';
}


// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
