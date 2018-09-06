<?php
/**
 * Role Editing Page
 *
 * Copyright 2004 (c) GForge LLC
 * Copyright 2010, Roland Mas
 * Copyright (C) 2011 Alain Peyrat - Alcatel-Lucent
 * Copyright © 2011
 *	Thorsten Glaser <t.glaser@tarent.de>
 * Copyright 2014, Stéphane-Eymeric Bredthauer
 * Copyright 2014, Franck Villaume - TrivialDev
 * Copyright 2016-2018, Henry Kwong, Tod Hing - SimTK Team
 *
 * This file is part of FusionForge. FusionForge is free software;
 * you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the Licence, or (at your option)
 * any later version.
 *
 * FusionForge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with FusionForge; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

require_once '../../env.inc.php';
require_once $gfcommon.'include/pre.php';
require_once $gfwww.'project/admin/project_admin_utils.php';
require_once $gfcommon.'include/Role.class.php';
require_once $gfcommon.'include/rbac_texts.php';

$group_id = getIntFromRequest('group_id');
session_require_perm ('project_admin', $group_id) ;

$role_id = getStringFromRequest('role_id');
$data = getStringFromRequest('data');

$group = group_get_object($group_id);

if (getStringFromRequest('delete')) {
	session_redirect('/project/admin/roledelete.php?group_id='.$group_id.'&role_id='.$role_id);
}

if (getStringFromRequest('add')) {
	$role_name = trim(getStringFromRequest('role_name')) ;
	$role = new Role ($group) ;
	$role_id=$role->createDefault($role_name) ;
}
else {
	$role = RBACEngine::getInstance()->getRoleById($role_id) ;
}
if (!$role || !is_object($role)) {
	exit_error(_('Could Not Get Role'),'admin');
}
elseif ($role->isError()) {
	exit_error($role->getErrorMessage(),'admin');
}

$old_data = $role->getSettingsForProject ($group) ;
$new_data = array () ;

if (!is_array ($data)) {
	$data = array () ;
}
foreach ($old_data as $section => $values) {
	if (!array_key_exists ($section, $data)) {
		continue ;
	}
	foreach ($values as $ref_id => $val) {
		if (!array_key_exists ($ref_id, $data[$section])) {
			continue ;
		}
		$new_data[$section][$ref_id] = $data[$section][$ref_id] ;
	}
}
$data = $new_data ;
if (getStringFromRequest('submit')) {
	if (($role->getHomeProject() != NULL) && 
		($role->getHomeProject()->getID() == $group_id)) {
		$role_name = trim(getStringFromRequest('role_name'));
		$public = getIntFromRequest('public') ? true : false ;
	}
	else {
		$role_name = $role->getName() ;
		$public = $role->isPublic() ;
	}
	if (!$role_name) {
		$error_msg .= 'Missing Role Name';
	}
	else if (strtolower(trim($role_name)) == "admin") {
		$error_msg .= 'Admin role settings cannot be edited';
	}
	else {
		if (!$role_id) {
			$role_id = $role->create($role_name, $data);
			if (!$role_id) {
				$error_msg .= $role->getErrorMessage();
			}
			else {
				$feedback = _('Successfully created new role.');
				$group->addHistory(_('Added Role'), $role_name);
			}
		}
		else {
			if ($role instanceof RoleExplicit) {
				$role->setPublic($public) ;
			}
			if (!$role->update($role_name, $data, false)) {
				$error_msg .= $role->getErrorMessage();
			}
			else {

				// Compare data to be sent against current values 
				// to see which values have been updated.
				$arrDiffSections = array();
				foreach ($data as $section=>$values) {
					// Get set of old values for the given section.
					$strOldPerms = "";
					foreach ($old_data[$section] as $ref_id => $val) {
						$strOldPerms .= $val;
					}

					// Get new set of values from UI.
					$strNewPerms = "";
					foreach ($values as $ref_id => $val) {
						$strNewPerms .= $val;
					}

					// Is the given section different?
					if ($strOldPerms != $strNewPerms) {
						// Different.
						$arrDiffSections[$section] = $section;
					}
				} 

				if (isset($arrDiffSections["plugin_moinmoin_access"])) {
					// MoinMoin change is amongst the new values.
					// Include the "MoinMoin access" keyword to get cronjob 
					// to reload in order to effect the role change in MoinMoin.
					$feedback = "Successfully updated role. " .
						"MoinMoin setting may take up to 15 minutes to take effect.";
					$group->addHistory('Updated Role: MoinMoin access', $role_name);
				}
				else {
					// No MoinMoin change.
					$feedback = 'Successfully updated role.';
					$group->addHistory('Updated Role', $role_name);
				}
			}
		}
	}
}

if (!$role_id) {
	$title= _('New Role');
}
else {
	$title= _('Edit Role');
}

project_admin_header(array('title'=> $title, 'group'=>$group_id));

if (strtolower(trim($role->getName())) != "admin") {
	echo '<p>Use this page to edit the permissions attached to each role.  Note that each role has at least as much access as the Anonymous and LoggedIn roles.  For example, if the Anonymous role has read access to a forum, all other roles will have it too.</p>';
}
else {
	// Disable all inputs and selects when role is "Admin", i.e. the page is view-only.
	echo '<p>View only.  Admin role settings cannot be edited.</p>';
	echo '
	<script>
	$(document).ready(function() {
		// Disable input.
		$("input").attr("disabled", "disabled");
		// Disable select.
		$("select").attr("disabled", "disabled");
	});
	</script>';
}

echo '<form action="' . getStringFromServer('PHP_SELF') .
	'?group_id=' . $group_id . 
	'&amp;role_id=' . $role_id . 
	'" method="post">';

if ($role->getHomeProject() == NULL || 
	$role->getHomeProject()->getID() != $group_id) {
	echo '<p><strong>Role Name</strong></p>' ;
	echo $role->getDisplayableName ($group) ;
}
else {
	echo '<p><strong>Role Name</strong><br />';
	echo '<input type="text" name="role_name" value="' .
		$role->getName() .
		'" required="required" /><br />';
}

$titles[]=_('Section');
$titles[]=_('Subsection');
$titles[]=_('Setting');

setup_rbac_strings();

echo $HTML->listTableTop($titles);

// Get the keys for this role and interate to build page
// Everything is built on the multi-dimensial arrays in the Role object
$j = 0;
$keys = array_keys($role->getSettingsForProject($group));
$keys2 = array () ;
foreach ($keys as $key) {
	if (!in_array ($key, $role->global_settings)) {
		$keys2[] = $key;
	}
}
$keys = $keys2 ;
for ($i=0; $i<count($keys); $i++) {
        if ((!$group->usesTracker() && preg_match("/tracker/", $keys[$i])) ||
                (!$group->usesPM() && preg_match("/pm/", $keys[$i])) ||
                (!$group->usesFRS() && preg_match("/frs/", $keys[$i])) ||
                (!$group->usesSCM() && preg_match("/scm/", $keys[$i])) ||
                (!$group->usesDocman() && preg_match("/docman/", $keys[$i]))) {
                // We don't display modules not used
		continue;
	}

	if (preg_match("/^plugin_([a-z]*)/", $keys[$i], $matches)) {
		$p = $matches[1];
		if (!$group->usesPlugin($p)) {
			// We don't display settings for unused plugins either
			continue;
		}
	}

	if ($keys[$i] == 'pm' || $keys[$i] == 'pmpublic') {

		$res=db_query_params ('SELECT group_project_id,project_name
			FROM project_group_list WHERE group_id=$1',
			array($group_id));
		for ($q=0; $q<db_numrows($res); $q++) {

			$sectionName = $rbac_edit_section_names[$keys[$i]];
			if ($sectionName == "Tasks") {
				// Skip all Tasks.
				continue;
			}
			echo '<tr '. $HTML->boxGetAltRowStyle($j++) . '>
			<td style="padding-left: 4em;">'. $sectionName .'</td>
			<td>'.db_result($res,$q,'project_name').'</td>
			<td>'.html_build_select_box_from_assoc(
				$role->getRoleVals($keys[$i]),
				"data[".$keys[$i]."][".db_result($res,$q,'group_project_id')."]",
				$role->getVal($keys[$i],db_result($res,$q,'group_project_id')),
				false, false ).'</td></tr>';
		}
	}
	elseif ($keys[$i] == 'tracker' || $keys[$i] == 'trackerpublic' || $keys[$i] == 'trackeranon') {
		// Handle tracker settings for all roles

		if ($keys[$i] == 'trackeranon') {
			//skip as we have special case below
		}
		else {
			$res=db_query_params ('SELECT group_artifact_id,name
				FROM artifact_group_list WHERE group_id=$1',
			array($group_id));
			for ($q=0; $q<db_numrows($res); $q++) {
				// Special cases - when going through the keys, we want to show trackeranon
				// on the same line as tracker public
				if ($keys[$i] == 'trackerpublic') {
					$txt = ' &nbsp; '.html_build_select_box_from_assoc(
					$role->getRoleVals('trackeranon'),
					"data[trackeranon][".db_result($res,$q,'group_artifact_id')."]",
					$role->getVal('trackeranon',db_result($res,$q,'group_artifact_id')),
					false, false );
				}
				else {
					$txt='';
				}


				// Replace section names to match better with UI.
				$sectionName = $rbac_edit_section_names[$keys[$i]];
				if ($sectionName == "Tracker") {
					$sectionName = "Issue tracker";
				}

				echo '<tr '. $HTML->boxGetAltRowStyle($j++) . '>
				<td style="padding-left: 4em;">'. $sectionName .'</td>
				<td>'.db_result($res,$q,'name').'</td>
				<td>'.html_build_select_box_from_assoc(
					$role->getRoleVals($keys[$i]),
					"data[".$keys[$i]."][".db_result($res,$q,'group_artifact_id')."]",
					$role->getVal($keys[$i],db_result($res,$q,'group_artifact_id')),
					false, false ). $txt .'</td></tr>';
			}
		}
	}
	elseif ($keys[$i] == 'frspackage') {
		// File release system - each package can be public/private

		$res=db_query_params ('SELECT package_id,name,is_public
			FROM frs_package WHERE group_id=$1',
			array($group_id));
		for ($q=0; $q<db_numrows($res); $q++) {
			echo '<tr '. $HTML->boxGetAltRowStyle($j++) . '>
			<td>'.$rbac_edit_section_names[$keys[$i]].'</td>
			<td>'.db_result($res,$q,'name').'</td>
			<td>'.html_build_select_box_from_assoc(
				$role->getRoleVals($keys[$i]),
				"data[".$keys[$i]."][".db_result($res,$q,'package_id')."]",
				$role->getVal($keys[$i],db_result($res,$q,'package_id')),
				false, false ).'</td></tr>';
		}
	}
	else {
		// Handle all other settings for all roles

		if ($keys[$i] == "new_forum" ||
			$keys[$i] == "forum" ||
			$keys[$i] == "forum_admin") {
			// Skip all forum roles.
			continue;
		}
		else if ($keys[$i] == "pm_admin" ||
			$keys[$i] == "new_pm") {
			// Skip "task managers".
			continue;
		}
		else if ($keys[$i] == "project_read") {
			// NOTE: Need to set project_read to 1 in a hidden data field.
			// Otherwise, the default is 0 and section values cannot be set!!!
			echo '<input type="hidden" ' .
				'name="data[' . $keys[$i] . '][' . $group_id . ']" ' .
				'value="1" />';
			continue;
		}

		// Replace section names to match better with UI.
		$sectionName = $rbac_edit_section_names[$keys[$i]];
		if ($sectionName == "SCM") {
			$sectionName = "Source code manager";
		}
		else if ($sectionName == "Files") {
			$sectionName = "Downloads";
		}
		else if ($sectionName == "Trackers administration") {
			$sectionName = "Issue trackers administration";
		}
		else if ($sectionName == "Default for new trackers") {
			$sectionName = "Default for new issue trackers ";
		}
		echo '<tr '. $HTML->boxGetAltRowStyle($j++) . '>
		<td colspan="2"><strong>'. $sectionName .'</strong></td>
		<td>';
		echo html_build_select_box_from_assoc($role->getRoleVals($keys[$i]), "data[".$keys[$i]."][$group_id]", $role->getVal($keys[$i],$group_id), false, false ) ;
		echo '</td>
		</tr>';

	}
}

echo $HTML->listTableBottom();

if (strtolower(trim($role->getName())) != "admin") {
	// Show Submit button only role is not "Admin".
	echo '<p><input type="submit" name="submit" value="Submit" class="btn-cta" /></p>';
}

echo '</form>';

project_admin_footer(array());

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
